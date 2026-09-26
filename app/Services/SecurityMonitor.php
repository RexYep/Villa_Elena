<?php

namespace App\Services;

use App\Helpers\NotificationHelper;
use App\Models\StaffLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One place that records a security-relevant event and decides when it is worth
 * waking someone up for.
 *
 * WHY THIS EXISTS. Task 12 found that the protections built in Tasks 1–11 were
 * all silent. Measured, with a real authenticated request: an attacker account
 * asking for another guest's payment data got HTTP 403 and produced **zero log
 * lines, zero audit rows, and zero log levels invoked**. The guard worked; there
 * was no way to know it had been tested. The same was true of all twenty rate
 * limiters. A control that cannot be observed cannot be operated.
 *
 * THREE SINKS, DELIBERATELY DIFFERENT, because they answer different questions:
 *
 *   Log::warning   ALWAYS. Cheap (stderr on Render), full trail, greppable.
 *                  This is the forensic record — "what happened, in order".
 *   staff_logs     FIRST occurrence per window, plus the escalation. This is
 *                  the human-readable record, and it lands in the audit viewer
 *                  built in Task 8 for free. See the flood note below.
 *   notifyAdmin    Only on crossing a threshold, once per window. This is the
 *                  interruption, and interruptions have to stay rare or they
 *                  get ignored, which is worse than not sending them.
 *
 * THE FLOOD PROBLEM, and why staff_logs is rationed. This class runs on every
 * rejected request, so an attacker chooses how often it runs. Writing an audit
 * row per event would hand them a write amplifier: a few thousand 403s become a
 * few thousand INSERTs on a free-tier database. So a row is written on the first
 * event in a window and again when the threshold is crossed — bounded at two per
 * window per actor, no matter how hard the door is rattled. The log still gets
 * every one, because appending to stderr costs nothing.
 *
 * The counter uses the DEFAULT cache store, not `database`. That is the opposite
 * of the rule for locks (CLAUDE.md: locks guarding correctness are pinned to
 * `database`) and the reason is that the failure modes are not comparable. A
 * lock in the wrong store means two writers both win — corruption. A counter
 * lost to a Redis blip means one alert is late. Given the choice between a
 * missed alert and a guaranteed database write per hostile request, the missed
 * alert is cheaper. Note the rate limiters themselves already write to the
 * `database` store (`cache.limiter`), so this is not the only counter in play.
 *
 * NOTHING HERE MAY THROW. It is called from the middleware that wraps every
 * response — including the PayMongo webhook, which must never return a non-2xx
 * (see PaymentController::webhook). A monitor that can 500 the thing it watches
 * is worse than no monitor.
 */
final class SecurityMonitor
{
    /**
     * Event names. These double as `staff_logs.action` values, so they are also
     * what the audit viewer filters on — keep them stable.
     */
    public const AUTHZ_FAILED = 'authorization_failed';

    public const RATE_LIMITED = 'rate_limited';

    public const WEBHOOK_REJECTED = 'webhook_signature_rejected';

    public const CRON_SECRET_REJECTED = 'cron_secret_rejected';

    public const LOGIN_FAILED_BURST = 'login_failed_burst';

    public const CREDENTIAL_SPRAY = 'credential_spray';

    /**
     * The one event here that is not a security event.
     *
     * A 500 rides this machinery on purpose rather than growing a parallel
     * ErrorMonitor: it needs the identical three things — ration the audit rows,
     * alert once on a threshold, never throw while doing it — and two copies of
     * that logic would drift, leaving whichever copy was not updated silently
     * wrong. Thresholds also want tuning together rather than in two places.
     */
    public const APP_ERROR = 'application_error';

    /** How long a counting window lasts, for every event type. */
    public const WINDOW_MINUTES = 60;

    /**
     * Record one event.
     *
     * $bucket is what "the same actor doing the same thing again" means for this
     * event — usually an IP, sometimes an IP plus a route. It is the unit both
     * the audit-row rationing and the escalation threshold count against, so a
     * bucket that is too broad (a constant) lets one noisy actor suppress
     * everyone else's first-occurrence row.
     *
     * Returns how many times this event has been seen for this bucket in the
     * current window, so a caller that wants its own threshold can use it.
     */
    /**
     * Request attribute marking "this request already produced a specific
     * security event", so the generic 403 recorder does not report it twice.
     */
    public const HANDLED_ATTRIBUTE = 'villa.security_event_recorded';

    public static function record(
        string $event,
        string $bucket,
        string $summary,
        array $context = [],
        ?string $targetTable = null,
        ?int $targetId = null,
    ): int {
        try {
            self::markHandled();

            $count = self::bump($event, $bucket);

            Log::warning("Security: {$event} — {$summary}", $context + [
                'event' => $event,
                'bucket' => $bucket,
                'seen_in_window' => $count,
            ]);

            // First one in this window gets a durable, human-readable row. The
            // rest are in the log; see the flood note on the class.
            if ($count === 1) {
                StaffLog::record($event, $targetTable, $targetId, $summary);
            }

            return $count;
        } catch (\Throwable $e) {
            // Never let monitoring break the request it is monitoring.
            self::panic($event, $e);

            return 0;
        }
    }

    /**
     * Notify the admins when $count has just reached $threshold — once per
     * window, on the exact crossing, never again until the window rolls.
     *
     * The `=== $threshold` test is doing real work: `>=` would notify on every
     * event after the threshold, which is precisely the behaviour that trains
     * people to filter these out. This is the same shape as
     * Booking::flagOverpayment()'s `overpayment_notified_at` guard — alert once,
     * then stop talking.
     */
    public static function escalate(
        string $event,
        int $count,
        int $threshold,
        string $title,
        string $message,
        ?string $link = null,
    ): bool {
        if ($count !== $threshold) {
            return false;
        }

        try {
            StaffLog::record($event.'_escalated', null, null, $message);

            Log::error("Security: {$event} crossed its alert threshold", [
                'event' => $event,
                'count' => $count,
                'threshold' => $threshold,
                'window_minutes' => self::WINDOW_MINUTES,
            ]);

            NotificationHelper::notifyAdmin($title, $message, $link);

            return true;
        } catch (\Throwable $e) {
            self::panic($event, $e);

            return false;
        }
    }

    /**
     * record() + escalate() together, for the common case.
     */
    public static function recordAndEscalate(
        string $event,
        string $bucket,
        string $summary,
        int $threshold,
        string $title,
        string $message,
        array $context = [],
        ?string $link = null,
    ): int {
        $count = self::record($event, $bucket, $summary, $context);

        self::escalate($event, $count, $threshold, $title, $message, $link);

        return $count;
    }

    /**
     * Count DISTINCT things one actor has touched in the window — how many
     * different accounts one IP has failed to log into, for instance.
     *
     * This is the question a plain counter cannot answer, and it is the gap
     * Task 12 F5 named: login is locked per ACCOUNT on failed attempts, and
     * limited to 30/min per IP, so one guess tried against a hundred accounts
     * from one address trips neither guard. There is no cache "set", so
     * membership is one key per pair and the tally only advances when the pair
     * is new.
     */
    public static function countDistinct(string $event, string $bucket, string $item): int
    {
        try {
            $seen = self::key($event, $bucket, 'seen:'.sha1($item));

            if (Cache::has($seen)) {
                return (int) Cache::get(self::key($event, $bucket, 'distinct'), 0);
            }

            Cache::put($seen, 1, now()->addMinutes(self::WINDOW_MINUTES));

            return self::bump($event, $bucket, 'distinct');
        } catch (\Throwable $e) {
            self::panic($event, $e);

            return 0;
        }
    }

    /**
     * Flag the current request as already accounted for.
     *
     * Without this, a wrong CRON_SECRET produced TWO records for one incident:
     * the route's own `cron_secret_rejected` and then the generic
     * `authorization_failed` from RecordSecurityResponses seeing the 403 come
     * back. Two audit rows, two warnings, and two thresholds counting the same
     * event — which is how alerting becomes untrustworthy. Caught by an existing
     * test asserting the warning count, not by design.
     *
     * The flag lives on the REQUEST, not on this class. A static would leak
     * between requests in any long-running process, and would leak between tests
     * in the same PHP process, which is the same bug with a faster feedback loop.
     *
     * Specific beats generic on purpose: `cron_secret_rejected` says what was
     * being guessed, where `authorization_failed` only says something was refused.
     */
    public static function markHandled(): void
    {
        try {
            if (app()->bound('request')) {
                request()->attributes->set(self::HANDLED_ATTRIBUTE, true);
            }
        } catch (\Throwable) {
            // No request (console, queue). Nothing to suppress.
        }
    }

    public static function wasHandled(): bool
    {
        try {
            return app()->bound('request')
                && request()->attributes->getBoolean(self::HANDLED_ATTRIBUTE);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Increment a window counter and return the new value.
     *
     * `Cache::add()` first, then `increment()`: `increment()` on a missing key
     * is not portable across stores (the array and database stores return false
     * rather than creating it), and a TTL must be set when the key is created or
     * the window would never roll over.
     */
    private static function bump(string $event, string $bucket, string $suffix = 'count'): int
    {
        $key = self::key($event, $bucket, $suffix);

        if (Cache::add($key, 1, now()->addMinutes(self::WINDOW_MINUTES))) {
            return 1;
        }

        $value = Cache::increment($key);

        // A store that refuses to increment (or a key that expired between the
        // add and the increment) must not report "0 occurrences" — that would
        // read as "nothing happened".
        return is_int($value) && $value > 0 ? $value : 1;
    }

    private static function key(string $event, string $bucket, string $suffix): string
    {
        return 'secmon:'.$event.':'.sha1($bucket).':'.$suffix;
    }

    /**
     * The monitor itself failed. Say so at CRITICAL and swallow it — the same
     * choice StaffLog::record() makes, and for the same reason: the business
     * action must not be destroyed by the recording of it.
     */
    private static function panic(string $event, \Throwable $e): void
    {
        try {
            Log::critical('SECURITY MONITOR FAILED — this event was not recorded.', [
                'event' => $event,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            // Logging is what broke. There is nowhere left to say so.
        }
    }
}
