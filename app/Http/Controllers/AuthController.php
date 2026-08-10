<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\StaffLog;
use App\Models\TrustedDevice;
use App\Models\LoginActivity;
use App\Helpers\DeviceHelper;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Helpers\NotificationHelper;

class AuthController extends Controller
{
    private const TRUSTED_DEVICE_COOKIE = 'trusted_device';
    private const TRUSTED_DEVICE_DAYS   = 60;
    private const OTP_TTL_MINUTES       = 10;

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
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        // Check if account is active
        if (!$user->isActive()) {
            return back()->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.'])->withInput();
        }

        // 2FA is opt-in (toggled in the customer profile) and only bites on
        // a device we haven't seen before — a matching, unexpired
        // trusted_devices row skips straight to a normal login.
        if ($user->two_factor_enabled && !$this->isTrustedDevice($user, $request)) {
            $code = (string) random_int(100000, 999999);
            Cache::put("2fa_otp_{$user->id}", Hash::make($code), now()->addMinutes(self::OTP_TTL_MINUTES));

            try {
                $user->notify(new TwoFactorCodeNotification($code));
            } catch (\Exception $e) {
                Log::error('Failed to send 2FA code: ' . $e->getMessage());
                return back()->withErrors(['email' => 'Hindi maipadala ang verification code ngayon. Subukan ulit mamaya.'])->withInput();
            }

            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $remember);

            return redirect()->route('two-factor.verify');
        }

        if (Auth::attempt($credentials, $remember)) {
            $this->completeLogin($user, trustDevice: false);
            return $this->redirectByRole($user);
        }

        return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
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

        $hashedCode = Cache::get("2fa_otp_{$user->id}");

        if (!$hashedCode || !Hash::check($request->code, $hashedCode)) {
            return back()->withErrors(['code' => 'Invalid or expired code. Please try again.']);
        }

        Cache::forget("2fa_otp_{$user->id}");
        $remember = $request->session()->pull('2fa_remember', false);
        $request->session()->forget('2fa_user_id');

        Auth::login($user, $remember);
        $this->completeLogin($user, trustDevice: true);

        return $this->redirectByRole($user)->with('success', 'Device verified! Welcome back.');
    }

    // ── Two-Factor: Resend Code ──────────────────────────────────────
    public function resendTwoFactor(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');
        $user   = $userId ? User::find($userId) : null;

        if (!$user) {
            return redirect()->route('login');
        }

        $code = (string) random_int(100000, 999999);
        Cache::put("2fa_otp_{$user->id}", Hash::make($code), now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            $user->notify(new TwoFactorCodeNotification($code));
        } catch (\Exception $e) {
            Log::error('Failed to resend 2FA code: ' . $e->getMessage());
            return back()->with('error', 'Hindi maipadala ang code ngayon. Subukan ulit mamaya.');
        }

        return back()->with('success', 'A new code has been sent to your email.');
    }

    // ── Check If The Current Browser Is A Trusted Device ────────────
    private function isTrustedDevice(User $user, Request $request): bool
    {
        $token = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if (!$token) {
            return false;
        }

        return TrustedDevice::where('user_id', $user->id)
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->exists();
    }

    // ── Finish A Login: Session, last_login, Audit Trail, History ──
    private function completeLogin(User $user, bool $trustDevice): void
    {
        request()->session()->regenerate();

        $user->update(['last_login' => now()]);

        StaffLog::record('user_login', 'users', $user->id, 'User logged in successfully');

        $deviceLabel = DeviceHelper::label(request()->userAgent());

        LoginActivity::create([
            'user_id'            => $user->id,
            'ip_address'         => request()->ip(),
            'device_label'       => $deviceLabel,
            'via_new_device_otp' => $trustDevice,
        ]);

        if ($trustDevice) {
            $token = Str::random(64);

            TrustedDevice::create([
                'user_id'      => $user->id,
                'token'        => $token,
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
        $request->validate([
            'full_name'             => 'required|string|max:150',
            'email'                 => 'required|email|unique:users,email',
            'phone'                 => 'required|string|max:20',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ], [
            'email.unique'              => 'This email is already registered.',
            'password.confirmed'        => 'Passwords do not match.',
            'password.min'              => 'Password must be at least 8 characters.',
        ]);

        $user = User::create([
            'full_name' => $request->full_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'password'  => Hash::make($request->password),
            'role'      => 'customer',
            'status'    => 1,
        ]);

        NotificationHelper::newGuestRegistered($user);

        Auth::login($user);
        $request->session()->regenerate();

        // Sends Laravel's built-in VerifyEmail notification. Doesn't block
        // registration if it fails — the user is already created and
        // logged in by this point, and can retry from the "resend" link
        // on the verification.notice page.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
        }

        return redirect()->route('verification.notice')
            ->with('success', 'Welcome to Villa Elena! Please verify your email to continue.');
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
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Exception $e) {
            Log::error('Failed to send password reset link: ' . $e->getMessage());
            return back()->withErrors(['email' => 'Hindi maipadala ang reset link ngayon. Subukan ulit mamaya.']);
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Password reset link has been sent to your email.')
            : back()->withErrors(['email' => 'We could not find an account with that email address.']);
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
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset successful. You can now log in.')
            : back()->withErrors(['email' => __($status)]);
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
    // This route is intentionally outside the 'auth' middleware so it
    // works when the user clicks the link from Gmail without an active
    // session. We validate the signed URL (handled by Laravel's 'signed'
    // middleware), then look up the user by id, check the hash, mark
    // them verified, log them in, and redirect to their dashboard.
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        // Make sure the hash matches this user's email
        if (!hash_equals(sha1($user->email), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Log the user in if they aren't already
        if (!Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return $this->redirectByRole($user)
            ->with('success', 'Email verified! Welcome to Villa Elena.');
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
            return back()->with('error', 'Hindi maipadala ang verification link ngayon. Subukan ulit mamaya.');
        }

        return back()->with('success', 'Verification link sent! Please check your email.');
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