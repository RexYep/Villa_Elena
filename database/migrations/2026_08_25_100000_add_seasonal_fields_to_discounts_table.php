<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ginagawang SEASONAL/AUTOMATIC promo ang `discounts` table.
 *
 * Ang orihinal na hugis nito ay puro promo-CODE (guest types "SUMMER15").
 * Ang napiling direksyon ay awtomatiko: walang itatype ang guest — kusang
 * nag-a-apply ang promo kapag pasok ang CHECK-IN date sa window nito,
 * kapareho ng ginagawa ng `pricing_rules` sa presyo. Kaya:
 *
 *   - `code` ay nullable na (walang code ang automatic promo). Nananatili
 *     ang unique index para hindi magkadoble kung babalikan ang code path.
 *   - `start_date` ang bagong simula ng window; ang dating `expiry_date`
 *     ang siyang katapusan. Ang NULL sa alinman ay "walang hangganan".
 *   - `applies_to` — puwedeng day-slot lang o night-slot lang ang promo
 *     (hal. "Weekday Night Special"), o pareho.
 *   - `is_public` — kontrolado nito kung lalabas ba ito sa landing page.
 *     Ang isang hindi-public na promo ay awtomatiko pa ring nag-a-apply,
 *     hindi lang ina-advertise.
 *   - `notified_at` — para ISANG BESES lang mag-blast ng in-app
 *     notification sa lahat ng customer kahit ilang beses i-edit ni admin.
 *
 * Hindi ginagamit ang `min_nights` sa modelong ito — laging 1 "night" ang
 * bawat fixed slot, kaya walang kahulugan ang minimum. Iniwan ang column
 * para hindi masira ang mga lumang row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->change();
            $table->string('description', 255)->nullable()->after('label');
            $table->date('start_date')->nullable()->after('value');
            $table->enum('applies_to', ['all', 'day', 'night'])->default('all')->after('expiry_date');
            $table->tinyInteger('is_public')->default(1)->after('applies_to');
            $table->timestamp('notified_at')->nullable()->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['description', 'start_date', 'applies_to', 'is_public', 'notified_at']);
        });
    }
};
