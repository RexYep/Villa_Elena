<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The emailed code a guest with two-factor login on must enter to delete
 * their account.
 *
 * Deleting is the one action on the profile page that cannot be undone, and
 * the password alone proves only that the person at the keyboard knows it.
 * For a guest who chose two-factor login, the code proves the inbox too —
 * the same thing a sign-in on a new device asks of them.
 *
 * Kept apart from the sign-in code (AuthController's `2fa_otp_{id}`): a code
 * mailed for signing in must not be able to delete the account, and the
 * other way round.
 */
class AccountDeletionCode
{
    public const TTL_MINUTES = 10;

    /** Wrong guesses allowed against one code before it is cancelled. */
    public const MAX_ATTEMPTS = 5;

    /** Codes that may be mailed per account per hour. */
    public const MAX_ISSUED_PER_HOUR = 3;

    /**
     * Same store as the sign-in code and the rate limiter, for the same
     * reason: the guess counter is security state, and the default store in
     * production is a non-persistent Redis that can drop it mid-attack.
     */
    private static function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('cache.limiter'));
    }

    private static function codeKey(User $user): string
    {
        return "account-delete-otp:{$user->id}";
    }

    private static function attemptsKey(User $user): string
    {
        return "account-delete-otp-attempts:{$user->id}";
    }

    private static function issueKey(User $user): string
    {
        return "account-delete-otp-issue:{$user->id}";
    }

    /** Why a code may not be mailed right now, or null when it may. */
    public static function issueProblem(User $user): ?string
    {
        $key = self::issueKey($user);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ISSUED_PER_HOUR)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return "Too many codes were requested. Please try again in {$minutes} "
                .($minutes === 1 ? 'minute' : 'minutes').'.';
        }

        return null;
    }

    /** Stores a fresh code (only its hash) and returns it for the email. */
    public static function issue(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        self::store()->put(self::codeKey($user), Hash::make($code), now()->addMinutes(self::TTL_MINUTES));
        self::store()->forget(self::attemptsKey($user));
        RateLimiter::hit(self::issueKey($user), 3600);

        return $code;
    }

    /**
     * Null when the code is right; otherwise the message for the form.
     * A right code is used up, so it cannot be submitted twice.
     */
    public static function check(User $user, ?string $code): ?string
    {
        $hashed = self::store()->get(self::codeKey($user));

        if (! $hashed) {
            return 'This code has expired or was not requested. Send a new code to your email.';
        }

        if (! $code || ! Hash::check($code, $hashed)) {
            self::store()->add(self::attemptsKey($user), 0, now()->addMinutes(self::TTL_MINUTES));

            if (self::store()->increment(self::attemptsKey($user)) >= self::MAX_ATTEMPTS) {
                self::forget($user);

                return 'Too many incorrect codes. This code has been cancelled — send a new one.';
            }

            return 'The code is incorrect.';
        }

        self::forget($user);

        return null;
    }

    public static function forget(User $user): void
    {
        self::store()->forget(self::codeKey($user));
        self::store()->forget(self::attemptsKey($user));
    }
}
