<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Helpers\NotificationHelper;
class AuthController extends Controller
{
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

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'No account found with this email.'])->withInput();
        }

        // Check if account is active
        if (!$user->isActive()) {
            return back()->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.'])->withInput();
        }

        // Attempt login
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            // Update last login timestamp
            Auth::user()->update(['last_login' => now()]);

            // Log the action
            StaffLog::record('user_login', 'users', Auth::id(), 'User logged in successfully');

            return $this->redirectByRole(Auth::user());
        }

        return back()->withErrors(['password' => 'Incorrect password.'])->withInput();
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

        return redirect()->route('customer.home')
            ->with('success', 'Welcome to Villa Elena! Your account has been created.');
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

        $status = Password::sendResetLink($request->only('email'));

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