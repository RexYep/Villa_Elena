<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\StaffLog;
use App\Models\TrustedDevice;
use App\Models\LoginActivity;
use App\Helpers\DeviceHelper;
use App\Mail\RegistrationAttemptMail;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Helpers\NotificationHelper;

class AuthController extends Controller
{
    private const TRUSTED_DEVICE_COOKIE = 'trusted_device';
    private const TRUSTED_DEVICE_DAYS   = 60;
    private const OTP_TTL_MINUTES       = 10;
    // Wrong guesses allowed against ONE code before it is cancelled. The
    // route throttle is per network only, so without this a 6-digit code
    // could be guessed from many IPs inside its 10-minute life.
    private const OTP_MAX_ATTEMPTS      = 5;
    // Per ACCOUNT, across every code and every IP. A correct password issues a
    // fresh code with a fresh five guesses, so without these two budgets the
    // per-code limit above could be reset by simply logging in again.
    private const OTP_MAX_ISSUED_PER_HOUR = 6;  // login-issued and resent codes together
    private const OTP_MAX_ISSUED_PER_DAY  = 12;
    private const OTP_MAX_WRONG_PER_HOUR  = 15; // wrong guesses, whichever code they were against
    private const PENDING_VERIFICATION_URL = 'pending_verification_url'; // session key
    private const REGISTER_MIN_SECONDS = 3;    // faster than this from render to submit is a script
    private const LOGIN_MAX_FAILURES   = 5;   // per email + IP, per minute
    private const LOGIN_MAX_FAILURES_PER_ACCOUNT = 15; // per email, any IP, per 15 minutes

    /**
     * A throwaway hash to check against when the email doesn't exist, so that
     * both answers cost the same. Nothing hashes to it; it is a stopwatch, not
     * a credential, and it never leaves this process.
     *
     * Built at runtime rather than pasted in as a constant ON PURPOSE. A
     * hardcoded `$2y$12$…` matches production but NOT any environment that
     * configures a different cost — phpunit.xml sets BCRYPT_ROUNDS=4, where a
     * cost-12 equaliser made the unknown-email branch ~319ms against a real
     * login's ~7ms. That does not close the oracle, it inverts it, and the
     * inverted version is just as readable. Generating it through Hash::make()
     * means it always carries whatever `hashing.bcrypt.rounds` currently is.
     *
     * Cost of doing it this way: one extra Hash::make per PHP process, paid by
     * the first unknown-email login that worker sees.
     */
    private static ?string $timingEqualiser = null;

    // ── Show Login Page ────────────────────────────────────────────
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }
        return view('auth.login');
    }

    // ── Handle Login ───────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        // Check credentials first WITHOUT revealing whether the email
        // exists or the password was wrong — a single generic message
        // for both prevents user enumeration (attackers probing which
        // emails are registered).
        // Failed-login lockout. Only FAILURES count — the old route-level
        // `throttle:5,1` also counted successful logins, per IP only, so a
        // household or resort Wi-Fi shared five logins a minute while one
        // attacker rotating IPs could keep guessing a single account.
        // Two keys: email+IP (Laravel Breeze's rule) catches one guesser,
        // email alone catches a guesser spread across many IPs.
        $email       = strtolower(trim($request->email));
        $lockoutKeys = [
            "login:{$email}|{$request->ip()}" => [self::LOGIN_MAX_FAILURES, 60],
            "login:{$email}"                  => [self::LOGIN_MAX_FAILURES_PER_ACCOUNT, 15 * 60],
        ];

        foreach ($lockoutKeys as $key => [$maxFailures]) {
            if (RateLimiter::tooManyAttempts($key, $maxFailures)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

                return back()
                    ->withErrors(['email' => "Too many failed login attempts. Please try again in {$minutes} " . Str::plural('minute', $minutes) . '.'])
                    ->withInput($request->only('email', 'remember'));
            }
        }

        // A FAILED LOGIN USED TO LEAVE NO TRACE ANYWHERE. Not in staff_logs,
        // not in login_activities, not even a Log::warning — so the table
        // held 465 `user_login` rows and nothing whatsoever about the
        // attempts that did not succeed. A password-spraying run against an
        // admin account was, by construction, invisible.
        //
        // What gets recorded is the attempted address and WHICH wall was hit.
        // That is not an enumeration leak: it goes to an admin-only page and
        // never to the browser, and the response below stays byte-identical
        // in both branches — the same generic "Invalid email or password."
        // that the timing equaliser further down exists to protect.
        //
        // Both callers route through here, so the extra INSERT is paid
        // identically whether or not the address exists. Moving it into only
        // one branch would reopen the timing oracle the equaliser closed.
        $failed = function (?User $user, string $reason) use ($request, $email, $lockoutKeys) {
            foreach ($lockoutKeys as $key => [$maxFailures, $decaySeconds]) {
                RateLimiter::hit($key, $decaySeconds);

                // `=== $maxFailures`, not `>=`: exactly one row at the moment
                // the wall goes up. The early return at the top of login()
                // means every LATER attempt in the same window never reaches
                // this closure at all, so an attacker cannot pump the audit
                // log — the volume is bounded by the lockout budget (5/min
                // per email+IP, 15 per 15min per email), not by the 30/min
                // route throttle.
                if (RateLimiter::attempts($key) === $maxFailures) {
                    StaffLog::record('login_lockout', 'users', $user?->id,
                        "Login locked out for {$email} after {$maxFailures} failed attempts (key: {$key})");
                }
            }

            StaffLog::record('login_failed', 'users', $user?->id,
                "Failed login for {$email} — {$reason}");

            // Task 12 F5. The audit rows above are good and are kept — the gap
            // was that nobody is TOLD, and that nothing correlates across
            // accounts.
            //
            // Two different questions, so two different signals:
            //
            //  1. Burst — one IP failing a lot. Bounded already by the lockout
            //     budget, so the threshold is low.
            //  2. SPRAY — one IP failing against MANY DIFFERENT accounts. This
            //     is the one no existing guard sees: lockout is per email+IP and
            //     per email, and the route limit is 30/min per IP, so one guess
            //     ("Summer2026!") tried against fifty addresses trips NOTHING.
            //     Each account has one failure; the IP stays under 30/min.
            //     countDistinct() is what makes that visible.
            //
            // The email is hashed into the distinct-counter rather than stored:
            // the tally only needs to know "a different account", and the
            // addresses themselves are already in staff_logs above, once each.
            \App\Services\SecurityMonitor::recordAndEscalate(
                event: \App\Services\SecurityMonitor::LOGIN_FAILED_BURST,
                bucket: 'ip:'.$request->ip(),
                summary: "Failed login from {$request->ip()} — {$reason}",
                threshold: 10,
                title: 'Repeated failed sign-ins',
                message: 'Ten or more sign-in attempts from '.$request->ip().' have failed in'
                    .' the last hour. Individual accounts lock themselves after five, so this'
                    .' is one source trying repeatedly. The Audit Log sign-ins view lists them.',
                context: ['ip' => $request->ip(), 'reason' => $reason, 'user_id' => $user?->id],
                link: route('admin.audit.signins', [], false),
            );

            $distinctAccounts = \App\Services\SecurityMonitor::countDistinct(
                \App\Services\SecurityMonitor::CREDENTIAL_SPRAY,
                'ip:'.$request->ip(),
                mb_strtolower($email),
            );

            \App\Services\SecurityMonitor::escalate(
                event: \App\Services\SecurityMonitor::CREDENTIAL_SPRAY,
                count: $distinctAccounts,
                threshold: 5,
                title: 'One source is trying many different accounts',
                message: $request->ip().' has failed to sign in to '.$distinctAccounts
                    .' DIFFERENT accounts within the last hour. That pattern is credential'
                    .' stuffing or password spraying, and it deliberately stays under the'
                    .' per-account lockout by trying each address only once or twice.',
                link: route('admin.audit.signins', [], false),
            );

            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput($request->only('email', 'remember'));
        };

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Burn the same time a real check costs. Without this, a missing
            // account skipped Hash::check() entirely and answered in ~0ms
            // while a real one took ~310ms (measured, bcrypt cost 12) — a
            // 300ms gap that is trivially readable over the network. The
            // generic "Invalid email or password." above was then decoration:
            // the clock answered the question the wording refuses to.
            self::$timingEqualiser ??= Hash::make('no-such-account-' . Str::random(16));
            Hash::check($request->password, self::$timingEqualiser);

            return $failed(null, 'no account with that address');
        }

        if (!Hash::check($request->password, $user->password)) {
            return $failed($user, 'wrong password');
        }

        // The password was right: whatever happens next, it isn't guessing.
        foreach (array_keys($lockoutKeys) as $key) {
            RateLimiter::clear($key);
        }

        // Check if account is active
        if (!$user->isActive()) {
            // Its own action name, and deliberately NOT routed through
            // $failed(): the password was CORRECT. Someone holding working
            // credentials for a disabled account is a different event from a
            // guess, and the lockout counters were just cleared on purpose.
            StaffLog::record('login_rejected_inactive', 'users', $user->id,
                "Correct password accepted for deactivated account {$email} — login refused");

            return back()->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.'])->withInput($request->only('email', 'remember'));
        }

        // 2FA is opt-in (toggled in the customer profile) and only bites on
        // a device we haven't seen before — a matching, unexpired
        // trusted_devices row skips straight to a normal login.
        if ($user->two_factor_enabled && !$this->isTrustedDevice($user, $request)) {
            if ($problem = $this->twoFactorIssueProblem($user)) {
                return back()->withErrors(['email' => $problem])->withInput($request->only('email', 'remember'));
            }

            $code = $this->issueTwoFactorCode($user);

            try {
                $user->notify(new TwoFactorCodeNotification($code));
            } catch (\Exception $e) {
                Log::error('Failed to send 2FA code: ' . $e->getMessage());
                return back()->withErrors(['email' => 'The verification code cannot be sent right now. Please try again later.'])->withInput($request->only('email', 'remember'));
            }

            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $remember);

            return redirect()->route('two-factor.verify');
        }

        if (Auth::attempt($credentials, $remember)) {
            $this->completeLogin($user, trustDevice: false);
            return $this->afterLoginRedirect($user);
        }

        // Hash::check() already passed above, so arriving here means the
        // guard refused a credential pair we just verified by hand — a
        // provider or config problem, not a guess. Given its own reason
        // string so it is never mistaken for one in the log.
        return $failed($user, 'guard refused an otherwise valid credential pair');
    }

    // ── Two-Factor: Show Verification Page ─────────────────────────
    public function showTwoFactor(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('2fa_user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $maskedEmail = preg_replace('/^(.).*(@.*)$/', '$1***$2', $user->email);

        return view('auth.two_factor', compact('maskedEmail'));
    }

    // ── Two-Factor: Verify Code ─────────────────────────────────────
    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $userId = $request->session()->get('2fa_user_id');
        $user   = $userId ? User::find($userId) : null;

        if (!$user) {
            return redirect()->route('login');
        }

        $wrongKey = $this->twoFactorWrongKey($user);

        // Checked before the code is even read: while the account is locked a
        // correct guess must not get in either.
        if (RateLimiter::tooManyAttempts($wrongKey, self::OTP_MAX_WRONG_PER_HOUR)) {
            return back()->withErrors(['code' => 'Too many incorrect codes. Please try again in '
                .$this->waitText(RateLimiter::availableIn($wrongKey)).'.']);
        }

        $hashedCode = $this->otpStore()->get("2fa_otp_{$user->id}");

        if (!$hashedCode || !Hash::check($request->code, $hashedCode)) {
            // Only a guess against a live code counts; submitting an expired
            // code is not guessing and must not lock the owner out.
            if ($hashedCode && RateLimiter::hit($wrongKey, 3600) >= self::OTP_MAX_WRONG_PER_HOUR) {
                $this->otpStore()->forget("2fa_otp_{$user->id}");

                StaffLog::record('two_factor_locked', 'users', $user->id,
                    "2FA verification locked for {$user->email} after ".self::OTP_MAX_WRONG_PER_HOUR.' incorrect codes within an hour');

                return back()->withErrors(['code' => 'Too many incorrect codes. Please try again in '
                    .$this->waitText(RateLimiter::availableIn($wrongKey)).'.']);
            }

            if ($hashedCode && $this->otpAttemptsExhausted($user)) {
                $this->otpStore()->forget("2fa_otp_{$user->id}");

                // The second factor being ground down is exactly the signal a
                // real account owner needs, and it was previously kept
                // nowhere: the counter lives in the cache and the
                // cancellation was visible only to whoever was guessing.
                StaffLog::record('two_factor_exhausted', 'users', $user->id,
                    "2FA code cancelled for {$user->email} after ".self::OTP_MAX_ATTEMPTS.' incorrect attempts');

                return back()->withErrors(['code' => 'Too many incorrect codes. This code has been cancelled — please request a new one.']);
            }

            StaffLog::record('two_factor_failed', 'users', $user->id,
                $hashedCode
                    ? "Incorrect 2FA code for {$user->email}"
                    : "2FA code submitted for {$user->email} with no live code outstanding (expired or already used)");

            return back()->withErrors(['code' => 'Invalid or expired code. Please try again.']);
        }

        $this->otpStore()->forget("2fa_otp_{$user->id}");
        $this->otpStore()->forget("2fa_attempts_{$user->id}");
        $remember = $request->session()->pull('2fa_remember', false);
        $request->session()->forget('2fa_user_id');

        Auth::login($user, $remember);
        $this->completeLogin($user, trustDevice: true);

        return $this->afterLoginRedirect($user)->with('success', 'Device verified! Welcome back.');
    }

    // ── Two-Factor: Resend Code ──────────────────────────────────────
    public function resendTwoFactor(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');
        $user   = $userId ? User::find($userId) : null;

        if (!$user) {
            return redirect()->route('login');
        }

        if ($problem = $this->twoFactorIssueProblem($user)) {
            return back()->with('error', $problem);
        }

        $code = $this->issueTwoFactorCode($user);

        try {
            $user->notify(new TwoFactorCodeNotification($code));
        } catch (\Exception $e) {
            Log::error('Failed to resend 2FA code: ' . $e->getMessage());
            return back()->with('error', 'The verification code cannot be sent right now. Please try again later.');
        }

        return back()->with('success', 'A new code has been sent to your email.');
    }

    /**
     * The store the 2FA code and its guess counter live in.
     *
     * NOT the default store. In production that is `failover`
     * (`['redis', 'database']`) on the free, non-persistent Render Key Value
     * plan, and these two keys are security state, not a cache: the counter is
     * the only thing standing between a 6-digit code and unlimited guesses
     * (the route throttle is per network). On the default store a Redis
     * restart, an `allkeys-lru` eviction, or a mid-attack failover drops the
     * counter while the code stays valid, handing out a fresh five guesses.
     *
     * Pinned to the same store as the rate limiter, for the same reason —
     * see `cache.limiter` in config/cache.php.
     */
    private function otpStore(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('cache.limiter'));
    }

    // ── Two-Factor: Store A Fresh Code, Resetting The Guess Counter ─
    private function issueTwoFactorCode(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        $this->otpStore()->put("2fa_otp_{$user->id}", Hash::make($code), now()->addMinutes(self::OTP_TTL_MINUTES));
        $this->otpStore()->forget("2fa_attempts_{$user->id}");

        foreach ($this->twoFactorIssueLimits($user) as $key => [, $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }

        return $code;
    }

    // ── Two-Factor: Per-Account Budgets For Issuing A Code ──────────
    private function twoFactorIssueLimits(User $user): array
    {
        return [
            "2fa-issue:h:{$user->id}" => [self::OTP_MAX_ISSUED_PER_HOUR, 3600],
            "2fa-issue:d:{$user->id}" => [self::OTP_MAX_ISSUED_PER_DAY, 86400],
        ];
    }

    private function twoFactorWrongKey(User $user): string
    {
        return "2fa-wrong:{$user->id}";
    }

    /**
     * Why a new code may not be issued right now, or null when it may.
     *
     * Both issuing paths (login and resend) must ask this first. The login
     * path used to be bounded only by the 30/min route throttle, so anyone
     * holding the password could mail the owner a code on every request and
     * collect five fresh guesses each time.
     */
    private function twoFactorIssueProblem(User $user): ?string
    {
        $wrongKey = $this->twoFactorWrongKey($user);

        if (RateLimiter::tooManyAttempts($wrongKey, self::OTP_MAX_WRONG_PER_HOUR)) {
            return 'Too many incorrect verification codes were entered for this account. Please try again in '
                .$this->waitText(RateLimiter::availableIn($wrongKey)).'.';
        }

        foreach ($this->twoFactorIssueLimits($user) as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return 'Too many verification codes were requested for this account. Please try again in '
                    .$this->waitText(RateLimiter::availableIn($key)).'.';
            }
        }

        return null;
    }

    private function waitText(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' '.Str::plural('second', $seconds);
        }

        if ($seconds < 3600) {
            $minutes = (int) ceil($seconds / 60);

            return $minutes.' '.Str::plural('minute', $minutes);
        }

        $hours = (int) ceil($seconds / 3600);

        return $hours.' '.Str::plural('hour', $hours);
    }

    // ── Two-Factor: Count A Wrong Guess; True Once The Code Is Burnt ─
    private function otpAttemptsExhausted(User $user): bool
    {
        $key = "2fa_attempts_{$user->id}";

        $this->otpStore()->add($key, 0, now()->addMinutes(self::OTP_TTL_MINUTES));

        return $this->otpStore()->increment($key) >= self::OTP_MAX_ATTEMPTS;
    }

    // ── Check If The Current Browser Is A Trusted Device ────────────
    private function isTrustedDevice(User $user, Request $request): bool
    {
        $token = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if (!$token) {
            return false;
        }

        // The cookie carries the raw token; the table stores only its SHA-256.
        // `activeForToken()` does the hashing and the expiry check together —
        // never look this up by the raw value, there is nothing to match.
        return TrustedDevice::query()
            ->where('user_id', $user->id)
            ->activeForToken($token)
            ->exists();
    }

    // ── Finish A Login: Session, last_login, Audit Trail, History ──
    private function completeLogin(User $user, bool $trustDevice): void
    {
        request()->session()->regenerate();

        $user->update(['last_login' => now()]);

        // F11 — a `user_login` StaffLog row used to be written HERE, one line
        // above the LoginActivity row below. They recorded the same event, in
        // the same second, with the same IP:
        //
        //   staff_logs  : user=2  2026-09-24 18:37:20  ip=172.18.0.1
        //   login_act.  : user=2  2026-09-24 18:37:20  ip=172.18.0.1
        //                 device="Chrome on Windows"  via_new_device_otp=0
        //
        // The LoginActivity row is strictly richer — it also carries the
        // device label and whether the sign-in came through an emailed code —
        // and it is the one the guest already sees in My Account. The
        // staff_logs copy was the poorer of two records of one event, and it
        // was 465 of the table's 1,196 rows, burying everything worth reading.
        //
        // NOTHING IS LOST BY REMOVING IT. Successful sign-ins are read at
        // /admin/audit-log/sign-ins, which lists `login_activities` with the
        // same actor and date filters — so the admin's single reading surface
        // now shows MORE about a login than it did before, not less.
        //
        // `user_logout` is deliberately still recorded in staff_logs: nothing
        // duplicates it, and login_activities has no concept of a logout.
        $deviceLabel = DeviceHelper::label(request()->userAgent());

        LoginActivity::create([
            'user_id'            => $user->id,
            'ip_address'         => request()->ip(),
            'device_label'       => $deviceLabel,
            'via_new_device_otp' => $trustDevice,
        ]);

        if ($trustDevice) {
            // The raw token exists only here and in the browser's cookie. What
            // goes in the database is its hash, so a read of `trusted_devices`
            // yields nothing anyone can present — the same reasoning that keeps
            // `users.password` hashed. See the TrustedDevice model.
            $token = Str::random(64);

            TrustedDevice::create([
                'user_id'      => $user->id,
                'token_hash'   => TrustedDevice::hashToken($token),
                'device_label' => $deviceLabel,
                'ip_address'   => request()->ip(),
                'last_used_at' => now(),
                'expires_at'   => now()->addDays(self::TRUSTED_DEVICE_DAYS),
            ]);

            Cookie::queue(self::TRUSTED_DEVICE_COOKIE, $token, 60 * 24 * self::TRUSTED_DEVICE_DAYS);
        }
    }

    // ── Show Register Page ─────────────────────────────────────────
    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }
        return view('auth.register');
    }

    // ── Handle Register ────────────────────────────────────────────
    public function register(Request $request)
    {
        // `unique:users,email` used to be here, with the message "This email
        // is already registered." That is ACCOUNT ENUMERATION: anyone could
        // type an address into the public sign-up form and be told in plain
        // words whether that person has an account here.
        //
        // It mattered because of what it undid. sendResetLink() answers
        // identically whether or not the address exists, and login() gives one
        // message for both "no such account" and "wrong password" — both
        // deliberately, so a list of addresses cannot be sifted for real ones.
        // Registration was the last door still answering honestly, and two
        // closed doors are worth little beside a third that is open.
        //
        // The uniqueness itself has not gone anywhere: `users.email` carries a
        // UNIQUE index, and the duplicate branch below is what enforces it at
        // this layer.
        if ($botResponse = $this->registrationBotResponse($request)) {
            return $botResponse;
        }

        $request->validate([
            // Letters (any script), spaces and . ' - only. A name is echoed
            // into the admins' "New Guest Registered" notification, so a free
            // text field here was a way to post links at them.
            'full_name'             => ['required', 'string', 'min:2', 'max:150', "regex:/^[\\pL\\pM][\\pL\\pM .'’-]*$/u"],
            'email'                 => 'required|email|max:100',
            'phone'                 => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 -]{6,18}$/'],
            'password'              => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'password_confirmation' => 'required',
        ], [
            'full_name.regex'    => 'Please enter your name using letters only.',
            'phone.regex'        => 'Please enter a valid phone number, for example 0917-123-4567.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        // Hashed BEFORE the branch, and used by only one of them.
        //
        // Ang bcrypt sa produksyon ay cost 12 — daan-daang milisegundo. Kung
        // ang sangay lang ng bagong account ang magbabayad niyon, ang ORAS ng
        // pagsagot ang magsasabi kung umiiral ang address, kahit magkapareho
        // ang mga salita. Ganoon din ang natuklasan sa login (v7.25); ito ang
        // parehong lunas, mas mura pa nga — walang throwaway na hash, gamitin
        // lang ang tunay na trabaho sa magkabilang panig.
        $hashedPassword = Hash::make($request->password);

        $existing = User::where('email', $request->email)->first();

        // Everything slow — mail, and the Pusher call behind the admin
        // notification — runs in defer(), after the response has gone. The
        // duplicate notice is capped per address (see below), so a capped
        // attempt sends nothing; done inline, that made the taken branch fast
        // on the second try and the clock gave the answer the words withhold.
        if ($existing) {
            defer(fn () => $this->announceDuplicateRegistration($existing));
        } else {
            try {
                $user = User::create([
                    'full_name' => $request->full_name,
                    'email'     => $request->email,
                    'phone'     => $request->phone,
                    'password'  => $hashedPassword,
                    'role'      => 'customer',
                    'status'    => 1,
                ]);

                defer(function () use ($user) {
                    try {
                        NotificationHelper::newGuestRegistered($user);
                    } catch (\Throwable $e) {
                        Log::error('Failed to notify admins of a new registration: ' . $e->getMessage());
                    }

                    // Sends Laravel's built-in VerifyEmail notification. A
                    // failure here is not flagged to the page: the answer is
                    // the same either way, and the guest can ask for another
                    // link from the verification notice once they sign in.
                    try {
                        $user->sendEmailVerificationNotification();
                    } catch (\Throwable $e) {
                        Log::error('Failed to send verification email: ' . $e->getMessage());
                    }
                });
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Dalawang sabay na pagpaparehistro sa iisang address. Nauna
                // ang isa; ang natalo ay tinatrato na parang nakita niya ang
                // account mula sa umpisa — iyon naman talaga ang totoo
                // ngayon.
                $duplicate = User::where('email', $request->email)->first();

                if ($duplicate) {
                    defer(fn () => $this->announceDuplicateRegistration($duplicate));
                }
            }
        }

        // ── IISANG SAGOT, ANUMAN ANG NANGYARI SA ITAAS ─────────────
        //
        // Dating naka-Auth::login() dito at dumederetso sa
        // verification.notice. Hindi iyon mapapanatili: HINDI puwedeng
        // i-login ang may-ari ng isang umiiral nang account dahil lang may
        // nagtype ng address niya — kaya ang mismong pagkakaroon ng session
        // ang magiging sagot sa tanong na sinusubukan nating itago.
        //
        // Kaya walang awtomatikong login. Alam naman ng bagong guest ang
        // password na pinili niya; ang pag-sign-in ay isang hakbang, at
        // doroon sila sa verification notice kung saan may resend button —
        // ang parehong pahinang inaabot ng BAWAT hindi pa berified na login.
        return redirect()->route('register.pending')
            ->with('success', 'Almost there — check your email to finish setting up your account.');
    }

    /**
     * Ipinaaalam sa TUNAY na may-ari na may nagtangkang magparehistro gamit
     * ang address niya.
     *
     * Dito napupunta ang impormasyong dating ibinibigay ng form sa kahit
     * sino: sa inbox ng taong may karapatan dito, at wala nang iba.
     *
     * Hindi ito kailanman nagtatapon. Ang isang bigong SMTP ay hindi dapat
     * maging kakaibang sagot ng form — dahil ang pagkakaiba ng sagot ang
     * mismong butas na isinasara nito.
     */
    private function announceDuplicateRegistration(User $user): void
    {
        // Once per address per day, from any network. The route throttle is
        // per IP, so without this a handful of IPs could fill the owner's
        // inbox with this notice. Skipped silently: the form's answer does
        // not depend on whether it was sent.
        $key = 'register-notice:'.sha1(mb_strtolower((string) $user->email));

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return;
        }

        RateLimiter::hit($key, 86400);

        try {
            Mail::to($user->email)->send(new RegistrationAttemptMail(
                recipientName: $user->full_name,
                forgotPasswordUrl: route('password.request'),
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send duplicate-registration notice: ' . $e->getMessage());
        }
    }

    /**
     * Cheap bot checks, run before validation so a bot learns nothing from
     * the validation errors. Returns the response to send, or null for a human.
     *
     * 1. Honeypot — `fax_number` is in the form but hidden from people. A
     *    script that fills every input fills it. Answered with the SAME
     *    redirect a real sign-up gets, so the bot has nothing to adapt to.
     * 2. Fill time — `form_token` is the encrypted time the form was rendered.
     *    Nobody types a name, email, phone and two passwords in under
     *    REGISTER_MIN_SECONDS. This one answers with a visible message,
     *    because a real guest can trip it (a fast resubmit after a validation
     *    error) and must not be told an account was created when it wasn't.
     */
    private function registrationBotResponse(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if (filled($request->input('fax_number'))) {
            Log::warning('Registration honeypot filled.', ['ip' => $request->ip()]);

            return redirect()->route('register.pending')
                ->with('success', 'Almost there — check your email to finish setting up your account.');
        }

        try {
            $renderedAt = (int) decrypt((string) $request->input('form_token'));
        } catch (\Throwable $e) {
            $renderedAt = null;
        }

        if ($renderedAt === null || time() - $renderedAt < self::REGISTER_MIN_SECONDS) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'form_token', 'fax_number']))
                ->with('error', 'That was a little too quick. Please check your details and submit again.');
        }

        return null;
    }

    // ── "Check your email" — ang iisang sagot ng register() ────────
    /**
     * Sinasadyang WALANG `auth` at walang anumang detalye ng account. Ito ang
     * nakikita ng bagong guest at ng taong nagtype ng address na mayroon na
     * — magkapareho, kaya walang matutunan ang huli.
     */
    public function registerPending()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        return view('auth.register_pending');
    }

    // ── Logout ─────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        StaffLog::record('user_logout', 'users', Auth::id(), 'User logged out');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out successfully.');
    }

    // ── Show Forgot Password Page ──────────────────────────────────
    public function showForgotPassword()
    {
        return view('auth.forgot_password');
    }

    // ── Send Reset Link ────────────────────────────────────────────
    /**
     * The answer is the SAME whether or not the address is registered.
     *
     * It used to be "We could not find an account with that email address.",
     * which turns this form into a free membership check: type an address,
     * read the answer, learn whether that person has an account here. That is
     * the exact question login() goes out of its way not to answer — its
     * single "Invalid email or password." exists for this reason — and the
     * per-address cap on this route was written in the belief that it already
     * leaked nothing.
     *
     * The cost is that someone who mistypes their address is told a link was
     * sent. That is the accepted trade, and it is why the message says "If
     * that address is registered" rather than claiming an email went out.
     * A status other than "sent" still goes to the log, so the diagnostic
     * information is kept — just not handed to the browser.
     *
     * The wording was only half of it. The broker ran INSIDE the request, so
     * a registered address took as long as sending an email (seconds) while
     * an unknown one answered at once, and a mail outage produced its own
     * "cannot be sent right now" error that only a registered address could
     * ever reach. Both told the browser what the message refuses to. The
     * broker now runs after the response has been sent, so the request does
     * the same work — validation and nothing else — for every address.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $credentials = $request->only('email');

        defer(function () use ($credentials) {
            try {
                $status = Password::sendResetLink($credentials);
            } catch (\Throwable $e) {
                Log::error('Failed to send password reset link: ' . $e->getMessage());

                return;
            }

            if ($status !== Password::RESET_LINK_SENT) {
                Log::info('Password reset link not sent.', ['status' => $status]);
            }
        });

        return back()->with('success', 'If that address is registered, a password reset link is on its way. Please check your inbox and spam folder.');
    }

    // ── Show Reset Password Page ───────────────────────────────────
    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset_password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    // ── Handle Reset Password ──────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'password_confirmation' => 'required',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                    // The emailed reset link is proof of the inbox, and the
                    // person holding it has just chosen the password — the
                    // same two things verifyEmail() requires. This is also how
                    // the real owner takes back an address somebody else
                    // registered: reset, and the account is theirs, verified.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                // The reason this flow exists is "someone else is in my
                // account". Rotating the password and remember_token alone
                // left the intruder's SESSION row live for the rest of
                // SESSION_LIFETIME and their trusted-device cookie skipping
                // 2FA for up to 60 days — so the reset changed the lock and
                // left them inside. NULL: there is no current session to
                // spare here, the guest is not logged in.
                $user->revokeOtherLogins();

                // Nobody is authenticated here, so `user_id` on this row is
                // NULL and that is the honest answer — the actor is whoever
                // held the emailed token. The IP and user agent captured by
                // record() are the only identity available, which is exactly
                // why they are worth having on this particular event.
                StaffLog::record('password_reset_completed', 'users', $user->id,
                    "Password reset completed for {$user->email} — all other sessions and trusted devices revoked");

                // The audit row above is for admins. The account holder is the
                // one who needs to know if this was not them, and they would
                // otherwise find out only when their own login stops working.
                try {
                    NotificationHelper::notifyGuest(
                        $user->id,
                        'Your Password Was Changed',
                        'Your password was reset on '.now()->format('M d, Y g:i A').' using the emailed reset link, and all other devices were signed out.'
                            .' If this was not you, reset your password again right away and contact the resort.'
                    );
                } catch (\Throwable $e) {
                    Log::error('Failed to notify user of password reset: ' . $e->getMessage());
                }
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset successful. You can now log in.');
        }

        // ONE message for every failure. The broker's own strings differ —
        // "We can't find a user with that email address." for an unknown
        // address, "This password reset token is invalid." for a known one —
        // so echoing `__($status)` let anyone submit a made-up token and read
        // off whether an address is registered. The real status is logged.
        Log::info('Password reset refused.', ['status' => $status]);

        return back()->withErrors(['email' => 'This password reset link is invalid or has expired. Please request a new one.']);
    }

    // ── Email Verification: Notice Page ────────────────────────────
    public function verifyNotice()
    {
        if (Auth::user()->hasVerifiedEmail()) {
            return $this->redirectByRole(Auth::user());
        }
        return view('auth.verify_email');
    }

    // ── Email Verification: Handle Link Click ──────────────────────
    // This route is intentionally outside the 'auth' middleware so a click
    // from Gmail without an active session gets a friendly trip through the
    // login page instead of losing the link. The signed URL is validated by
    // Laravel's 'signed' middleware; the link never signs anyone in.
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        // Make sure the hash matches this user's email
        if (!hash_equals(sha1($user->email), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        // THE LINK ALONE NO LONGER VERIFIES. It proves the clicker can read
        // the inbox; it does not prove they are the person who chose the
        // password. Anyone could register with a stranger's address and their
        // own password, and the stranger — receiving a genuine "verify your
        // account" email — would click it and hand over a verified account
        // the other person can log in to. Requiring a session for THIS account
        // means verified = knows the password AND reads the inbox: the
        // stranger lacks the first, whoever planted the account lacks the
        // second.
        if (! Auth::check()) {
            // Remembered server-side, so nothing a visitor supplies decides
            // where the login lands. afterLoginRedirect() sends them back
            // here, and the signature is checked again on that request.
            $request->session()->put(self::PENDING_VERIFICATION_URL, $request->fullUrl());

            return redirect()->route('login')
                ->with('success', 'Please sign in to finish verifying your email.');
        }

        if (Auth::id() !== $user->id) {
            // Signed in as SOMEBODY ELSE — never redirect by the verified
            // user's role, it would hand a guest an admin landing page.
            return $this->redirectByRole(Auth::user())
                ->with('error', 'That verification link belongs to a different account. Sign out, then open the link again.');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->redirectByRole($user)
            ->with('success', 'Email verified! Welcome to Villa Elena.');
    }

    /**
     * Where a completed login goes: back to a verification link that was
     * opened while signed out, otherwise the role's landing page.
     */
    private function afterLoginRedirect(User $user)
    {
        $pending = request()->session()->pull(self::PENDING_VERIFICATION_URL);

        // Only for the account the link is for. Signing in as someone else
        // drops it rather than bouncing them to a page that will refuse them.
        if ($pending && ! $user->hasVerifiedEmail()
            && str_contains((string) parse_url($pending, PHP_URL_PATH), "/email/verify/{$user->id}/")) {
            return redirect()->to($pending);
        }

        return $this->redirectByRole($user);
    }

    // ── Email Verification: Resend Link ────────────────────────────
    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirectByRole($request->user());
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Exception $e) {
            Log::error('Failed to resend verification email: ' . $e->getMessage());

            return back()
                ->with('error', 'The verification link cannot be sent right now. Please try again later.')
                ->with('verification_sent', false);
        }

        return back()
            ->with('success', 'Verification link sent! Please check your email.')
            ->with('verification_sent', true);
    }

    // ── Redirect By Role ───────────────────────────────────────────
    private function redirectByRole(User $user)
    {
        return match($user->role) {
            'admin'    => redirect()->route('admin.dashboard'),
            'staff'    => redirect()->route('staff.frontdesk'),
            'customer' => redirect()->route('customer.home'),
            default    => redirect('/'),
        };
    }
}