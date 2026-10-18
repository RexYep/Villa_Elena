<?php

namespace App\Services;

use App\Events\PropertyAvailabilityChanged;
use App\Models\Booking;
use App\Models\StaffLog;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use App\Support\AccountDeletionCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deletes a guest's account under RA 10173 (Data Privacy Act of 2012)
 * while keeping the records the business must keep.
 *
 * WHY NOT `DELETE FROM users`: `bookings.user_id` cascades, and so does
 * `payments.booking_id`. A real delete takes every booking, payment and
 * refund record of that guest with it — the proof of a transaction, and
 * every past figure in the reports. So the row stays and its personal
 * fields are cleared ("anonymised in place"). Nothing else in the schema
 * copies a guest's name into a booking, so clearing the row is enough to
 * detach every kept record from the person.
 *
 * WHAT IS KEPT AND WHY
 *   - bookings, payments, extras, refund_destinations, refund_transfers:
 *     financial records (and where refund money went). The right to
 *     erasure yields to a legal obligation to keep them.
 *   - reviews: the rating and text stay, shown under "Former Guest". The
 *     guest can delete a review themselves before deleting the account.
 *   - staff_logs: an audit trail the audited person can edit is not one;
 *     it is pruned on its own schedule (config/audit.php).
 *
 * WHAT IS REMOVED: name, email, phone, address, profile photo, password,
 * `special_requests` on their bookings (free text that can hold anything),
 * their own notifications, sign-in history, trusted devices, sessions and
 * any password-reset token.
 *
 * WHEN IT IS REFUSED — see problems(). An account with something still in
 * play (a stay not finished, money owed either way, a payment in progress)
 * cannot be deleted: the resort still needs to reach that guest, and the
 * guest still needs the account to see or settle it.
 */
class AccountErasure
{
    /** Who asked: the guest from their profile, or an admin on request. */
    public const BY_GUEST = 'guest';

    public const BY_ADMIN = 'admin';

    /**
     * Why this account cannot be deleted right now, one entry per reason,
     * each naming its booking. Empty when it can.
     *
     * Read on the profile page (so the guest sees why before typing a
     * password) and again inside erase()'s lock, so a booking made in
     * another tab meanwhile still counts.
     *
     * @return list<array{message: string, booking: ?Booking}>
     */
    public static function problems(User $user): array
    {
        if (! User::anonymizedColumnExists()) {
            return [['message' => 'Account deletion is not available right now. Please try again later.', 'booking' => null]];
        }

        if ($user->role !== 'customer') {
            return [['message' => 'Only guest accounts can be deleted this way.', 'booking' => null]];
        }

        $problems = [];
        $bookings = $user->bookings()
            ->with('payments.refundTransfers')
            ->orderBy('check_in_date')
            ->get();

        foreach ($bookings as $booking) {
            $ref = $booking->booking_ref;
            $stay = $booking->stay_phrase;

            // One reason per booking — the most pressing — so a paid,
            // upcoming stay is not listed three times.
            $message = match (true) {
                $booking->status === 'checked_in' => "Booking {$ref} is checked in now. The account can be deleted after check-out.",

                $booking->status === 'confirmed',
                $booking->status === 'pending' && (float) $booking->amount_paid > 0 => "Booking {$ref} ({$stay}) has not taken place yet. Cancel it first, or wait until the stay is over.",

                in_array($booking->refundStage(), ['owed', 'processing', 'failed'], true) => "A refund on booking {$ref} has not been paid out yet. The account can be deleted once you have received it.",

                $booking->isOverpaid() => "Booking {$ref} was paid more than its total, and the resort has not settled the difference yet. Please contact the resort.",

                $booking->status === 'checked_out' && (float) $booking->balance_due > 0 => "Booking {$ref} still has ₱".number_format((float) $booking->balance_due, 2).' unpaid. Please settle it first.',

                default => null,
            };

            if ($message) {
                $problems[] = ['message' => $message, 'booking' => $booking];
            }
        }

        // Without this, deleting and signing up again would be the way out
        // of the pause on new bookings after repeated unpaid holds.
        if ($endsAt = Booking::bookingCooldownEndsAt($user->id)) {
            $problems[] = [
                'message' => 'New bookings are paused on this account after several unpaid holds. The account can be deleted after '
                    .$endsAt->format('M j, g:i A').'.',
                'booking' => null,
            ];
        }

        return $problems;
    }

    /**
     * Unpaid holds that deleting the account cancels. Not a reason to refuse:
     * nothing is paid, and the guest would lose the hold in an hour anyway.
     */
    public static function holdsToCancel(User $user): Collection
    {
        return $user->bookings()
            ->where('status', 'pending')
            ->where('amount_paid', '<=', 0)
            ->get();
    }

    /**
     * Deletes the account. Returns the problems that stopped it — empty
     * means it is done.
     *
     * @return list<array{message: string, booking: ?Booking}>
     */
    public static function erase(User $user, string $by): array
    {
        if ($user->isAnonymized()) {
            return [];
        }

        // ── 1. Close every open checkout first, outside any transaction ──
        //
        // An HTTP call never sits inside a DB transaction here. And it has
        // to happen BEFORE the holds are cancelled: a QR the guest is
        // scanning right now would otherwise pay a booking whose owner the
        // resort can no longer contact. 'open' means PayMongo refused — it
        // is paid or being paid. 'failed' means PayMongo could not be asked,
        // so the QR may still be live; both stop the deletion.
        foreach (static::holdsToCancel($user) as $hold) {
            if (! $hold->paymongo_session_id) {
                continue;
            }

            if ($hold->closeCheckoutSession() !== 'closed') {
                return [[
                    'message' => "A payment for booking {$hold->booking_ref} may be in progress. Please wait a few minutes and try again.",
                    'booking' => $hold,
                ]];
            }
        }

        $erased = false;
        $oldEmail = null;
        $firstName = '';
        $oldPhoto = null;
        $cancelled = collect();

        $problems = DB::transaction(function () use ($user, &$erased, &$oldEmail, &$firstName, &$oldPhoto, &$cancelled) {
            // The lock serialises two deletes of one account (a double
            // click); the second finds anonymized_at set and stops.
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if (! $locked || $locked->isAnonymized()) {
                return [];
            }

            // Asked again under the lock: a booking made or paid since the
            // page was rendered counts.
            if ($problems = static::problems($locked)) {
                return $problems;
            }

            $oldEmail = $locked->email;
            $firstName = $locked->first_name;
            $oldPhoto = $locked->profile_image;

            foreach (static::holdsToCancel($locked) as $hold) {
                // Eloquent, not a query-builder update: the model's hooks
                // free `slot_hold`, refresh the availability grid and the
                // dashboard, and close a checkout session opened since step 1.
                $hold->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Cancelled because the guest deleted their account.',
                    'cancelled_by' => 'guest',
                    'balance_due' => 0,
                ]);
                $cancelled->push($hold);
            }

            // Free text the guest wrote ("my wife is allergic to…"). Not a
            // financial record, and nothing reports on it.
            Booking::withTrashed()
                ->where('user_id', $locked->id)
                ->whereNotNull('special_requests')
                ->update(['special_requests' => null]);

            DB::table('notifications')->where('user_id', $locked->id)->delete();
            DB::table('login_activities')->where('user_id', $locked->id)->delete();

            // Every session and every trusted device.
            $locked->revokeOtherLogins(null);

            if ($oldEmail) {
                DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                    ->where('email', $oldEmail)
                    ->delete();
            }

            // email NULL, not a made-up address: the address is free to sign
            // up again, the unique index allows any number of NULLs, and every
            // mail path already skips a guest with no email (walk-in records
            // have none). A fake address would be mailed, and bounce.
            $locked->forceFill([
                'full_name' => User::ANONYMIZED_NAME,
                'email' => null,
                'phone' => null,
                'address' => null,
                'profile_image' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => Str::random(60),
                'status' => 0,
                'two_factor_enabled' => false,
                'email_notifications_enabled' => false,
                'email_verified_at' => null,
                'anonymized_at' => now(),
            ])->save();

            $erased = true;

            return [];
        });

        if (! $erased) {
            return $problems;
        }

        // After the commit, like every audit write (the project's rule: an
        // entry inside a transaction widens the lock and can be rolled back).
        // The user id only — writing the email here would keep the very thing
        // that was just erased in a table kept for a year.
        StaffLog::record('account_erased', 'users', $user->id,
            ($by === self::BY_ADMIN ? 'Guest account deleted by an admin at the guest\'s request' : 'Guest account deleted by the account holder')
            ." (user #{$user->id}); personal details cleared, booking and payment records kept"
            .($cancelled->isNotEmpty() ? '; unpaid hold(s) cancelled: '.$cancelled->pluck('booking_ref')->implode(', ') : ''));

        $user->refresh();
        AccountDeletionCode::forget($user);

        // ── 2. After the commit: the parts that leave the database ──────

        if ($oldPhoto) {
            try {
                Storage::disk('public')->delete($oldPhoto);
            } catch (\Throwable $e) {
                Log::warning("Could not delete the profile photo of erased user #{$user->id}: ".$e->getMessage());
            }
        }

        foreach ($cancelled as $hold) {
            try {
                event(new PropertyAvailabilityChanged(
                    $hold->property_id,
                    'freed',
                    $hold->check_in_date->format('Y-m-d'),
                    $hold->check_out_date->format('Y-m-d'),
                    bookingId: $hold->id,
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to broadcast PropertyAvailabilityChanged (account erased): '.$e->getMessage());
            }
        }

        // The last email to that address: it tells the owner what happened,
        // and if it was not them, that is how they find out.
        if ($oldEmail) {
            defer(function () use ($oldEmail, $firstName, $by) {
                try {
                    Notification::route('mail', $oldEmail)
                        ->notify(new AccountDeletedNotification($firstName, $by === self::BY_ADMIN));
                } catch (\Throwable $e) {
                    Log::error('Failed to send the account-deleted email: '.$e->getMessage());
                }
            });
        }

        return [];
    }
}
