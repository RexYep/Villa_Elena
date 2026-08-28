<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ang talaan ng bawat PagTATANGKANG ipadala ang isang refund.
 *
 * Bakit hiwalay sa `refund_destinations`?
 *
 *   - Ang destinasyon ay KASALUKUYANG sagot sa "saan ipapadala?" —
 *     nababago ito ng guest hangga't hindi pa naipapadala.
 *   - Ang transfer ay ang HINDI NA MABABAGONG tala ng "saan ito
 *     aktwal na napunta, at ano ang nangyari". Kailangan itong
 *     manatili kahit palitan pa ng guest ang destinasyon niya bukas.
 *
 * Kaya may sariling snapshot ng institusyon at account ang bawat row
 * dito. Hindi ito pag-uulit ng datos nang walang saysay: kung ang
 * unang pagtatangka ay napunta sa maling account at inayos ng guest
 * ang detalye, ang tanong na "saan napunta ang unang ₱2,000?" ay may
 * sagot lang kung nakasnapshot iyon dito.
 *
 * MARAMI kada refund, sinasadya — libre ang bigong transfer (`fee`
 * ay ibinabalik sa 0, napatunayan sa live noong 2026-08-23), kaya
 * ang muling pagsubok matapos ayusin ang detalye ay normal, hindi
 * kataliwasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_transfers', function (Blueprint $table) {
            $table->id();

            // Ang refund na `payments` row na ipinapadala nito.
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();

            // ── Pagkakakilanlan sa PayMongo ──────────────────────────
            //
            // Nullable ang `transfer_id`: ang row ay isinusulat BAGO
            // tawagin ang PayMongo (para hindi makadoble ang isang
            // dobleng pindot), kaya may sandaling wala pa ito.
            $table->string('transfer_id', 64)->nullable()->unique();
            $table->string('batch_transfer_id', 64)->nullable();

            // Atin ito, ipinapadala natin — at ito ang nananatiling
            // matatag na susi kapag hindi umabot ang sagot ng API.
            $table->string('reference_number', 100)->unique();

            // ── Estado ───────────────────────────────────────────────
            //
            // Tatlo lang ang ibinibigay ng PayMongo: pending, succeeded,
            // failed. Idinagdag ang `error` para sa mga pagkabigong
            // hindi man lang nakarating sa kanila (walang network,
            // tinanggihan ang request) — iba iyon sa isang transfer na
            // tinanggihan ng bangko, at hindi dapat pagsamahin.
            $table->enum('status', ['pending', 'succeeded', 'failed', 'error'])->default('pending');

            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);

            // ── Sagot ng provider ────────────────────────────────────
            //
            // Ang `provider_reference_number` ay NAGBABAGO: sa unang
            // sagot ay echo lang ito ng sarili nating reference; kapag
            // na-settle na ito ang tunay nang trace ng bangko. Kaya
            // isinusulat lang ito mula sa na-settle nang sagot.
            $table->string('provider_reference_number', 100)->nullable();
            $table->string('provider_error_code', 32)->nullable();
            $table->string('provider_error_message', 255)->nullable();

            // ACTC kapag tinanggap, RJCT kapag tinanggihan. ISO 20022
            // ito, hindi ang mga string na nasa test-case docs.
            $table->string('sub_code', 16)->nullable();

            // ── Snapshot ng pinadalhan ───────────────────────────────
            $table->string('institution_name');
            $table->string('institution_bic', 16);
            $table->string('account_number', 64);
            $table->string('account_name');

            // ── Sino at kailan ───────────────────────────────────────
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            // Ang tanong na palaging itinatanong: "ano ang pinakahuling
            // nangyari sa refund na ito?"
            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_transfers');
    }
};
