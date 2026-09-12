<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Holds the generated text of the AI report pages so that visiting one is a
 * read, not a Groq call.
 *
 * Before this, `Admin\InsightsController::index()` and
 * `Admin\ForecastController::index()` called `GeminiService::ask()` inline.
 * Every navigation into the page — sidebar click, back button, a second admin
 * opening the same tab — spent a request. Measured at three calls for three
 * visits on each page. The "Refresh" button was an `<a href>` pointing at the
 * same GET route, so it was indistinguishable from ordinary navigation and
 * could not have helped.
 *
 * `Admin\PrescriptiveController` already worked the right way — it reads what
 * `prescriptive:generate` last wrote and only calls the AI from its POST
 * refresh — and its own comment names Insights and Forecast as the two pages
 * still getting this wrong. This class is that same arrangement, shared.
 *
 * Storage notes, both deliberate:
 *
 * - **`Cache::store('database')`, not the default store.** Production runs
 *   `CACHE_STORE=failover` (`['redis', 'database']`), and its Redis is the free
 *   Render Key Value plan, which does not persist. On the default store a Redis
 *   restart or an `allkeys-lru` eviction silently drops the report, and the next
 *   admin to open the page pays for a fresh generation — quietly reintroducing
 *   the behaviour this class exists to remove. MySQL keeps it.
 * - **Not the `settings` table**, which is where `Prescriptive\BriefingWriter`
 *   puts its (short) briefing. `Setting::get()` loads every setting as one blob
 *   on every public page via `CheckMaintenanceMode`. That blob is 762 bytes
 *   across 39 rows today; a forecast report is several KB of Markdown, and
 *   every guest hitting the landing page would carry it.
 *
 * Stored forever, with no TTL, on purpose: a TTL is a timer that spends money
 * on nobody's behalf. The report changes when the admin asks for it to change.
 */
class AiReportStore
{
    public const INSIGHTS = 'insights';

    public const FORECAST = 'forecast';

    /**
     * Returns the stored report, generating and storing it once if there is
     * none yet. The generator is expected to return NULL when the AI call
     * fails (`GeminiService::ask()`'s contract) — a failure is never stored,
     * so it doesn't become a permanent empty page that only a Refresh click
     * could clear.
     *
     * @param  callable(): ?string  $generate
     * @return array{text: string, generated_at: Carbon}|null
     */
    public function remember(string $report, callable $generate): ?array
    {
        return $this->read($report) ?? $this->refresh($report, $generate);
    }

    /**
     * Generates unconditionally and replaces what is stored. This is the only
     * thing the Refresh buttons call.
     *
     * A failed generation leaves the previous report in place rather than
     * blanking the page: stale data the admin can see the age of beats no data
     * at all, and the view says when it was made.
     *
     * Returns NULL for "not regenerated", so the caller can say so without
     * comparing timestamps to guess. Those are second-resolution, and two
     * generations inside one second would compare equal and read as a failure.
     *
     * @param  callable(): ?string  $generate
     * @return array{text: string, generated_at: Carbon}|null
     */
    public function refresh(string $report, callable $generate): ?array
    {
        $text = $generate();

        if ($text === null || trim($text) === '') {
            return null;
        }

        $entry = [
            'text' => trim($text),
            'generated_at' => Carbon::now()->toDateTimeString(),
        ];

        $this->repo()->forever($this->key($report), $entry);

        return $this->hydrate($entry);
    }

    /**
     * @return array{text: string, generated_at: Carbon}|null
     */
    public function read(string $report): ?array
    {
        $entry = $this->repo()->get($this->key($report));

        return is_array($entry) && isset($entry['text']) ? $this->hydrate($entry) : null;
    }

    public function forget(string $report): void
    {
        $this->repo()->forget($this->key($report));
    }

    /**
     * @param  array{text: string, generated_at?: string}  $entry
     * @return array{text: string, generated_at: Carbon}
     */
    private function hydrate(array $entry): array
    {
        return [
            'text' => $entry['text'],
            // A pre-existing entry without a timestamp would otherwise blow up
            // in the view's diffForHumans(); treat it as "as old as we know".
            'generated_at' => isset($entry['generated_at'])
                ? Carbon::parse($entry['generated_at'])
                : Carbon::now(),
        ];
    }

    private function key(string $report): string
    {
        return "ai_report:{$report}";
    }

    private function repo(): Repository
    {
        return Cache::store('database');
    }
}
