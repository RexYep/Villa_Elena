<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dagdagan ng `stay22` ang `discounts.applies_to`.
 *
 * Ang `applies_to` ang nagtatakda kung aling slot ang saklaw ng isang
 * seasonal promo (`all` / `day` / `night`). Kapag may pangatlong slot na
 * at hindi ito naidagdag dito, ang isang promong para lang sa 22-oras na
 * stay ay hindi maitatala — at mas malala, ang isang admin na pipili nito
 * sa form ay makakakuha ng "Data truncated for column 'applies_to'".
 *
 * Ang `all` ay awtomatiko nang sumasaklaw sa bagong slot: ang
 * `Discount::isValidOn()` ay dumadaan lang sa paghahambing ng slot kapag
 * `applies_to !== 'all'`, kaya walang existing promo ang nagbabago ng
 * kahulugan dahil sa migration na ito.
 *
 * TANDAAN: pinapanatili ang DEFAULT na 'all'. Ang MySQL ay hindi
 * nagpapanatili ng default kapag MODIFY lang ang ginamit nang hindi
 * inuulit ang buong kahulugan ng column.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE discounts
            MODIFY applies_to ENUM('all','day','night','stay22') NOT NULL DEFAULT 'all'
        ");
    }

    public function down(): void
    {
        // Ang anumang promong naka-'stay22' ay kailangang ilipat muna —
        // kung hindi, mag-e-error ang rollback na ito. Ginagawa itong
        // 'night' dahil iyon ang slot na kapareho ng oras ng check-in
        // (7:00 PM), kaya iyon ang pinakamalapit na kahulugan.
        DB::statement("UPDATE discounts SET applies_to = 'night' WHERE applies_to = 'stay22'");

        DB::statement("
            ALTER TABLE discounts
            MODIFY applies_to ENUM('all','day','night') NOT NULL DEFAULT 'all'
        ");
    }
};
