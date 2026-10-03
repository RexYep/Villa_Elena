<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Itinatala ang SLOT ng booking bilang tunay na datos, sa halip na
 * hulaan ito mula sa check_in_time/check_out_time kada tanong.
 *
 * Bakit kailangan:
 *
 *   Ang `Booking::slotKey()` ay reverse-engineering — tinitingnan nito
 *   ang nakaimbak na oras at hinahanap kung aling slot ang tugma. Gumana
 *   iyon habang DALAWA ang slot, dahil natatangi ang bawat check-in time
 *   (08:00 at 19:00). Dalawang bagay ang sumisira roon:
 *
 *   1. Ang 22-Hours na slot (19:00–17:00, pinayagan ng may-ari) ay
 *      KAPAREHO ng check-in time ng Night (19:00–06:00). Dalawang field
 *      na ang kailangang ihambing, hindi isa.
 *   2. Ang `extendStay()` ay nagbabago ng check_out_time at hindi ng
 *      check_in_time. Kapag pinahaba na ang isang stay, WALA nang
 *      kombinasyon ng oras na tumutugma sa alinmang slot — kaya hindi na
 *      talaga mababawi ang pinagmulang slot nito sa oras lamang.
 *
 *   Ang column na ito ang sumasagot sa "ano ang binook" kahit hindi na
 *   sinasabi ng mga oras.
 *
 * ⚠️ SARADO NA ANG BINTANA NG BACKFILL PAGKATAPOS NITO.
 *
 * Sinasadyang HARDCODED dito ang oras ng Day at Night, at HINDI
 * binabasa ang `Booking::SLOTS`. Dalawang dahilan:
 *
 *   - Ang isang migration ay dapat laging nangangahulugan ng iisang
 *     bagay. Kung `Booking::SLOTS` ang babasahin nito, ang parehong
 *     migration na ito ay iba na ang gagawin sa isang bagong database
 *     kapag nadagdagan na ng pangatlong slot ang konstanteng iyon.
 *   - Sa sandaling umiral ang isang tunay na 22-oras na booking, HINDI
 *     NA ito mababackfill: 19:00 ang check-in ng Night at ng 22-Hours,
 *     kaya ang isang pinahabang Night ay hindi na maipagkakaiba sa isang
 *     22-oras na stay sa pamamagitan ng oras. Dito lang, ngayon lang,
 *     tiyak pa ang kasagutan — kaya dito isinusulat.
 *
 * Tumutugma lang ang backfill sa EKSAKTONG oras (at sa tamang
 * pagtawid-ng-araw). Ang mga legacy row bago ang v5.1 (hal. 2:00 PM na
 * check-in) at ang mga pinahabang stay ay mananatiling NULL — tama iyon:
 * wala talaga silang slot, at ang `slotKey()` ay bumabalik sa
 * paghahambing ng oras para sa mga ganoong row.
 *
 * Kasama ang mga soft-deleted at kinanselang booking. Hindi ito
 * paghahabol sa slot na gaya ng `slot_hold` — paglalarawan lang ito ng
 * BINOOK, kaya walang kinalaman ang status dito.
 */
return new class extends Migration
{
    /**
     * Ang mga slot noong tumakbo ang migration na ito: key => [check-in,
     * check-out, tumatawid-ba-ng-araw].
     */
    private const SLOTS_AT_MIGRATION_TIME = [
        'day' => ['08:00:00', '17:00:00', false],
        'night' => ['19:00:00', '06:00:00', true],
    ];

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Katabi ng `slot_hold`, na hango rin sa parehong mga field.
            $table->string('slot', 20)->nullable()->after('slot_hold');
        });

        foreach (self::SLOTS_AT_MIGRATION_TIME as $key => [$in, $out, $overnight]) {
            $query = DB::table('bookings')
                ->whereNull('slot')
                ->where('check_in_time', $in)
                ->where('check_out_time', $out);

            // Ang pagtawid-ng-araw ay bahagi ng tugma, hindi dagdag lang:
            // ang isang sirang row na may oras na pang-overnight ngunit
            // iisa ang petsa (negatibo ang haba) ay hindi isang tunay na
            // Night na booking, at hindi dapat matawag na ganoon.
            $overnight
                ? $query->whereColumn('check_out_date', '!=', 'check_in_date')
                : $query->whereColumn('check_out_date', '=', 'check_in_date');

            $query->update(['slot' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('slot');
        });
    }
};
