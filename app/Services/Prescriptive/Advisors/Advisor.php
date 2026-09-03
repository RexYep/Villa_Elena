<?php

namespace App\Services\Prescriptive\Advisors;

use App\Services\Prescriptive\DemandModel;

/**
 * Kontrata ng isang advisor.
 *
 * Ang bawat advisor ay nagbabalik ng mga ARRAY ng attribute na handa nang
 * gawing `Recommendation` row — hindi sila sumusulat sa database mismo.
 * Ang `PrescriptiveEngine` ang may hawak ng pagsusulat, ng dedupe, at ng
 * pag-expire, kaya iisa lang ang lugar na kailangang unawain kapag
 * nagdagdag ng bagong advisor.
 *
 * Mahalagang tuntunin: TAMANG SAGOT ang WALANG LAMAN na array.
 * Ang isang engine na laging may sinasabi ay ingay, hindi payo — kung
 * maayos naman ang kalendaryo, walang dapat imungkahi.
 */
abstract class Advisor
{
    /** @return array<int, array<string, mixed>> */
    abstract public function generate(DemandModel $demand): array;

    /**
     * Ang `recommendations.type` na pagmamay-ari ng advisor na ito.
     *
     * Kailangan ito ng engine para malaman kung ANONG mga card ang may
     * karapatan itong i-expire. Kapag bumagsak ang isang advisor, ang mga
     * card niya ay dapat manatili — walang alam ang sistema tungkol sa
     * kanila sa sandaling iyon, at ang katahimikan ay hindi katibayan na
     * hindi na sila totoo.
     */
    abstract public function type(): string;

    /** Pagkakakilanlan ng mungkahi, para hindi ito madoble kada araw. */
    protected function fingerprint(array $parts): string
    {
        return hash('sha256', implode('|', $parts));
    }
}
