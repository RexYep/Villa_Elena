<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    // ── Show Profile Page ──────────────────────────────────────────
    public function edit(Request $request)
    {
        $user = Auth::user();

        return view('customer.profile', [
            'user' => $user,
            'trustedDevices' => $user->trustedDevices()->where('expires_at', '>', now())->latest('last_used_at')->get(),
            'loginActivities' => $user->loginActivities()->latest()->limit(10)->get(),
            'currentDeviceToken' => $request->cookie('trusted_device'),
        ]);
    }

    // ── Update Profile Info ────────────────────────────────────────
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'id_type' => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
        ]);

        $data = $request->only(['full_name', 'phone', 'address', 'id_type', 'id_number']);

        if ($request->hasFile('avatar')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    // ── Change Password ─────────────────────────────────────────────
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        // Named error bag ('updatePassword') keeps this form's errors from
        // bleeding into the 2FA-disable and Deactivate forms below, which
        // both also collect a field literally named "password" — without
        // the bag, Blade's @error('password') can't tell them apart and
        // all three light up together.
        $request->validateWithBag('updatePassword', [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ], [
            'password.confirmed' => 'Passwords do not match.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'], 'updatePassword');
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', 'Password changed successfully.');
    }

    // ── Toggle Booking Confirmation Emails ───────────────────────────
    // Low-stakes preference, unlike two_factor_enabled below — no
    // password confirmation needed in either direction. Only ever
    // gates the OPTIONAL BookingConfirmedMail send (PaymentController)
    // — never 2FA/verification/password-reset, which always send.
    public function toggleEmailNotifications(Request $request)
    {
        Auth::user()->update(['email_notifications_enabled' => $request->boolean('email_notifications_enabled')]);

        return back()->with('success', 'Notification preference updated.');
    }

    // ── Toggle Two-Factor Authentication ────────────────────────────
    public function toggleTwoFactor(Request $request)
    {
        $user = Auth::user();

        // Turning it off is the sensitive direction — require the current
        // password, same pattern used by deactivate() below. Turning it on
        // is friction-free.
        if ($user->two_factor_enabled) {
            $request->validateWithBag('twoFactor', ['password' => 'required|string']);

            if (! Hash::check($request->password, $user->password)) {
                return back()->withErrors(['password' => 'Password is incorrect.'], 'twoFactor');
            }

            $user->update(['two_factor_enabled' => false]);

            return back()->with('success', 'Two-factor authentication has been turned off.');
        }

        $user->update(['two_factor_enabled' => true]);

        return back()->with('success', 'Two-factor authentication is now on. A new device will need to verify via email before logging in.');
    }

    // ── Remove A Trusted Device ──────────────────────────────────────
    public function removeTrustedDevice(TrustedDevice $device)
    {
        abort_if($device->user_id !== Auth::id(), 403);

        $device->delete();

        return back()->with('success', 'Device removed. It will need to verify again next time it logs in.');
    }

    // ── Deactivate Account ──────────────────────────────────────────
    public function deactivate(Request $request)
    {
        $user = Auth::user();

        $request->validateWithBag('deactivate', ['password' => 'required|string']);

        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Password is incorrect.'], 'deactivate');
        }

        $user->update(['status' => 0]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Your account has been deactivated. Contact the resort to reactivate it.');
    }
}
