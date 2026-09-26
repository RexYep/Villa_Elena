<?php

namespace App\Console\Commands;

use App\Models\StaffLog;
use Illuminate\Console\Command;

/**
 * Apply the staff_logs retention policy (Task 12 F8).
 *
 * WHY THIS BECAME URGENT. Nothing has ever pruned this table, and it was already
 * the largest in the database (448 KB of 1.63 MB; 1,206 of 2,283 rows). v7.41
 * then made it the security-event store, so it grows with REJECTED REQUESTS as
 * well as with staff activity — and the volume of those is chosen by whoever is
 * rattling the door, not by the resort. The rationing in SecurityMonitor bounds
 * that to two rows per window per actor, but bounded growth is still growth on a
 * free-tier database.
 *
 * TWO TIERS, not one date. A successful login is noise a fortnight later; a
 * lockout, a password change or a credential spray is what an incident
 * investigation reads — and incidents are found long after they happen, which is
 * the whole argument for keeping that tier four times longer.
 *
 * THE EXEMPT LIST IS LOAD-BEARING, and it is not about record-keeping.
 * `auto_checkin_skipped_balance` rows are read by the scheduler as a dedup key.
 * Delete one and `bookings:auto-checkinout` — which runs everyMinute() — starts
 * re-notifying the admins about that booking every minute until staff resolve it.
 * Pruning it is a functional bug, so the guard lives in config and this command
 * refuses to run if it has been emptied by accident.
 *
 * DELETES ARE CHUNKED. A single unbounded DELETE on the biggest table in a
 * free-tier MySQL holds row locks for as long as it takes; the front desk and the
 * public booking form are both writing to this table through StaffLog::record().
 */
class PruneStaffLogs extends Command
{
    protected $signature = 'staff-logs:prune
                            {--dry-run : Report what would be deleted and delete nothing}
                            {--chunk=500 : Rows per DELETE}';

    protected $description = 'Delete staff_logs rows older than the retention policy in config/audit.php';

    public function handle(): int
    {
        $securityDays = (int) config('audit.retention.security_days');
        $routineDays = (int) config('audit.retention.routine_days');
        $exempt = (array) config('audit.never_prune');
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        // An empty exempt list is almost certainly a mistake rather than a
        // decision, and the consequence is the scheduler spamming notifications
        // once an hour of pruning has run. Refuse rather than discover it later.
        if ($exempt === []) {
            $this->error('config/audit.php has an empty never_prune list.');
            $this->line('  At minimum it must contain auto_checkin_skipped_balance, which the');
            $this->line('  scheduler reads as state. See the comment in that file.');

            return self::FAILURE;
        }

        $this->line('  security tier : '.($securityDays > 0 ? "keep {$securityDays} days" : 'keep forever'));
        $this->line('  routine tier  : '.($routineDays > 0 ? "keep {$routineDays} days" : 'keep forever'));
        $this->line('  never pruned  : '.implode(', ', $exempt));

        if ($dryRun) {
            $this->warn('  DRY RUN — nothing will be deleted.');
        }

        $this->newLine();

        $deleted = 0;
        $deleted += $this->pruneTier('routine', $routineDays, $exempt, false, $chunk, $dryRun);
        $deleted += $this->pruneTier('security', $securityDays, $exempt, true, $chunk, $dryRun);

        $this->newLine();
        $this->info($dryRun
            ? "  {$deleted} rows are eligible for deletion."
            : "  {$deleted} rows deleted.");

        // Say what survived, so the output answers "is the audit trail still
        // there?" rather than only "how much did you throw away?".
        $this->line('  remaining     : '.StaffLog::count().' rows');

        return self::SUCCESS;
    }

    /**
     * @param  bool  $security  true prunes the security actions, false prunes everything else
     */
    private function pruneTier(
        string $label,
        int $days,
        array $exempt,
        bool $security,
        int $chunk,
        bool $dryRun,
    ): int {
        if ($days <= 0) {
            $this->line("  {$label}: retained forever, nothing to do");

            return 0;
        }

        $cutoff = now()->subDays($days);

        $query = fn () => StaffLog::query()
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('action', $exempt)
            ->when(
                $security,
                fn ($q) => $q->whereIn('action', StaffLog::SECURITY_ACTIONS),
                // The routine tier is "everything that is not a security action",
                // expressed as the complement rather than as its own list. A list
                // would silently stop covering any action added later, and the
                // failure mode would be rows quietly never expiring.
                fn ($q) => $q->whereNotIn('action', StaffLog::SECURITY_ACTIONS),
            );

        $eligible = $query()->count();

        if ($eligible === 0) {
            $this->line("  {$label}: nothing older than {$cutoff->toDateString()}");

            return 0;
        }

        if ($dryRun) {
            $this->line("  {$label}: {$eligible} rows older than {$cutoff->toDateString()} would go");

            return $eligible;
        }

        $deleted = 0;

        // Chunked by primary key, not offset: deleting as you page with an offset
        // shifts the window under you and skips rows.
        do {
            $ids = $query()->orderBy('id')->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += StaffLog::whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        $this->line("  {$label}: {$deleted} rows deleted (older than {$cutoff->toDateString()})");

        return $deleted;
    }
}
