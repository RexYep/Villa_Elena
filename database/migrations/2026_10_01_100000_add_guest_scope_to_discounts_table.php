<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ginagawang dalawang-aksis ang pagiging karapat-dapat sa isang promo.
 *
 * Hanggang ngayon, ang tanong lang ng `discounts` ay *KAILAN* at *ALING
 * SLOT*: pasok ba ang check-in date sa window (`start_date` →
 * `expiry_date`) at tugma ba ang `applies_to`. Ang may-ari ay nagbibigay
 * din ng diskuwento sa mga REGULAR na customer, kaya may pangalawang
 * tanong na: *SINO*.
 *
 *   - `guest_scope` — 'all' (ang dating gawi, at ang default) o
 *     'returning' (mga nakatapos na ng stay).
 *   - `min_completed_bookings` — ilang natapos nang stay ang kailangan
 *     bago maging karapat-dapat. Binabasa LANG ito kapag 'returning'
 *     ang scope; walang kahulugan ito sa isang 'all' na promo.
 *
 * Ang default na 'all' ang dahilan kung bakit walang anumang umiiral na
 * promo ang nagbabago ng kahulugan dahil sa migration na ito — pareho
 * pa rin ang bawas na nakukuha ng bawat guest bago at pagkatapos nito.
 *
 * Ang 1 ang default ng threshold: ang pinakalikas na kahulugan ng
 * "regular" ay sinumang bumalik. Nae-edit ito kada promo sa
 * Admin → Promotions, kaya ang may-ari ang nagpapasya kung 1 ba o 5.
 *
 * Bakit nasa `discounts` at hindi sa sariling table: ang diskuwento sa
 * regular ay PAREHONG bagay na may ibang pagsusuri sa pagiging
 * karapat-dapat — kapareho pa rin ng date window, ng slot match, ng
 * `used_count` na pagtutuos, ng `bookings.discount_id` na FK, at ng
 * paligsahan sa Discount::bestFor(). Ang hiwalay na table ay
 * magdodoble sa lahat ng iyon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->enum('guest_scope', ['all', 'returning'])
                ->default('all')
                ->after('applies_to');

            $table->unsignedSmallInteger('min_completed_bookings')
                ->default(1)
                ->after('guest_scope')
                ->comment('Binabasa lang kapag guest_scope = returning');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['guest_scope', 'min_completed_bookings']);
        });
    }
};
