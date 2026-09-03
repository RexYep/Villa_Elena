<?php

namespace App\Services\Prescriptive;

use App\Models\Recommendation;
use App\Services\Prescriptive\Advisors\Advisor;
use App\Services\Prescriptive\Advisors\IdleDatePromoAdvisor;
use App\Services\Prescriptive\Advisors\MaintenanceWindowAdvisor;
use App\Services\Prescriptive\Advisors\PeakRateAdvisor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pinapatakbo ang lahat ng advisor at inililigpit ang resulta.
 *
 * ANG PINAKAMAHALAGANG BAGAY NA GINAGAWA NG KLASENG ITO: ang tanging
 * isinusulat nito ay mga row sa `recommendations`. Walang `Discount`,
 * walang `AvailabilityBlock`, walang `PricingRule`, walang `Setting`.
 * Kahit araw-araw itong tumakbo, hindi ito makakagalaw ng presyo at
 * walang makikita ang guest. Ang pagsasakatuparan ay nasa
 * `Admin\PrescriptiveController::apply()` — at doon lang, kapag may
 * taong pumindot.
 *
 * Tatlong tuntunin sa pag-iingat ng listahan:
 *
 * 1. DEDUPE. Ang parehong mungkahi ay may parehong fingerprint, kaya ang
 *    pang-araw-araw na pagtakbo ay nag-uupdate sa iisang card sa halip na
 *    magdagdag ng bago kada umaga.
 * 2. HINDI BINUBUHAY ANG PATAY. Ang isang na-dismiss (o na-apply) nang
 *    mungkahi ay hindi na muling ibabalik sa `new` — ang "hindi" ng admin
 *    ay sagot, hindi paanyaya para magtanong ulit bukas.
 * 3. INAAMIN ANG PAGLIPAS. Ang bukas na mungkahing hindi na muling
 *    nabuo — dahil nabook na ang petsa, o lumipas na ito — ay minamarkahan
 *    agad na `expired` sa halip na maiwang nakasabit sa page bilang
 *    aksyong hindi na naman puwede.
 */
class PrescriptiveEngine
{
    private DemandModel $demand;

    public function __construct(?DemandModel $demand = null)
    {
        $this->demand = $demand ?? new DemandModel;
    }

    /** @return array<int, Advisor> */
    public function advisors(): array
    {
        return [
            new IdleDatePromoAdvisor,
            new PeakRateAdvisor,
            new MaintenanceWindowAdvisor,
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
            // Ang isang bumagsak na advisor ay hindi dapat pumatay sa iba —
            // parehong hugis ng pag-iingat na ginagamit sa mail at broadcast.
            try {
                $batch = $advisor->generate($this->demand);
            } catch (\Throwable $e) {
                Log::error('Prescriptive advisor failed: '.$advisor::class.' — '.$e->getMessage());

                continue;
            }

            // Nakarating dito nang buo — kaya lang siya may karapatang
            // mag-expire ng sarili niyang mga card.
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

                // Ang `applied` at `dismissed` ay DESISYON NG TAO — hindi
                // sila ginagalaw kailanman. Ang "hindi" ng admin ay sagot,
                // hindi paanyayang magtanong ulit bukas.
                if (in_array($existing->status, ['applied', 'dismissed'], true)) {
                    $stats['skipped']++;

                    continue;
                }

                // Ang `expired` naman ay HINDI desisyon ng tao — sariling
                // pagtatala lang ito ng engine na "hindi ko na ito nakita
                // noong huling takbo". Kapag muli itong nabuo, totoo na
                // naman ang pagkakataon at dapat itong ibalik.
                //
                // Nahuli ito sa pagsubok: ibinaba ang peak threshold, nabuo
                // ang mga card, ibinalik ang threshold, kaya nag-expire
                // sila — at nang ibaba itong muli, tahimik na tinanggihan
                // ang lahat ng 23. Ang parehong bitag ay tatama sa totoong
                // buhay kapag nakansela ang isang booking: nabubuhay ulit
                // ang petsa, pero patay na ang card habambuhay.
                $revived = $existing->status === 'expired';

                $existing->update($attrs + [
                    'status' => 'new',
                    'generated_at' => $now,
                    // Malinis na simula — walang dalang lumang bakas ng
                    // pagkakataong ito.
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
     * Isinasara ang mga bukas na mungkahing hindi na muling nabuo.
     *
     * Iyon ang tanda na hindi na sila totoo: nabook na ang petsa, may
     * ibang promong sumasakop na rito, o lumipas na ang window. Mas
     * mabuting mawala ang card kaysa mag-alok ng Apply na magbubunga ng
     * promo para sa nakaraan.
     *
     * ANG BANTAY: `$succeededTypes` LANG ANG SINASAKOP.
     *
     * Natuklasan ito nang mamatay ang Redis sa dev — bumagsak ang lahat ng
     * advisor sa `Setting::get()`, walang naibalik na fingerprint, at
     * tahimik na ini-expire ng bersyong ito ang BAWAT bukas na card. Isang
     * outage sa cache ang naglilinis sana ng buong page, at walang
     * anumang tanda maliban sa isang linya sa log.
     *
     * Ang katahimikan ng isang bumagsak na advisor ay hindi katibayan na
     * hindi na totoo ang mga mungkahi niya. Kapag hindi siya nakatapos,
     * nananatili ang mga card niya hanggang sa susunod na matagumpay na
     * pagtakbo.
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
