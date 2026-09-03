<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRESCRIPTIVE ANALYTICS — ang iisang table na idinagdag ng feature na ito.
 *
 * Walang binabago sa `bookings`, `discounts`, `pricing_rules`, o
 * `availability_blocks`. Sinasadya iyon: ang engine ay READ-ONLY sa
 * lahat ng umiiral na datos. Ang tanging isinusulat nito ay ang mga
 * MUNGKAHI dito — at ang isang mungkahi ay walang epekto kahit kanino
 * hangga't hindi ito pinipindot ng admin.
 *
 * Ang kabaligtarang link (anong record ang nagawa nang i-apply ito) ay
 * nakatago rin DITO — `applied_record_type` / `applied_record_id` — sa
 * halip na maglagay ng `recommendation_id` column sa mga lumang table.
 * Kaya kung buburahin ang buong feature, isang table lang ang mawawala
 * at walang maiiwang bakas sa financial na datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();

            // Aling advisor ang gumawa nito (idle_date_promo, maintenance_window, ...)
            $table->string('type', 50);
            $table->string('title', 150);
            $table->text('summary');

            // Ang mga hilaw na numerong pinagbatayan — ipinapakita bilang
            // "Why this?" na bullet list. Naka-JSON para hindi na kailangang
            // i-recompute (at para hindi magbago ang paliwanag pagkatapos).
            $table->json('evidence')->nullable();

            // Anong bahagi ng kalendaryo ang tinutukoy
            $table->date('target_start');
            $table->date('target_end');
            $table->string('slot', 10)->nullable()->comment('day | night | null = pareho');

            // Ang aksyon, handa nang isagawa
            $table->string('action_type', 40)->comment('create_promo | create_block');
            $table->json('action_payload');

            // Ang kinompyut na katwiran
            $table->decimal('expected_impact', 10, 2)->default(0)->comment('PHP; positibo = kita o naiwasang lugi');
            $table->decimal('confidence', 5, 2)->default(0)->comment('0-100, base sa laki ng sample');
            $table->integer('sample_size')->default(0)->comment('ilang historical na obserbasyon ang pinagbatayan');

            $table->enum('status', ['new', 'applied', 'dismissed', 'expired'])->default('new');

            // Dedupe: pareho ang fingerprint = pareho ang mungkahi. Araw-araw
            // tumatakbo ang generator; kung wala itong index na ganito,
            // madadagdagan ng kaparehong card ang page kada umaga.
            $table->string('fingerprint', 64)->unique();

            $table->timestamp('generated_at')->nullable();

            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applied_record_type', 40)->nullable();
            $table->unsignedBigInteger('applied_record_id')->nullable();

            $table->timestamp('dismissed_at')->nullable();
            $table->string('dismiss_reason', 255)->nullable();

            // Phase 4 (outcome tracking): kapag lumipas na ang target window,
            // dito isusulat ang AKTWAL na naging epekto, para masukat ang
            // sistema laban sa sarili nitong pangako. Null hanggang doon.
            $table->decimal('realized_impact', 10, 2)->nullable();

            $table->timestamps();

            $table->index(['status', 'expected_impact']);
            $table->index(['type', 'target_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
