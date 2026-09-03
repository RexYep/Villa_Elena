<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Booking;
use App\Models\Recommendation;
use App\Services\Prescriptive\DemandModel;
use Carbon\Carbon;

/**
 * "Malakas ang mga petsang ito — kulang ba ang sinisingil natin?"
 *
 * Ang kabaligtaran ng IdleDatePromoAdvisor, at kailangan niyang umiral.
 * Ang isang engine na diskuwento lang ang alam ay hindi umoopetimisa ng
 * kita — namimigay lang ito. Parehong anyo ng optimisasyon, taas naman
 * ang direksyon:
 *
 *     E(u) = p(u) x presyo x (1 + u)
 *     p(u) = p x (1 - peak_elasticity x u)
 *
 * DALAWANG ELASTICITY, AT IYON AY SADYA. Ang bumibili ng tahimik na
 * Martes ay naghahanap ng mura — malamang na elastic (`prescriptive_elasticity`,
 * default 1.5). Ang bumubuo ng reunion sa Sabado ng Mahal na Araw ay may
 * petsang hindi mababago — malamang na INELASTIC (`prescriptive_peak_elasticity`,
 * default 0.6). Iisang numero para sa dalawa ay siguradong mali sa isa.
 *
 * Ang matematika ay nagbibigay ng parehong klasikong hangganan sa
 * kabilang panig: ang pagtataas ng presyo ay nagbabayad lang kapag
 * INELASTIC ang demand (e < 1), at ang optimum ay
 *
 *     u* = (1 - e) / (2e)
 *
 * Sa e = 0.6, iyon ay ~33%, na hinihigpitan ng `prescriptive_max_increase`.
 * Sa e >= 1, walang imumungkahi kailanman — tama iyon.
 *
 * ⚠️ ANG BITAG: `type = 'fixed'` ANG GINAGAMIT, HINDI `'percentage'`.
 *
 * Ang isang `pricing_rules` na `percentage` ay kinukuwenta ng
 * `Property::getPackagePrice()` bilang `base_price x (1 + price/100)` —
 * laging laban sa BASE, hindi sa `weekend_price`. Ang mga peak na petsa ay
 * halos palaging weekend, kaya ang isang "+15%" na rule sa isang Sabado ay
 * magbibigay ng 4,000 x 1.15 = 4,600 — mas MABABA pa sa 6,000 na
 * kasalukuyang sinisingil. Ang mungkahing magtaas ng presyo ay tahimik
 * na magpapamura. Kaya ganap na halaga ang ipinapadala rito, at ang
 * magkakasunod na petsa ay pinagsasama LANG kapag pareho ang
 * kasalukuyang presyo nila.
 */
class PeakRateAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_PEAK_RATE;
    }

    public function generate(DemandModel $demand): array
    {
        $elasticity = DemandModel::setting('prescriptive_peak_elasticity', 0.6);
        $peakCutoff = DemandModel::setting('prescriptive_peak_threshold', 60) / 100;
        $maxIncrease = DemandModel::setting('prescriptive_max_increase', 20);
        $minImpact = DemandModel::setting('prescriptive_min_impact', 500);
        $candidates = (array) config('prescriptive.increase_candidates', [0, 5, 10, 15, 20]);

        // Walang halaga ang pagtataas ng presyo kapag elastic ang demand —
        // at ang paglabas dito nang maaga ay nagpapaliwanag ng katahimikan
        // ng advisor nang hindi kailangang basahin ang buong loop.
        if ($elasticity >= 1) {
            return [];
        }

        $start = Carbon::today()->addDays((int) config('prescriptive.min_lead_days', 2));
        $end = Carbon::today()->addDays($demand->lookaheadDays());

        $out = [];

        foreach (array_keys(Booking::SLOTS) as $slot) {
            $perDate = [];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $best = $this->bestIncreaseFor(
                    $demand, $date, $slot,
                    $elasticity, $peakCutoff, $maxIncrease, $candidates
                );

                if ($best !== null) {
                    $perDate[$date->format('Y-m-d')] = $best;
                }
            }

            foreach ($this->groupIntoRuns($perDate) as $run) {
                $rec = $this->buildRecommendation($run, $slot, $elasticity, $minImpact, $demand->lookbackDays());

                if ($rec !== null) {
                    $out[] = $rec;
                }
            }
        }

        return $out;
    }

    /**
     * @return array{increase: float, gain: float, p: float, p_new: float, price: float, sample: int, booked: int, raw_rate: float, dow: int}|null
     */
    private function bestIncreaseFor(
        DemandModel $demand,
        Carbon $date,
        string $slot,
        float $elasticity,
        float $peakCutoff,
        float $maxIncrease,
        array $candidates
    ): ?array {
        if ($demand->isOccupied($date, $slot)) {
            return null;
        }
        if ($demand->isBlocked($date)) {
            return null;
        }

        // May itinakda nang presyo ang may-ari para sa araw na ito, o may
        // tumatakbong promo — alinman sa dalawa ay sadyang desisyon na
        // hindi dapat pangunahan.
        if ($demand->hasPricingRule($date)) {
            return null;
        }
        if ($demand->hasPromo($date, $slot)) {
            return null;
        }

        $fill = $demand->fillRate($date->dayOfWeek, $slot);

        if ($fill['sample'] < 1) {
            return null;
        }

        $p = $fill['rate'];

        // Hindi ito peak — walang batayan para magtaas.
        if ($p < $peakCutoff) {
            return null;
        }

        $price = $demand->price($date, $slot);
        $baseline = $p * $price;

        $bestIncrease = 0.0;
        $bestValue = $baseline;
        $bestP = $p;

        foreach ($candidates as $u) {
            $u = (float) $u;

            if ($u <= 0 || $u > $maxIncrease) {
                continue;
            }

            // Hindi puwedeng maging negatibo ang posibilidad kahit gaano
            // kalaki ang taas.
            $pNew = max(0.0, $p * (1 - ($elasticity * $u) / 100));
            $value = $pNew * $price * (1 + $u / 100);

            if ($value > $bestValue) {
                $bestValue = $value;
                $bestIncrease = $u;
                $bestP = $pNew;
            }
        }

        if ($bestIncrease <= 0) {
            return null;
        }

        return [
            'increase' => $bestIncrease,
            'gain' => round($bestValue - $baseline, 2),
            'p' => $p,
            'p_new' => $bestP,
            'price' => $price,
            'sample' => $fill['sample'],
            'booked' => $fill['booked'],
            'raw_rate' => $fill['raw_rate'],
            'dow' => $date->dayOfWeek,
        ];
    }

    /**
     * Pinagsasama ang magkakasunod na petsa — pero PAREHONG KASALUKUYANG
     * PRESYO lang, hindi tulad ng promo advisor.
     *
     * Ang ipinapadala ay ganap na halaga (tingnan ang bitag sa itaas), at
     * ang isang `pricing_rules` row ay iisang presyo para sa buong saklaw
     * nito. Ang pagsama ng Biyernes (6,000) at Lunes (4,000) sa isang rule
     * ay magpapapantay sa kanila — tataas ang Lunes, at BABABA ang
     * Biyernes.
     *
     * @return array<int, array<string, array>>
     */
    private function groupIntoRuns(array $perDate): array
    {
        if (empty($perDate)) {
            return [];
        }

        ksort($perDate);

        $maxDays = max(1, (int) config('prescriptive.max_promo_run_days', 14));

        $runs = [];
        $current = [];
        $prev = null;

        foreach ($perDate as $date => $info) {
            $carbon = Carbon::parse($date);

            $continues = $prev !== null
                && $carbon->copy()->subDay()->isSameDay($prev['date'])
                && $info['increase'] === $prev['increase']
                && abs($info['price'] - $prev['price']) < 0.01
                && count($current) < $maxDays;

            if (! $continues && ! empty($current)) {
                $runs[] = $current;
                $current = [];
            }

            $current[$date] = $info;
            $prev = ['date' => $carbon, 'increase' => $info['increase'], 'price' => $info['price']];
        }

        if (! empty($current)) {
            $runs[] = $current;
        }

        return $runs;
    }

    private function buildRecommendation(
        array $run,
        string $slot,
        float $elasticity,
        float $minImpact,
        int $lookbackDays
    ): ?array {
        $dates = array_keys($run);
        $first = Carbon::parse($dates[0]);
        $last = Carbon::parse(end($dates));
        $increase = (float) $run[$dates[0]]['increase'];

        $gain = round(array_sum(array_column($run, 'gain')), 2);

        if ($gain < $minImpact) {
            return null;
        }

        $price = (float) $run[$dates[0]]['price'];   // pareho sa buong run — tingnan ang groupIntoRuns()
        $newPrice = round($price * (1 + $increase / 100), 2);
        $avgPNew = array_sum(array_column($run, 'p_new')) / count($run);

        $byDow = [];
        foreach ($run as $info) {
            $byDow[$info['dow']] = ['sample' => $info['sample'], 'booked' => $info['booked']];
        }

        $totalSample = array_sum(array_column($byDow, 'sample'));
        $totalBooked = array_sum(array_column($byDow, 'booked'));
        $rawFill = $totalSample > 0 ? $totalBooked / $totalSample : 0.0;

        $slotLabel = $slot === 'day' ? 'Day' : 'Night';
        $window = $first->isSameDay($last)
            ? $first->format('M j')
            : $first->format('M j').'–'.$last->format('M j');

        $elasticityLabel = rtrim(rtrim(number_format($elasticity, 2), '0'), '.');

        $evidence = [
            sprintf(
                '%s slot on %s filled %d of %d comparable days in the last %d days (%d%%) — among the strongest on the calendar.',
                $slotLabel,
                $this->dayNames($run),
                $totalBooked,
                $totalSample,
                $lookbackDays,
                round($rawFill * 100)
            ),
            sprintf(
                '%d date%s in this window %s still open at PHP %s.',
                count($run),
                count($run) === 1 ? '' : 's',
                count($run) === 1 ? 'is' : 'are',
                number_format($price, 0)
            ),
            sprintf(
                'At PHP %s (+%d%%), the fill rate is projected to ease to %d%% — fewer bookings, but more revenue per booking.',
                number_format($newPrice, 0),
                $increase,
                round($avgPNew * 100)
            ),
            sprintf(
                'Net projected gain PHP %s across the window.',
                number_format($gain, 0)
            ),
            sprintf(
                'Assumption: peak-date price elasticity = %s — demand here is treated as INELASTIC, so a %d%% rise costs only %s%% of demand. At 1.0 or above, no increase would ever be recommended.',
                $elasticityLabel,
                $increase,
                rtrim(rtrim(number_format($elasticity * $increase, 1), '0'), '.')
            ),
        ];

        return [
            'type' => Recommendation::TYPE_PEAK_RATE,
            'title' => sprintf('Raise the %s rate %d%% for %s', strtolower($slotLabel), $increase, $window),
            'summary' => sprintf(
                '%d %s-slot date%s in %s %s filling well above the peak threshold. Holding them at PHP %s leaves money on the table; PHP %s is projected to be worth about PHP %s more.',
                count($run),
                strtolower($slotLabel),
                count($run) === 1 ? '' : 's',
                $window,
                count($run) === 1 ? 'is' : 'are',
                number_format($price, 0),
                number_format($newPrice, 0),
                number_format($gain, 0)
            ),
            'evidence' => $evidence,
            'target_start' => $first->toDateString(),
            'target_end' => $last->toDateString(),
            'slot' => $slot,
            'action_type' => Recommendation::ACTION_CREATE_PRICING_RULE,
            'action_payload' => [
                'label' => sprintf('Peak Rate %s (+%d%%)', $window, $increase),
                // GANAP na halaga, hindi porsyento — tingnan ang bitag sa
                // docblock ng klase. Huwag itong gawing 'percentage'.
                'type' => 'fixed',
                'price' => $newPrice,
                'start_date' => $first->toDateString(),
                'end_date' => $last->toDateString(),
                'is_active' => 1,
                'current_price' => $price,
                'increase_pct' => $increase,
            ],
            'expected_impact' => $gain,
            'baseline_projection' => round(array_sum(array_map(
                fn ($info) => $info['p'] * $info['price'],
                $run
            )), 2),
            'sample_size' => $totalSample,
            'confidence' => DemandModel::confidence((int) min(array_column($byDow, 'sample'))),
            'fingerprint' => $this->fingerprint([
                Recommendation::TYPE_PEAK_RATE,
                $slot,
                $first->toDateString(),
                $last->toDateString(),
                $increase,
            ]),
        ];
    }

    private function dayNames(array $run): string
    {
        $names = [];

        foreach (array_keys($run) as $date) {
            $names[Carbon::parse($date)->dayOfWeek] = Carbon::parse($date)->format('l').'s';
        }

        ksort($names);
        $names = array_values($names);

        if (count($names) === 7) {
            return 'every day of the week';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        $lastName = array_pop($names);

        return implode(', ', $names).' and '.$lastName;
    }
}
