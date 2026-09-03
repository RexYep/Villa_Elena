<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Booking;
use App\Models\Recommendation;
use App\Services\Prescriptive\DemandModel;
use Carbon\Carbon;

/**
 * "Kailan dapat isara ang villa para sa maintenance?"
 *
 * Isang tapat na MINIMIZATION, at ito ang pinakamalinis na halimbawa ng
 * prescriptive analytics sa sistema: kailangang isara ang villa nang
 * ilang araw — hindi iyon mapag-uusapan. Ang tanging tanong ay KAILAN,
 * at ang bawat sagot ay may presyo. Sinusukat ng advisor ang lahat ng
 * posibleng window sa horizon at pinipili ang PINAKAMURA:
 *
 *     halaga(window) = sum ng p(araw, slot) x presyo(araw, slot)
 *
 * Ang inuulat na epekto ay hindi ang halaga ng napiling window kundi ang
 * NAIWASAN: karaniwang halaga ng window - pinakamababa. Iyon ang tunay na
 * naiaambag ng pagpili, at hindi ito nagpapanggap na libre ang maintenance.
 *
 * Isang beses lang ito nagsasalita: kung may nakatakda nang maintenance
 * block sa loob ng horizon, wala itong imumungkahi. Hindi ito paalala,
 * mungkahi ito.
 */
class MaintenanceWindowAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_MAINTENANCE;
    }

    public function generate(DemandModel $demand): array
    {
        $days = max(1, (int) DemandModel::setting('prescriptive_maintenance_days', 2));
        $lead = (int) config('prescriptive.maintenance_lead_days', 7);
        $horizon = (int) config('prescriptive.maintenance_horizon_days', 60);

        $first = Carbon::today()->addDays($lead);
        $last = Carbon::today()->addDays($horizon);

        // May nakatakda na — hindi na kailangan ng mungkahi.
        for ($d = Carbon::today()->copy(); $d->lte($last); $d->addDay()) {
            if ($demand->blockReason($d) === 'maintenance') {
                return [];
            }
        }

        $windows = [];

        for ($start = $first->copy(); $start->copy()->addDays($days - 1)->lte($last); $start->addDay()) {
            $window = $this->evaluate($demand, $start, $days);

            if ($window !== null) {
                $windows[] = $window;
            }
        }

        // Isang kandidato lang ay walang pinipilian — walang maipagmamalaking
        // desisyon, at walang naiwasang halagang maiuulat nang tapat.
        if (count($windows) < 2) {
            return [];
        }

        usort($windows, fn ($a, $b) => $a['cost'] <=> $b['cost']);

        $best = $windows[0];
        $average = array_sum(array_column($windows, 'cost')) / count($windows);
        $worst = end($windows);
        $saved = round($average - $best['cost'], 2);

        // Pantay-pantay ang lahat ng window (karaniwan kapag manipis pa ang
        // datos) — walang totoong mapipili, kaya walang sasabihin.
        if ($saved <= 0) {
            return [];
        }

        $start = $best['start'];
        $end = $best['end'];

        $windowLabel = $days === 1
            ? $start->format('M j')
            : $start->format('M j').'–'.$end->format('M j');

        $evidence = [
            sprintf(
                'Checked %d possible %d-day windows between %s and %s; %s is the cheapest.',
                count($windows),
                $days,
                $first->format('M j'),
                $last->format('M j'),
                $windowLabel
            ),
            sprintf(
                'Projected revenue at risk in this window: PHP %s, against PHP %s for an average window and PHP %s for the worst.',
                number_format($best['cost'], 0),
                number_format($average, 0),
                number_format($worst['cost'], 0)
            ),
            sprintf(
                'Days covered: %s — historically the quieter end of the week.',
                $best['day_names']
            ),
            'No bookings, existing blocks, or Philippine holidays fall inside this window.',
            sprintf(
                'Closing here instead of an average window protects about PHP %s of expected revenue.',
                number_format($saved, 0)
            ),
        ];

        return [[
            'type' => Recommendation::TYPE_MAINTENANCE,
            'title' => sprintf('Schedule %d-day maintenance on %s', $days, $windowLabel),
            'summary' => sprintf(
                'Of the %d open %d-day windows in the next %d days, %s costs the least in expected revenue (PHP %s versus PHP %s for a typical window).',
                count($windows),
                $days,
                $horizon,
                $windowLabel,
                number_format($best['cost'], 0),
                number_format($average, 0)
            ),
            'evidence' => $evidence,
            'target_start' => $start->toDateString(),
            'target_end' => $end->toDateString(),
            'slot' => null,
            'action_type' => Recommendation::ACTION_CREATE_BLOCK,
            'action_payload' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'reason' => 'maintenance',
                'notes' => sprintf(
                    'Scheduled from a prescriptive recommendation — lowest projected revenue at risk (PHP %s) of %d candidate windows.',
                    number_format($best['cost'], 0),
                    count($windows)
                ),
            ],
            'expected_impact' => $saved,
            // Ang kitang isinusuko sa napiling window. Naitala para sa
            // display, PERO hindi ito kailanman ginagawang
            // `realized_impact` — tingnan ang OutcomeTracker: sadyang
            // sinasara ang window na ito, kaya laging zero ang aktuwal na
            // kita, at ang tunay na tanong (magkano sana kung ibang
            // window ang pinili) ay hindi kailanman masasagot.
            'baseline_projection' => $best['cost'],
            'sample_size' => $best['sample'],
            'confidence' => DemandModel::confidence($best['min_sample']),
            'fingerprint' => $this->fingerprint([
                Recommendation::TYPE_MAINTENANCE,
                $start->toDateString(),
                $end->toDateString(),
                $days,
            ]),
        ]];
    }

    /**
     * Halaga ng isang kandidatong window, o null kung hindi ito puwede.
     *
     * @return array{start: Carbon, end: Carbon, cost: float, sample: int, min_sample: int, day_names: string}|null
     */
    private function evaluate(DemandModel $demand, Carbon $start, int $days): ?array
    {
        $end = $start->copy()->addDays($days - 1);
        $cost = 0.0;

        $samples = [];
        $dayNames = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            // Hindi puwedeng isara ang villa sa ibabaw ng isang totoong
            // booking, ng ibang block, o ng pista.
            if ($demand->isBlocked($d)) {
                return null;
            }
            if ($demand->holidayName($d) !== null) {
                return null;
            }

            foreach (array_keys(Booking::SLOTS) as $slot) {
                if ($demand->isOccupied($d, $slot)) {
                    return null;
                }

                $cost += $demand->expectedRevenue($d, $slot);
                $samples[] = $demand->fillRate($d->dayOfWeek, $slot)['sample'];
            }

            $dayNames[$d->dayOfWeek] = $d->format('D');
        }

        ksort($dayNames);

        return [
            'start' => $start->copy(),
            'end' => $end,
            'cost' => round($cost, 2),
            'sample' => array_sum($samples),
            'min_sample' => $samples ? (int) min($samples) : 0,
            'day_names' => implode(', ', $dayNames),
        ];
    }
}
