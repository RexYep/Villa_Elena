<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4 — OUTCOME TRACKING.
 *
 * Hanggang ngayon, ang sistema ay nangangako lang. Ang `expected_impact`
 * ay hula, at walang anumang bumabalik para tingnan kung natupad ito.
 * Ang tatlong column na ito ang nagsasara ng singsing.
 *
 * BAKIT KAILANGANG I-FREEZE ANG `baseline_projection` SA HALIP NA
 * KUWENTAHIN ULIT SA HULI:
 *
 * Ang `expected_impact` ay ang DAGDAG lang (kita kung kikilos, bawas ang
 * kita kung hindi), hindi ang antas. Para masukat ang naganap, kailangan
 * ang antas — kung magkano ang inaasahan kapag WALANG ginawa. Puwede
 * sanang kuwentahin ulit iyon pagsapit ng petsa, pero ang fill rate ay
 * nagbago na noon, kaya ang makukuha ay ibang numero kaysa sa aktuwal na
 * ipinangako. Ang isang hulang tahimik na inaayos matapos malaman ang
 * sagot ay hindi na hula. Kaya nakatala ito sa mismong sandali ng
 * pagmumungkahi at hindi na ginagalaw.
 *
 * `realized_impact = actual_revenue - baseline_projection`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            // Ang inaasahang kita sa target window kung WALANG gagawin —
            // naka-freeze noong araw na nabuo ang mungkahi.
            $table->decimal('baseline_projection', 10, 2)->nullable()->after('expected_impact');

            // Ang tunay na pumasok sa mga petsang iyon, batay sa mga
            // booking na aktwal na natuloy.
            $table->decimal('actual_revenue', 10, 2)->nullable()->after('realized_impact');

            // Kailan sinukat. Ang NULL ay "hindi pa tapos ang window".
            $table->timestamp('settled_at')->nullable()->after('actual_revenue');

            // Para mabilis mahanap ng settler ang mga nakabinbin.
            $table->index(['settled_at', 'target_end']);
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropIndex(['settled_at', 'target_end']);
            $table->dropColumn(['baseline_projection', 'actual_revenue', 'settled_at']);
        });
    }
};
