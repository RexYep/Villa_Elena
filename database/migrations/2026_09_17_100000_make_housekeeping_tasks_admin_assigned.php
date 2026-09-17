<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping: mula awtomatiko, papuntang task na ipinapadala ng admin.
 *
 * Dati ay kusang gumagawa ang check-in ng `checkout_clean` na task. Simula
 * ngayon ay ang admin ang gumagawa ng task (Admin → Housekeeping), at
 * tinatanggap/ginagawa ito ng staff sa frontdesk.
 *
 *   - `title`, `location`, `priority`, `due_time` — ang mga field ng form
 *     ng admin. Walang pangalan ang mga `type=room` na record simula v5.0,
 *     kaya malayang teksto ang `location` ("Room C", "Pool area") imbes na
 *     dropdown ng property.
 *   - `created_by` — sino ang nagpadala.
 *   - `started_at` — para sukatin kung gaano tagal ang pagtapos.
 *   - `cancelled` sa status — para bawiin ang task na naipadala nang mali
 *     nang hindi kailangang markahan itong "Done".
 *
 * Ang mga natitirang bukas na awtomatikong task ay minamarkahang
 * `cancelled` (nananatili ang kasaysayan), dahil wala nang nagpapatakbo sa
 * mga ito at kung hindi ay mananatili sila sa frontdesk magpakailanman.
 */
return new class extends Migration
{
    private const NOTE = ' [Cancelled automatically: checkout cleaning is no longer auto-created — '
                       . 'housekeeping tasks are now sent by the admin.]';

    public function up(): void
    {
        Schema::table('housekeeping_tasks', function (Blueprint $table) {
            $table->string('title', 150)->nullable()->after('task_type');
            $table->string('location', 100)->nullable()->after('title');
            $table->enum('priority', ['normal', 'urgent'])->default('normal')->after('location');
            $table->time('due_time')->nullable()->after('due_date');
            $table->foreignId('created_by')->nullable()->after('assigned_to')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->after('notes');
        });

        DB::statement("ALTER TABLE housekeeping_tasks MODIFY COLUMN status
            ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending'");

        DB::table('housekeeping_tasks')
            ->where('task_type', 'checkout_clean')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('created_by')
            ->update([
                'status'     => 'cancelled',
                'notes'      => DB::raw('CONCAT(COALESCE(notes, \'\'), ' . DB::getPdo()->quote(self::NOTE) . ')'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Walang `cancelled` sa lumang enum — `completed` ang pinakamalapit.
        DB::table('housekeeping_tasks')->where('status', 'cancelled')->update(['status' => 'completed']);

        DB::statement("ALTER TABLE housekeeping_tasks MODIFY COLUMN status
            ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending'");

        Schema::table('housekeeping_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['title', 'location', 'priority', 'due_time', 'started_at']);
        });
    }
};
