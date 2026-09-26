<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `staff_logs` carried exactly two indexes: the primary key, and the one
 * MySQL creates for the `user_id` foreign key. That was survivable only
 * because nothing ever read the table — there is no admin page, no artisan
 * command and no export, so its 1,196 rows were written and never queried.
 *
 * The audit viewer added alongside this migration reads it constantly, and
 * every question it asks is a filter this table had no way to answer except
 * by scanning:
 *
 *   ORDER BY created_at DESC        -> the default listing and the date range
 *   WHERE action = ?                -> "show me every refund"
 *   WHERE target_table/target_id    -> "everything that happened to booking 42"
 *   WHERE user_id = ?               -> "everything this admin did"
 *
 * The composites lead with the filtered column and carry `created_at` so the
 * sort is served by the same index rather than a filesort stacked on top.
 *
 * `(target_table, target_id)` pays for itself even without the viewer: it is
 * the existing dedup lookup in AutoCheckInOutBookings, which runs once per
 * eligible booking EVERY MINUTE of the scheduler's life and was, until now,
 * a full table scan each time.
 *
 * DELIBERATELY NOT HERE: automatic pruning. How long evidence is kept is the
 * owner's decision, not a migration's, and a wrong answer is unrecoverable.
 * There is also a concrete trap — `auto_checkin_skipped_balance` rows are not
 * history, they are application STATE (the dedup above reads them to avoid
 * re-alerting admins every minute), so a naive "delete anything older than N
 * days" would resume that spam. Any future retention work must exempt them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_logs', function (Blueprint $table) {
            $table->index('created_at', 'staff_logs_created_at_index');
            $table->index(['action', 'created_at'], 'staff_logs_action_created_index');
            $table->index(['target_table', 'target_id'], 'staff_logs_target_index');
            $table->index(['user_id', 'created_at'], 'staff_logs_user_created_index');
        });
    }

    /**
     * THE SHAPE OF THIS METHOD IS NOT COSMETIC. The obvious version fails,
     * and so does the second-obvious version. Both were measured failing
     * before this comment was written.
     *
     * FIRST TRAP — `staff_logs` had a standalone `staff_logs_user_id_foreign`
     * index that MySQL created for the foreign key. Adding
     * `(user_id, created_at)` in up() made that index redundant and InnoDB
     * SILENTLY DROPPED IT: the composite leads with `user_id`, so it can
     * serve the constraint alone. Nothing asked for that and nothing said so.
     * The composite is then the ONLY index backing the key, and dropping it
     * first gives:
     *
     *   SQLSTATE[HY000]: 1553 Cannot drop index
     *   'staff_logs_user_created_index': needed in a foreign key constraint
     *
     * So the standalone index has to go back first, under its original name.
     *
     * SECOND TRAP — but only SOMETIMES. After an up()/down()/up() round trip
     * the standalone index exists again as an explicitly created one, and
     * InnoDB does not auto-drop an index it did not auto-create. Recreating
     * it unconditionally then gives:
     *
     *   SQLSTATE[42000]: 1061 Duplicate key name 'staff_logs_user_id_foreign'
     *
     * Hence the existence check. A migration that only rolls back correctly
     * the first time is not a rollback.
     *
     * Both traps bite hard because MySQL DDL IS NOT TRANSACTIONAL — a failure
     * part way through does not rewind. The first attempt left three indexes
     * dropped, one still present, and the migration still marked as run.
     */
    public function down(): void
    {
        Schema::table('staff_logs', function (Blueprint $table) {
            $table->dropIndex('staff_logs_created_at_index');
            $table->dropIndex('staff_logs_action_created_index');
            $table->dropIndex('staff_logs_target_index');
        });

        // Hand the foreign key an index of its own back BEFORE taking away
        // the composite it has been leaning on — and only if it is missing.
        if (! $this->indexExists('staff_logs', 'staff_logs_user_id_foreign')) {
            Schema::table('staff_logs', function (Blueprint $table) {
                $table->index('user_id', 'staff_logs_user_id_foreign');
            });
        }

        Schema::table('staff_logs', function (Blueprint $table) {
            $table->dropIndex('staff_logs_user_created_index');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
              LIMIT 1',
            [$table, $index]
        ) !== null;
    }
};
