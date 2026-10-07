<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recommendations became plain IF–THEN rules (v7.58).
 *
 * The seven columns dropped here belonged to the model that was removed:
 * a peso "expected impact" computed from an assumed price elasticity, a
 * "confidence" derived from sample size, and the outcome tracking that
 * compared actual revenue with that model's own baseline. Nothing reads
 * them any more, and on the day this was written not one row had ever been
 * measured (`realized_impact` was NULL everywhere).
 *
 * Two data changes ride along, both needed for the page to make sense the
 * moment this runs:
 *
 * - Every card still `new` is marked `expired`. It was worked out by the old
 *   method and its payload has the old shape; the next run of
 *   `prescriptive:generate` writes fresh ones.
 * - The settings rows of the removed assumptions are deleted, so
 *   Admin → Settings does not keep saving values nothing reads.
 *
 * `down()` restores the columns (empty) but cannot restore what they held.
 */
return new class extends Migration
{
    private const REMOVED_SETTINGS = [
        'prescriptive_lookback_days',
        'prescriptive_lookahead_days',
        'prescriptive_idle_threshold',
        'prescriptive_elasticity',
        'prescriptive_max_discount',
        'prescriptive_min_impact',
        'prescriptive_peak_threshold',
        'prescriptive_peak_elasticity',
        'prescriptive_max_increase',
    ];

    public function up(): void
    {
        DB::table('recommendations')
            ->where('status', 'new')
            ->update(['status' => 'expired', 'updated_at' => now()]);

        Schema::table('recommendations', function (Blueprint $table) {
            // Indexes first: MySQL refuses to drop a column an index still names.
            $table->dropIndex(['status', 'expected_impact']);
            $table->dropIndex(['settled_at', 'target_end']);
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropColumn([
                'expected_impact',
                'baseline_projection',
                'confidence',
                'sample_size',
                'realized_impact',
                'actual_revenue',
                'settled_at',
            ]);

            // Open cards are listed soonest-first now, not by peso value.
            $table->index(['status', 'target_start']);
        });

        DB::table('settings')->whereIn('setting_key', self::REMOVED_SETTINGS)->delete();

        // A query-builder delete fires no model event, so the cached copy of
        // the settings has to be dropped by hand. Never fatal: a stale entry
        // only carries a few extra keys until it expires.
        try {
            Cache::forget(\App\Models\Setting::CACHE_KEY);
        } catch (\Throwable $e) {
            //
        }
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropIndex(['status', 'target_start']);

            $table->decimal('expected_impact', 10, 2)->default(0)->after('action_payload');
            $table->decimal('baseline_projection', 10, 2)->nullable()->after('expected_impact');
            $table->decimal('confidence', 5, 2)->default(0)->after('baseline_projection');
            $table->integer('sample_size')->default(0)->after('confidence');
            $table->decimal('realized_impact', 10, 2)->nullable()->after('dismiss_reason');
            $table->decimal('actual_revenue', 10, 2)->nullable()->after('realized_impact');
            $table->timestamp('settled_at')->nullable()->after('actual_revenue');

            $table->index(['status', 'expected_impact']);
            $table->index(['settled_at', 'target_end']);
        });
    }
};
