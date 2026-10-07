<?php

namespace App\Services\Prescriptive;

use App\Models\Recommendation;
use App\Services\Prescriptive\Advisors\Advisor;
use App\Services\Prescriptive\Advisors\BalanceReminderAdvisor;
use App\Services\Prescriptive\Advisors\MaintenanceWindowAdvisor;
use App\Services\Prescriptive\Advisors\PeakRateAdvisor;
use App\Services\Prescriptive\Advisors\QuietDatesPromoAdvisor;
use App\Services\Prescriptive\Advisors\TurnoverCleanAdvisor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Runs every decision rule and files the results.
 *
 * THE MOST IMPORTANT THING ABOUT THIS CLASS: the only rows it writes are in
 * `recommendations`. No promo, no pricing rule, no block, no task, no
 * notification. It can run every day and no price moves and no guest sees
 * anything. Acting on a card happens in `Admin\PrescriptiveController::apply()`
 * — and only there, when a person presses the button.
 *
 * Three rules for keeping the list:
 *
 * 1. DEDUPE. The same suggestion has the same fingerprint, so the daily run
 *    updates one card instead of adding another every morning.
 * 2. A DECISION IS NOT REOPENED. A card the admin applied or dismissed never
 *    goes back to `new` — "no" is an answer, not an invitation to ask again
 *    tomorrow.
 * 3. IT ADMITS WHEN A CARD IS OVER. An open card that a rule no longer
 *    produces — the dates got booked, the guest paid, the date passed — is
 *    marked `expired` rather than left offering an action that no longer
 *    makes sense.
 */
class PrescriptiveEngine
{
    private OccupancyCalendar $calendar;

    public function __construct(?OccupancyCalendar $calendar = null)
    {
        $this->calendar = $calendar ?? new OccupancyCalendar;
    }

    /** @return array<int, Advisor> */
    public function advisors(): array
    {
        return [
            new QuietDatesPromoAdvisor,
            new PeakRateAdvisor,
            new MaintenanceWindowAdvisor,
            new TurnoverCleanAdvisor,
            new BalanceReminderAdvisor,
        ];
    }

    /**
     * @return array{created: int, refreshed: int, skipped: int, expired: int}
     */
    public function run(): array
    {
        $stats = ['created' => 0, 'refreshed' => 0, 'skipped' => 0, 'expired' => 0];

        $produced = [];
        $succeeded = [];
        $now = Carbon::now();

        foreach ($this->advisors() as $advisor) {
            // One rule failing must not stop the others.
            try {
                $batch = $advisor->generate($this->calendar);
            } catch (\Throwable $e) {
                Log::error('Recommendation rule failed: '.$advisor::class.' — '.$e->getMessage());

                continue;
            }

            // It finished, so it has earned the right to expire its own cards.
            $succeeded[] = $advisor->type();

            foreach ($batch as $attrs) {
                $produced[] = $attrs['fingerprint'];

                $existing = Recommendation::where('fingerprint', $attrs['fingerprint'])->first();

                if (! $existing) {
                    Recommendation::create($attrs + [
                        'status' => 'new',
                        'generated_at' => $now,
                    ]);
                    $stats['created']++;

                    continue;
                }

                // `applied` and `dismissed` are a PERSON'S decision and are
                // never touched.
                if (in_array($existing->status, ['applied', 'dismissed'], true)) {
                    $stats['skipped']++;

                    continue;
                }

                // `expired` is not a person's decision — it only records that
                // the rule did not produce the card last time. If it is
                // produced again (a booking was cancelled and the dates are
                // quiet again), the opportunity is real again.
                $revived = $existing->status === 'expired';

                $existing->update($attrs + [
                    'status' => 'new',
                    'generated_at' => $now,
                    'dismissed_at' => null,
                    'dismiss_reason' => null,
                ]);

                $stats[$revived ? 'created' : 'refreshed']++;
            }
        }

        $stats['expired'] = $this->expireStale($produced, $succeeded);

        return $stats;
    }

    /**
     * Closes open cards that no rule produced this time.
     *
     * THE GUARD: ONLY `$succeededTypes` ARE COVERED. When Redis died in dev,
     * every rule failed inside `Setting::get()`, no fingerprint came back,
     * and an earlier version silently expired EVERY open card. A rule that
     * did not finish says nothing about whether its cards are still true, so
     * they stay until its next successful run.
     */
    private function expireStale(array $producedFingerprints, array $succeededTypes): int
    {
        if (empty($succeededTypes)) {
            return 0;
        }

        $query = Recommendation::open()->whereIn('type', $succeededTypes);

        if (! empty($producedFingerprints)) {
            $query->whereNotIn('fingerprint', $producedFingerprints);
        }

        return $query->update([
            'status' => 'expired',
            'updated_at' => Carbon::now(),
        ]);
    }
}
