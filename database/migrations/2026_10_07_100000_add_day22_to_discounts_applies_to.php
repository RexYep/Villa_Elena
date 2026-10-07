<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dagdagan ng `day22` ang `discounts.applies_to`.
 *
 * Ang `day22` (8:00 AM – 6:00 AM kinabukasan) ang ikalawang 22-oras na
 * slot sa `Booking::SLOTS`. Iniaalok ng promo form ang BAWAT slot doon
 * (`PromotionController::validated()`), kaya kung wala ito sa ENUM, ang
 * admin na pipili nito ay makakakuha ng "Data truncated for column
 * 'applies_to'" — kapareho ng dahilan ng
 * 2026_09_28_110001_add_stay22_to_discounts_applies_to.
 *
 * Walang existing promo ang nagbabago ng kahulugan: ang `all` ay
 * awtomatiko nang sumasaklaw sa bagong slot.
 *
 * TANDAAN: inuulit ang DEFAULT na 'all' — hindi ito pinapanatili ng
 * MySQL sa isang MODIFY na hindi buo ang kahulugan ng column.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE discounts
            MODIFY applies_to ENUM('all','day','night','stay22','day22') NOT NULL DEFAULT 'all'
        ");
    }

    public function down(): void
    {
        // Ang promong naka-'day22' ay inililipat muna sa 'stay22' — ang
        // kapwa nito 22-oras na slot, at kapareho ng presyo — kung hindi
        // ay mag-e-error ang rollback.
        DB::statement("UPDATE discounts SET applies_to = 'stay22' WHERE applies_to = 'day22'");

        DB::statement("
            ALTER TABLE discounts
            MODIFY applies_to ENUM('all','day','night','stay22') NOT NULL DEFAULT 'all'
        ");
    }
};
