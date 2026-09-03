<?php

namespace App\Services\Prescriptive;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Recommendation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 4 — binabalikan ang bawat mungkahi matapos ang window nito at
 * tinitingnan kung ano talaga ang nangyari.
 *
 * Ito ang bahaging nagpapatunay o nagpapabulaan sa buong feature. Kung
 * wala ito, ang "projected PHP 3,200" ay isang pangakong hindi kailanman
 * sinisingil.
 *
 * ── ANG SUKAT ──────────────────────────────────────────────────────
 *
 *     realized_impact = actual_revenue - baseline_projection
 *
 * Ang `baseline_projection` ay naka-freeze noong araw na nabuo ang
 * mungkahi: ang inaasahang kita kung WALANG gagawin. Ang
 * `actual_revenue` ay ang tunay na natanggap para sa mga petsang iyon.
 *
 * ── ANG LIMITASYON, NANG HAYAGAN ───────────────────────────────────
 *
 * ITO AY HINDI ISANG KONTROLADONG EKSPERIMENTO. Walang control group;
 * hindi natin makikita kung ano ang mangyayari sa parehong Setyembre
 * nang walang promo. Ang paghahambing ay laban sa SARILING baseline ng
 * modelo, kaya ang "realized impact" ay sumusukat ng dalawang bagay nang
 * magkasama: kung gumana ba ang aksyon, AT kung tama ba ang baseline.
 *
 * Kaya may dalawang paraan ng pagbabasa nito, at iba sila:
 *
 *   - APPLIED   → gaano kalapit ang naganap sa ipinangako ng aksyon.
 *   - DISMISSED / EXPIRED → walang ginawa, kaya ang pagkakaiba ay
 *     PURONG ERROR NG MODELO. Ito talaga ang mas mahalagang numero:
 *     sinusuri nito ang `DemandModel` mismo, nang hiwalay sa anumang
 *     interbensyon.
 *
 * At ang isang window ay hindi katibayan. Ang inaasahang kita ay isang
 * probabilidad sa 3–14 na slot; iisang booking ang makakapagpabaligtad
 * nito. Ang kabuuan sa maraming naitalang mungkahi ang may kahulugan —
 * kaya ganoon ito ipinapakita sa accuracy page, at sinasabi roon nang
 * tahasan.
 *
 * ── BAKIT HINDI SINUSUKAT ANG MAINTENANCE ──────────────────────────
 *
 * Sinasadyang isinasara ang window na iyon, kaya laging ZERO ang aktuwal
 * na kita — at ang paghahambing niyon sa baseline ay palaging
 * magbibigay ng eksaktong halaga ng nawalang kita, na hindi naman
 * "resulta" kundi ang mismong plano. Ang tunay na tanong ("magkano sana
 * ang kinita kung IBANG window ang pinili?") ay hindi kailanman
 * masasagot, dahil hindi ito nangyari. Minamarkahang settled ang mga ito
 * na may NULL na `realized_impact`, at hayagang inilalabas sa accuracy —
 * mas mabuti iyon kaysa sa isang numerong mukhang sukat pero hindi naman.
 */
class OutcomeTracker
{
    /**
     * @return array{settled: int, unmeasurable: int}
     */
    public function settleDue(?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::today();

        $due = Recommendation::whereNull('settled_at')
            ->whereDate('target_end', '<', $asOf->toDateString())
            ->get();

        $stats = ['settled' => 0, 'unmeasurable' => 0];

        foreach ($due as $rec) {
            try {
                if ($rec->type === Recommendation::TYPE_MAINTENANCE) {
                    $rec->update(['settled_at' => now()]);
                    $stats['unmeasurable']++;

                    continue;
                }

                // Walang naka-freeze na baseline — mungkahi ito mula sa
                // bago ang Phase 4. Hindi ito puwedeng sukatin nang
                // pabalik nang tapat, kaya minamarkahan lang na tapos.
                if ($rec->baseline_projection === null) {
                    $rec->update(['settled_at' => now()]);
                    $stats['unmeasurable']++;

                    continue;
                }

                $actual = $this->actualRevenue($rec);

                $rec->update([
                    'actual_revenue' => $actual,
                    'realized_impact' => round($actual - (float) $rec->baseline_projection, 2),
                    'settled_at' => now(),
                ]);

                $stats['settled']++;
            } catch (\Throwable $e) {
                // Ang isang bumagsak na row ay hindi dapat pumigil sa iba.
                // Iniiwang NULL ang `settled_at` para subukan ulit bukas.
                Log::error("Outcome settle failed for recommendation {$rec->id}: ".$e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Ang kitang aktuwal na natanggap para sa mga petsa/slot ng mungkahi.
     *
     * `base_amount - discount_amount`, HINDI `total_amount` — sinasadya.
     * Ang `total_amount` ay may kasamang extras (pagkain, karagdagang
     * serbisyo), na hindi kailanman bahagi ng hula: ang modelo ay
     * nagpepresyo ng slot lang, sa pamamagitan ng `quoteFor()`. Ang
     * pagsama ng extras ay magpapalobo sa bawat resulta laban sa isang
     * baseline na hindi naman sila kasama, at gagawing mukhang mas
     * matalino ang engine kaysa sa totoo.
     *
     * Bawat petsa sa [target_start, target_end] ay BUKAS noong nabuo ang
     * mungkahi — pinagsasama lang ng mga advisor ang magkakasunod na
     * petsang walang booking — kaya anumang booking na nakikita rito
     * ngayon ay dumating PAGKATAPOS ng hula. Wala tayong binibilang na
     * naibenta na bago pa man.
     */
    private function actualRevenue(Recommendation $rec): float
    {
        $villaId = Property::where('type', 'villa')->value('id');

        $query = Booking::where('property_id', $villaId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('check_in_date', '>=', $rec->target_start->toDateString())
            ->whereDate('check_in_date', '<=', $rec->target_end->toDateString());

        // Kapag may tinukoy na slot ang mungkahi, ang kabilang slot sa
        // parehong araw ay ibang produkto — hindi ito bunga ng mungkahi.
        if ($rec->slot && isset(Booking::SLOTS[$rec->slot])) {
            $query->whereTime('check_in_time', Booking::SLOTS[$rec->slot]['check_in'].':00');
        }

        $bookings = $query->get(['base_amount', 'discount_amount']);

        return round($bookings->sum(
            fn ($b) => (float) $b->base_amount - (float) $b->discount_amount
        ), 2);
    }
}
