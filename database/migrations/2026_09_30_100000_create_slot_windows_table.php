<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kung ALING PETSA inaalok ang isang slot na hindi pang-araw-araw.
 *
 * Ang 22-Hours Stay ay hindi isang palaging bukas na slot na gaya ng Day
 * at Night. Pinipili ng may-ari kung kailan ito ibibigay — hal. "ang
 * gabi ng Okt 2 hanggang hapon ng Okt 3" — at sa lahat ng iba pang petsa
 * ay Day/Night lang ang inaalok. Sa v7.47 ay isang pandaigdigang switch
 * ito: sa sandaling mapresyuhan, lumilitaw ang slot sa BAWAT petsa. Mali
 * iyon, at ito ang pumapalit doon.
 *
 * IISANG ROW = IISANG INAALOK NA STAY, kaya `check_in_date` at hindi
 * start/end na saklaw. Ang haba ay hindi iniimbak: hinahango ito sa
 * `Booking::slotDateTimes($slot, $check_in_date)`, ang parehong iisang
 * pinagmumulan ng katotohanan na ginagamit ng bawat booking path. Kung
 * iimbak ang span dito, dalawa na ang magsasabi ng haba ng isang slot at
 * tiyak na maghihiwalay sila.
 *
 * `slot` ay isang column at hindi ipinapalagay na `stay22`: kapag may
 * bagong slot na kailangan din ng ganitong pagtrato, wala nang migration.
 *
 * ⚠️ HINDI ITO KAPAREHO NG `availability_blocks`. Ang block ay
 * NAGSASARA ng petsa. Ito ay NAGBUBUKAS ng slot na kung hindi ay hindi
 * inaalok. Magkasalungat sila, at pareho silang tinitingnan sa
 * check-in date — tingnan ang `AvailabilityBlock::scopeCoveringDate()`
 * para sa dahilan kung bakit check-in date ang batayan at hindi ang
 * buong haba ng stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('slot', 20);
            $table->date('check_in_date');
            $table->tinyInteger('is_active')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Walang saysay ang dalawang row para sa iisang slot sa iisang
            // petsa, at ang pagpayag doon ay nangangahulugang dalawang
            // beses na lilitaw ang parehong alok sa listahan ng admin.
            $table->unique(['property_id', 'slot', 'check_in_date']);

            // Ang tanong na itinatanong kada petsa ng grid at ng calendar.
            $table->index(['property_id', 'slot', 'check_in_date', 'is_active'], 'slot_windows_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_windows');
    }
};
