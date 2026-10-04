<?php

namespace App\Console\Commands;

use App\Models\StaffLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Delete self-registered accounts that were never verified.
 *
 * Registration creates the `users` row before the address is proven, so an
 * address typed by a stranger (or by a bot) leaves an account behind for good.
 * It cannot be verified by the wrong person — verifyEmail() needs the password
 * — and the real owner can always take the address back with a password reset,
 * so this is housekeeping, not the barrier. What it buys is that a squatted
 * address frees itself, and the users table does not fill with dead rows.
 *
 * WHAT IS NEVER DELETED, and why each test is here:
 *
 *  - anything but `role = customer`;
 *  - a row with no email — that is a walk-in "guest record", not a sign-up;
 *  - anyone with a booking, including a cancelled or soft-deleted one. Every
 *    walk-in account is created together with its booking, so this is what
 *    keeps staff-created guests out;
 *  - an account an admin created. Nothing on the row says so; the only trace
 *    is the `created_user` audit entry, which is kept 365 days
 *    (config/audit.php). That is why the window below has an UPPER bound: an
 *    account older than MAX_AGE_DAYS is left alone, so this never decides
 *    about a row whose audit entry could already have been pruned.
 */
class PruneUnverifiedUsers extends Command
{
    private const MAX_AGE_DAYS = 30;

    protected $signature = 'users:prune-unverified
                            {--hours=48 : Minimum age of an unverified account before it is deleted}
                            {--dry-run : Report what would be deleted and delete nothing}';

    protected $description = 'Delete self-registered customer accounts that never verified their email';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $users = User::query()
            ->where('role', 'customer')
            ->whereNotNull('email')
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHours($hours))
            ->where('created_at', '>', now()->subDays(self::MAX_AGE_DAYS))
            ->whereDoesntHave('bookings', fn ($q) => $q->withTrashed())
            ->whereNotIn('id', StaffLog::query()
                ->where('action', 'created_user')
                ->where('target_table', 'users')
                ->whereNotNull('target_id')
                ->select('target_id'))
            ->orderBy('id')
            ->get(['id', 'email', 'created_at']);

        if ($users->isEmpty()) {
            $this->line('  No unverified accounts to remove.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("  DRY RUN — {$users->count()} account(s) would be deleted:");
            $users->each(fn ($u) => $this->line("    #{$u->id} created {$u->created_at}"));

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($users as $user) {
            try {
                // Eloquent delete, one at a time: the model's `deleted` hook
                // refreshes the dashboard's guest count.
                $user->delete();
                $deleted++;
            } catch (\Throwable $e) {
                Log::error("Could not prune unverified user #{$user->id}: ".$e->getMessage());
            }
        }

        StaffLog::record('pruned_unverified_users', 'users', null,
            "Deleted {$deleted} self-registered account(s) left unverified for more than {$hours} hours");

        $this->info("  {$deleted} unverified account(s) deleted.");

        return self::SUCCESS;
    }
}
