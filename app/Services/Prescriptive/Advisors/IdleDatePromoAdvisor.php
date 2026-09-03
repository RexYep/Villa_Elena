<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Booking;
use App\Models\Recommendation;
use App\Services\Prescriptive\DemandModel;
use Carbon\Carbon;

/**
 * "Mahina ang mga petsang ito — magkano ang diskuwentong sulit?"
 *
 * DITO NAKASALALAY ANG PAGKA-PRESCRIPTIVE NG BUONG FEATURE. Hindi ito
 * basta nagsasabing "mababa ang bookings tuwing Martes" (iyon ay
 * descriptive) ni humuhula lang na "mananatiling mababa" (predictive).
 * Sinusubukan nito ang bawat kandidatong diskuwento at pinipili ang
 * NAGPAPALAKI sa inaasahang kita:
 *
 *     E(d) = p(d) x presyo x (1 - d)
 *     p(d) = p x (1 + elasticity x d)     [may sapin na maximum]
 *
 * ANG `elasticity` DITO AY ANG KLASIKONG PRICE ELASTICITY OF DEMAND:
 * kung 1.5, ang 1% na pagbaba ng presyo ay ipinapalagay na nagdaragdag ng
 * 1.5% na demand. Sinasadyang MULTIPLICATIVE ito at hindi additive.
 *
 * Ang unang bersyon nito ay additive (`p + e x d`), at ang bunga niyon ay
 * ang PINAKAMALAKING diskuwento ay laging panalo — 20% sa bawat petsa.
 * Iyon ang tanda na sira ang modelo, hindi na matipid ang villa: sa
 * additive na anyo, ang isang petsang 8% ang fill ay itinuturing na
 * tataas nang parehong 15 puntos gaya ng petsang 30% ang fill, na
 * imposible. Sa multiplicative na anyo, ang mahinang petsa ay tumataas
 * nang KAUNTI lang, at ang optimum ay nasa loob:
 *
 *     d* = 50 x (elasticity - 1) / elasticity
 *
 * Kaya sa 1.5, ang sagot ay ~17% (15% sa mga kandidato); sa 1.2, ~8%; at
 * sa 1.0 pababa, WALANG diskuwentong sulit — ang klasikong resulta na ang
 * pagbaba ng presyo ay nagbabayad lang kapag elastic ang demand. Iyon ang
 * tamang asal, at nasusuri ng panel.
 *
 * ANG ELASTICITY AY PAGPAPALAGAY PA RIN, HINDI SUKAT. Wala pang sapat na
 * naunang promo ang Villa Elena para masukat ito nang totoo, kaya
 * nakalantad ito sa Settings (`prescriptive_elasticity`) at binabanggit
 * nang tahasan sa evidence ng bawat card. Huwag itong itago sa loob ng
 * code — ang unang itatanong tungkol sa numerong ito ay "saan galing?".
 */
class IdleDatePromoAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_IDLE_PROMO;
    }

    public function generate(DemandModel $demand): array
    {
        $elasticity = DemandModel::setting('prescriptive_elasticity', 1.5);
        $idleCutoff = DemandModel::setting('prescriptive_idle_threshold', 35) / 100;
        $maxDiscount = DemandModel::setting('prescriptive_max_discount', 20);
        $minImpact = DemandModel::setting('prescriptive_min_impact', 500);
        $ceiling = (float) config('prescriptive.probability_ceiling', 0.90);
        $candidates = (array) config('prescriptive.discount_candidates', [0, 5, 10, 15, 20]);

        $start = Carbon::today()->addDays((int) config('prescriptive.min_lead_days', 2));
        $end = Carbon::today()->addDays($demand->lookaheadDays());

        $out = [];

        foreach (array_keys(Booking::SLOTS) as $slot) {
            $perDate = [];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $best = $this->bestDiscountFor(
                    $demand, $date, $slot,
                    $elasticity, $idleCutoff, $maxDiscount, $ceiling, $candidates
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
     * Ang optimizer mismo — isang petsa, isang slot.
     *
     * @return array{discount: float, gain: float, p: float, p_new: float, price: float, sample: int, dow: int}|null
     */
    private function bestDiscountFor(
        DemandModel $demand,
        Carbon $date,
        string $slot,
        float $elasticity,
        float $idleCutoff,
        float $maxDiscount,
        float $ceiling,
        array $candidates
    ): ?array {
        // Nabenta na, hindi naman inaalok, o may promo nang tumatama —
        // walang dapat pag-usapan.
        if ($demand->isOccupied($date, $slot)) {
            return null;
        }
        if ($demand->isBlocked($date)) {
            return null;
        }
        if ($demand->hasPromo($date, $slot)) {
            return null;
        }

        // Hindi binabawasan ang presyo sa mga pista. Ang mga araw na iyon
        // ang pinakamalakas sa taon; ang diskuwento roon ay bigay-lang.
        if ($demand->holidayName($date) !== null) {
            return null;
        }

        $fill = $demand->fillRate($date->dayOfWeek, $slot);

        // Walang naitalang katulad na araw — walang batayan, kaya walang
        // mungkahi. Mas mabuting tahimik kaysa gumawa ng numero.
        if ($fill['sample'] < 1) {
            return null;
        }

        $p = $fill['rate'];

        // Malakas na naman ang araw na ito — hindi ito ang problema.
        if ($p >= $idleCutoff) {
            return null;
        }

        $price = $demand->price($date, $slot);
        $baseline = $p * $price;

        $bestDiscount = 0.0;
        $bestValue = $baseline;
        $bestP = $p;

        foreach ($candidates as $d) {
            $d = (float) $d;

            if ($d <= 0 || $d > $maxDiscount) {
                continue;
            }

            $pNew = min($ceiling, $p * (1 + ($elasticity * $d) / 100));
            $value = $pNew * $price * (1 - $d / 100);

            if ($value > $bestValue) {
                $bestValue = $value;
                $bestDiscount = $d;
                $bestP = $pNew;
            }
        }

        if ($bestDiscount <= 0) {
            return null;
        }

        return [
            'discount' => $bestDiscount,
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
     * Pinagsasama ang magkakasunod na petsang may PAREHONG diskuwento sa
     * isang mungkahi.
     *
     * Kung wala ito, ang isang tahimik na Setyembre ay magiging 30 hiwalay
     * na card at 30 hiwalay na `Discount` row — at ang page na iyon ay
     * hindi na babasahin ninuman.
     *
     * May HANGGANAN naman ang haba (`max_promo_run_days`). Kapag mahina
     * ang buong buwan, isang tuluy-tuloy na takbo ang lalabas — at ang
     * "20% off araw-araw sa loob ng 32 araw" ay isang malaking desisyon sa
     * negosyo na ipinapasa bilang isang pindot. Sa paghahati nito, ang
     * admin ay makakapagpasya nang paunti-unti, at mas maliit ang
     * babawiin kapag lumabas na masyadong maluwag ang pagpapalagay sa
     * elasticity.
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
                && $info['discount'] === $prev['discount']
                && count($current) < $maxDays;

            if (! $continues && ! empty($current)) {
                $runs[] = $current;
                $current = [];
            }

            $current[$date] = $info;
            $prev = ['date' => $carbon, 'discount' => $info['discount']];
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
        $discount = (float) $run[$dates[0]]['discount'];

        $gain = round(array_sum(array_column($run, 'gain')), 2);

        // Ang isang mungkahing nagkakahalaga ng piso ay hindi sulit sa
        // atensyon ng may-ari. Nasa Settings ang hangganan.
        if ($gain < $minImpact) {
            return null;
        }

        $avgPNew = array_sum(array_column($run, 'p_new')) / count($run);
        $avgPrice = array_sum(array_column($run, 'price')) / count($run);

        // Bawat araw-ng-linggo ay iisang obserbasyon lang kahit ilang beses
        // itong lumitaw sa window. Ang tatlong Martes sa isang run ay
        // nagbabahagi ng IISANG fill-rate na sample; kung isasama silang
        // tatlo, tatlong beses lalaki ang mukhang batayan ng mungkahi
        // gayong ni isang bagong obserbasyon ay walang naidagdag.
        $byDow = [];
        foreach ($run as $info) {
            $byDow[$info['dow']] = ['sample' => $info['sample'], 'booked' => $info['booked']];
        }

        // Ang ipinapakitang porsyento ay ang HILAW na naganap, hindi ang
        // pinakinis na p na ginamit sa matematika — "0 of 26" na sinundan
        // ng "(2%)" ay mukhang mali sa mata ng nagbabasa.
        $totalSample = array_sum(array_column($byDow, 'sample'));
        $totalBooked = array_sum(array_column($byDow, 'booked'));

        $rawFill = $totalSample > 0 ? $totalBooked / $totalSample : 0.0;

        $slotLabel = $slot === 'day' ? 'Day' : 'Night';
        $window = $first->isSameDay($last)
            ? $first->format('M j')
            : $first->format('M j').'–'.$last->format('M j');

        $newPrice = round($avgPrice * (1 - $discount / 100), 2);
        $dayNames = $this->dayNames($run);
        $elasticityLabel = rtrim(rtrim(number_format($elasticity, 2), '0'), '.');

        $evidence = [
            sprintf(
                '%s slot on %s filled %d of %d comparable days in the last %d days (%d%%).',
                $slotLabel,
                $dayNames,
                $totalBooked,
                $totalSample,
                $lookbackDays,
                round($rawFill * 100)
            ),
            sprintf(
                '%d date%s in this window %s still open, at an average rate of PHP %s.',
                count($run),
                count($run) === 1 ? '' : 's',
                count($run) === 1 ? 'is' : 'are',
                number_format($avgPrice, 0)
            ),
            sprintf(
                'A %d%% promo prices them at PHP %s and is projected to lift the fill rate to %d%%.',
                $discount,
                number_format($newPrice, 0),
                round($avgPNew * 100)
            ),
            sprintf(
                'Net projected gain PHP %s — that is after giving up PHP %s per booking.',
                number_format($gain, 0),
                number_format($avgPrice - $newPrice, 0)
            ),
            sprintf(
                'Assumption: price elasticity of demand = %s — a 1%% price cut is taken to raise booking probability by %s%%. At 1.0 or below, no discount would ever be recommended. Adjustable under Settings → Prescriptive Engine.',
                $elasticityLabel,
                $elasticityLabel
            ),
        ];

        return [
            'type' => Recommendation::TYPE_IDLE_PROMO,
            'title' => sprintf('Run a %d%% %s promo for %s', $discount, strtolower($slotLabel), $window),
            'summary' => sprintf(
                '%d %s-slot date%s in %s %s tracking below the idle threshold. A %d%% promo is the best of the discounts tested, worth about PHP %s more than leaving the rate alone.',
                count($run),
                strtolower($slotLabel),
                count($run) === 1 ? '' : 's',
                $window,
                count($run) === 1 ? 'is' : 'are',
                $discount,
                number_format($gain, 0)
            ),
            'evidence' => $evidence,
            'target_start' => $first->toDateString(),
            'target_end' => $last->toDateString(),
            'slot' => $slot,
            'action_type' => Recommendation::ACTION_CREATE_PROMO,
            'action_payload' => [
                'label' => sprintf('%d%% %s Promo (%s)', $discount, $slotLabel, $window),
                'description' => sprintf('Automatic %d%% off the %s-slot rate.', $discount, strtolower($slotLabel)),
                'type' => 'percentage',
                'value' => $discount,
                'start_date' => $first->toDateString(),
                'expiry_date' => $last->toDateString(),
                'applies_to' => $slot,
                'is_public' => 1,
                'is_active' => 1,
            ],
            'expected_impact' => $gain,
            // Ang antas, hindi lang ang dagdag — ito ang inaasahang kita
            // kung WALANG gagawin. Naka-freeze dito para may
            // maihahambing ang Phase 4 sa aktuwal na nangyari, sa halip
            // na kuwentahin ulit matapos malaman ang sagot.
            'baseline_projection' => round(array_sum(array_map(
                fn ($info) => $info['p'] * $info['price'],
                $run
            )), 2),
            'sample_size' => $totalSample,
            // Ang pinakamahinang araw-ng-linggo ang nagtatakda ng
            // confidence, hindi ang kabuuan: ang isang mungkahing sakop
            // ang Martes (12 obserbasyon) at Miyerkules (2) ay kasing-
            // hina ng Miyerkules, at hindi dapat magmukhang matibay dahil
            // lang marami ang natipong bilang.
            'confidence' => DemandModel::confidence((int) min(array_column($byDow, 'sample'))),
            'fingerprint' => $this->fingerprint([
                Recommendation::TYPE_IDLE_PROMO,
                $slot,
                $first->toDateString(),
                $last->toDateString(),
                $discount,
            ]),
        ];
    }

    /** "Tuesdays and Wednesdays" — mas nababasa kaysa listahan ng petsa. */
    private function dayNames(array $run): string
    {
        $names = [];

        foreach (array_keys($run) as $date) {
            $names[Carbon::parse($date)->dayOfWeek] = Carbon::parse($date)->format('l').'s';
        }

        ksort($names);
        $names = array_values($names);

        // Ang "Sundays, Mondays, Tuesdays, Wednesdays, Thursdays, Fridays
        // and Saturdays" ay mahabang paraan ng pagsasabing mahina ang
        // buong linggo.
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
