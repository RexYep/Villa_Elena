<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Paglipat ng payments.payment_method sa QR Ph.
     *
     * Hindi na hiwa-hiwalay na ini-enable ang GCash at Maya sa PayMongo
     * (kailangan ng bukod na aplikasyon/aktibasyon ang bawat isa) —
     * saklaw na sila ng QR Ph, na aktibo na sa account ng resort. Iisa
     * na lang ang online rail: QR Ph. Ang `cash` ay nananatili para sa
     * perang aktwal na inaabot sa front desk.
     *
     * APAT ang hakbang, at MAHALAGA ang pagkakasunod:
     *
     *   1. Itago sa `notes` ang orihinal na method.
     *   2. PALAKIHIN muna ang ENUM para tanggapin ang 'qrph'. Hindi
     *      puwedeng laktawan ito — kung ita-target agad ang relabel,
     *      itatapon ng MySQL ang value bilang `Data truncated`, dahil
     *      wala pang 'qrph' sa ENUM na pinapasukan.
     *   3. Saka mag-relabel.
     *   4. Saka paliitin sa panghuling hugis. (Kabaligtaran ito ng
     *      v5.5 shrink migration, na nag-relabel patungo sa isang
     *      EXISTING na value — kaya isang ALTER lang ang kailangan
     *      doon.)
     *
     * Isinusulat muna ang orihinal na method sa `notes` bago mag-relabel.
     * 36 sa mga `gcash` na row (at 2 `paymaya`) ay manu-manong itinala —
     * tunay na direktang GCash/Maya transfer na tinanggap ng staff,
     * hindi QR Ph. Hindi mabubura ang katotohanang iyon dahil lang
     * pinaliit ang ENUM; ang `notes` ang magiging tanging tala nito.
     */
    public function up(): void
    {
        // 1) Itago ang orihinal na method bago ito mawala.
        //    Idempotent — nilalaktawan ang mga may marka na, para hindi
        //    dumoble kapag na-rerun matapos ang isang bigong takbo.
        foreach (['gcash' => 'GCash', 'paymaya' => 'PayMaya'] as $value => $label) {
            DB::table('payments')
                ->where('payment_method', $value)
                ->where(function ($q) {
                    $q->whereNull('notes')
                      ->orWhere('notes', 'not like', '%before the QR Ph switch%');
                })
                ->update([
                    'notes' => DB::raw(
                        "TRIM(CONCAT(COALESCE(notes, ''), ' [Originally recorded as {$label} before the QR Ph switch.]'))"
                    ),
                ]);
        }

        // 2) Palakihin muna — kailangang tanggapin ng ENUM ang 'qrph'
        //    bago may row na makapag-hawak nito
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_method ENUM('qrph','gcash','paymaya','cash') NOT NULL
        ");

        // 3) Relabel
        DB::table('payments')
            ->whereIn('payment_method', ['gcash', 'paymaya'])
            ->update(['payment_method' => 'qrph']);

        // 4) Paliitin sa panghuling hugis
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_method ENUM('qrph','cash') NOT NULL
        ");
    }

    /**
     * Naibabalik lang ang mas malawak na ENUM.
     *
     * Hindi na matutukoy kung aling `qrph` na row ang dating `gcash` at
     * alin ang `paymaya` — nasa `notes` na lang iyon, na sinasadyang
     * hindi ginagalaw dito para manatiling nababasa ang kasaysayan.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_method ENUM('qrph','gcash','paymaya','cash') NOT NULL
        ");
    }
};
