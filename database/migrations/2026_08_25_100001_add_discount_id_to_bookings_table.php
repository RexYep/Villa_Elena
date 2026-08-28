<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail kung ALIN na promo ang na-apply sa isang booking.
 *
 * May `discount_amount` na ang bookings mula pa noon, pero ang halaga
 * lang ang itinatago nito — hindi masasagot ng peso amount kung anong
 * promo iyon, kaya hindi masusukat kung kumikita ba talaga ang isang
 * kampanya. `nullOnDelete()` para hindi mabura ang booking history kapag
 * tinanggal ni admin ang lumang promo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('discount_id')->nullable()->after('discount_amount')
                ->constrained('discounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
            $table->dropColumn('discount_id');
        });
    }
};
