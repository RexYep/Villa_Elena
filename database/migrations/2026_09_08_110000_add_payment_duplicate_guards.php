<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dalawang guard laban sa dobleng bayad.
 *
 * 1. `payments (booking_id, reference_number)` UNIQUE — ang idempotency
 *    check sa `PaymentController::recordPaymongoPayment()` ay isang
 *    "tingnan muna, tapos isulat" na walang anumang humahawak sa
 *    pagitan. Kaparehong hugis ng bug na nag-double-book ng 2026-09-15
 *    (v7.0) — at dito, ang puwang ay hindi teoretikal: ang webhook ay
 *    dumarating habang PADATING pa lang ang guest sa success page, kaya
 *    iyon mismo ang sandaling parehong tumatakbo ang dalawang path.
 *
 *    NULL ang `reference_number` ng mga manwal na bayad (cash sa front
 *    desk). Sa MySQL, hindi ipinapatupad ang isang unique index kapag
 *    may NULL sa alinmang bahagi ng key, kaya malayang makakapagtala ng
 *    maraming cash payment ang staff — ang mga bayad na may tunay na
 *    reference lang (`pay_xxx` ng PayMongo) ang binabantayan.
 *
 * 2. `bookings.overpayment_notified_at` — para minsan lang maabisuhan
 *    ang admin sa isang sobrang bayad. Kung wala ito, aabisuhan sila sa
 *    BAWAT recalculation ng parehong booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateReferences();

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['booking_id', 'reference_number']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('overpayment_notified_at')->nullable()->after('balance_due');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_booking_id_reference_number_unique');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('overpayment_notified_at');
        });
    }

    /**
     * Sinasadyang HINDI ito nagbabago ng kahit anong payment row.
     *
     * Sa slot_hold migration (v7.0), inalis lang natin sa index ang mas
     * huling booking ng isang double-booking at ipinagpatuloy ang
     * migration — tama iyon doon: buo pa rin ang booking at nakikita ng
     * admin. Iba ang payments: talaan sila ng pera. Ang tahimik na
     * pagbabago ng `reference_number` ng isang bayad para lang
     * makagawa ng index ay pagsira sa audit trail. Mas mabuting
     * tumigil at ipaayos sa tao.
     */
    private function assertNoDuplicateReferences(): void
    {
        $dupes = DB::table('payments')
            ->select('booking_id', 'reference_number', DB::raw('COUNT(*) as total'))
            ->whereNotNull('reference_number')
            ->where('reference_number', '!=', '')
            ->groupBy('booking_id', 'reference_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($dupes->isEmpty()) {
            return;
        }

        $detail = $dupes->map(function ($d) {
            $ids = DB::table('payments')
                ->where('booking_id', $d->booking_id)
                ->where('reference_number', $d->reference_number)
                ->pluck('id')
                ->implode(', ');

            return "booking {$d->booking_id} / ref {$d->reference_number} → payment ids {$ids}";
        })->implode('; ');

        throw new RuntimeException(
            'Cannot add the payments (booking_id, reference_number) unique index: the same gateway reference '
            .'is already recorded more than once, which means a payment was double-recorded. These are financial '
            .'records, so this migration will not alter them — review and remove the duplicate row(s) by hand, '
            ."then re-run. Found: {$detail}"
        );
    }
};
