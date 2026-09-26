<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Measures what the cache layer is actually worth, in whichever environment
 * it runs.
 *
 * It exists because the question "does Redis help?" has a different answer
 * locally and in production, and only one of those can be measured from a
 * developer machine. On a Windows dev box Redis sits behind Docker Desktop's
 * port proxy (~2-4ms per round trip) while MySQL is a direct localhost
 * connection, so Redis measures SLOWER there. On Render the arrangement is
 * reversed — Aiven MySQL is an external TLS host, Render Key Value is on the
 * internal network — but nobody had numbers for that, only the reasoning in
 * project.md v7.4. This class is how the numbers get collected: the same
 * measurements run from the console locally and from a token-gated route in
 * production, so the two are directly comparable.
 *
 * Read paths talk to each store DIRECTLY rather than through Setting::get(),
 * whose Cache::memo() wrapper holds the value in PHP memory for the life of
 * the process. In a web request that is the point — one real cache read per
 * request — but in a measurement loop it would make every iteration after
 * the first a free array lookup and report all stores as equally instant.
 */
class CacheDiagnostics
{
    /** Stores to compare, in the order a report should read. */
    public const STORES = ['redis', 'database', 'file'];

    /**
     * Settings read by the landing page. Sampled from the real keys so the
     * query count reflects an actual page render rather than a round number.
     */
    public const LANDING_PAGE_SETTING_READS = 18;

    /** Keys written during measurement, namespaced so they can't collide. */
    private const PROBE_PREFIX = 'cache_diagnostics_probe_';

    /**
     * Upper bound on the measurement loop.
     *
     * `?iterations=` comes straight off the query string, and each iteration is
     * a real round trip to Redis, MySQL and disk — three stores, twice over. A
     * floor alone left the ceiling at PHP_INT_MAX, so one request could pin the
     * production container until it timed out. 500 is already far more samples
     * than the timings need to stop moving.
     */
    public const MAX_ITERATIONS = 500;

    /** Clamps `?iterations=` into the range the loop is allowed to run. */
    public static function clampIterations(int $iterations): int
    {
        return max(1, min($iterations, self::MAX_ITERATIONS));
    }

    public static function measure(int $iterations = 100): array
    {
        $iterations = self::clampIterations($iterations);

        return [
            'environment' => self::environment(),
            'redis' => self::redisStatus(),
            'queries_eliminated' => self::queriesEliminated(),
            'transport' => self::transportCost($iterations),
            'read_cost_ms' => self::readCost($iterations),
            'iterations' => $iterations,
        ];
    }

    /**
     * Which configuration this measurement was taken under — NOT where anything
     * lives. `db_host` used to be here and was removed in v7.40: this report is
     * served over HTTP by /diagnostics/cache, whose own comment promised
     * "timings only, never config values or credentials", and the production
     * database hostname is not a timing. It also added nothing — you already
     * know which deployment you queried, because you had to hold its
     * CRON_SECRET to ask.
     *
     * Everything kept here is a value the caller chose, not infrastructure they
     * could not otherwise name: the environment, which cache store is in front,
     * and which Redis client is compiled in. All three change what the numbers
     * below mean, which is the only reason this block exists.
     */
    private static function environment(): array
    {
        return [
            'app_env' => config('app.env'),
            'cache_store' => config('cache.default'),
            'redis_client' => config('database.redis.client'),
        ];
    }

    private static function redisStatus(): array
    {
        try {
            $info = Redis::connection('cache')->info();

            return [
                'reachable' => true,
                // predis and phpredis disagree on whether INFO comes back
                // flat or grouped under section headings.
                'version' => $info['redis_version'] ?? $info['Server']['redis_version'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            // The CLASS, not the message. Measured: a predis failure message is
            // "…failed … [tcp://127.0.0.1:6399]" — it carries the host and port
            // of whatever this deployment connects to, and this array is
            // returned over HTTP. (It does NOT carry the password even when the
            // URL has one, which was also measured — but the host is enough to
            // break the "never config values" promise.)
            //
            // Same rule GeminiService already follows for ConnectionException,
            // and for the same reason: an exception message is written for a
            // developer reading a log, not for an HTTP response body.
            //
            // The cost is that `cache:benchmark` now prints a class name instead
            // of a description. Acceptable: the actionable half of that output is
            // the "docker compose up -d redis" hint on the next line, and the
            // full exception is still in the log.
            return ['reachable' => false, 'error' => class_basename($e)];
        }
    }

    /**
     * The environment-independent figure: how much database work the cache
     * removes. The same number on a laptop and on Render — what changes
     * between them is only what each of those queries costs.
     */
    public static function queriesEliminated(): array
    {
        $cold = self::countQueries(function () {
            for ($i = 0; $i < self::LANDING_PAGE_SETTING_READS; $i++) {
                Setting::pluck('setting_value', 'setting_key')->all();
            }
        });

        // Populate the cache first: the query that fills it is paid once per
        // TTL by whichever request arrives first, not by the page being
        // measured. The steady state is what a visitor actually experiences.
        Setting::get('app_name');
        $warm = self::countQueries(function () {
            for ($i = 0; $i < self::LANDING_PAGE_SETTING_READS; $i++) {
                Setting::get('app_name');
            }
        });

        $dashboardCold = self::countQueries(fn () => self::dashboardStats());

        $key = self::PROBE_PREFIX.'dashboard';
        Cache::put($key, self::dashboardStats(), 300);
        $dashboardWarm = self::countQueries(fn () => Cache::get($key));
        Cache::forget($key);

        return [
            'landing_page_settings' => ['without_cache' => $cold, 'with_cache' => $warm],
            'admin_dashboard_stats' => ['without_cache' => $dashboardCold, 'with_cache' => $dashboardWarm],
        ];
    }

    /**
     * Pure transport overhead. A Redis PING does no work at all, so whatever
     * it costs is the round trip — which is the entire reason the local and
     * production answers differ. Paired with the cheapest possible query so
     * the two are comparable.
     */
    private static function transportCost(int $iterations): array
    {
        $result = [];

        try {
            $redis = Redis::connection('cache');
            $result['redis_ping_ms'] = self::time($iterations, fn () => $redis->ping());
        } catch (\Throwable $e) {
            $result['redis_ping_ms'] = null;
        }

        $result['mysql_select_1_ms'] = self::time($iterations, fn () => DB::select('SELECT 1'));

        return $result;
    }

    /** Per-read cost of the settings blob across each available store. */
    private static function readCost(int $iterations): array
    {
        $settings = Setting::pluck('setting_value', 'setting_key')->all();
        $key = self::PROBE_PREFIX.'settings';

        $result = [
            'no_cache_mysql_query' => self::time(
                $iterations,
                fn () => Setting::pluck('setting_value', 'setting_key')->all()
            ),
        ];

        foreach (self::STORES as $store) {
            $result[$store] = self::measureStore($store, $key, $settings, $iterations);
        }

        return $result;
    }

    /**
     * Writes the value into one store, then times reading it back. Returns
     * NULL when that store can't be reached — an unreachable Redis is a
     * result worth reporting, not a crash. Production reaches this through a
     * web route, so it must never throw.
     */
    private static function measureStore(string $store, string $key, mixed $value, int $iterations): ?float
    {
        try {
            $cache = Cache::store($store);
            $cache->put($key, $value, 300);

            // Warm-up: the first call pays for connecting / opening the file,
            // a one-off cost a real request doesn't repeat on every read.
            $cache->get($key);

            $elapsed = self::time($iterations, fn () => $cache->get($key));

            $cache->forget($key);

            return $elapsed;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The same counts DashboardController caches. Kept here rather than
     * called through the controller so this measures the queries and
     * nothing else.
     */
    private static function dashboardStats(): array
    {
        return [
            'todays_checkins' => Booking::whereDate('check_in_date', today())
                ->where('status', 'confirmed')->count(),
            'todays_checkouts' => Booking::whereDate('check_out_date', today())
                ->where('status', 'checked_in')->count(),
            'total_properties' => Property::count(),
            'current_guest' => Booking::where('status', 'checked_in')
                ->with('user:id,full_name')
                ->latest('check_in_date')
                ->first(),
        ];
    }

    /** Runs the operation once and reports how many DB queries it issued. */
    private static function countQueries(callable $operation): int
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $operation();

        $count = count(DB::connection()->getQueryLog());

        DB::connection()->disableQueryLog();
        DB::connection()->flushQueryLog();

        return $count;
    }

    /** Average milliseconds per iteration. */
    private static function time(int $iterations, callable $operation): float
    {
        // The query log grows unboundedly across hundreds of iterations and
        // would itself show up in the measurement.
        DB::connection()->disableQueryLog();

        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $operation();
        }

        return ((hrtime(true) - $start) / $iterations) / 1_000_000;
    }
}
