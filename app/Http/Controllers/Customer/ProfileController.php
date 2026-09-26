<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\StaffLog;
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
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
        ]);

        $data = $request->only(['full_name', 'phone', 'address']);

        if ($request->hasFile('avatar')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    // ── Delete Profile Photo ─────────────────────────────────────────
    public function deleteAvatar()
    {
        $user = Auth::user();

        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
            $user->update(['profile_image' => null]);
        }

        return back()->with('success', 'Profile photo removed successfully.');
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

        // Same reasoning as the reset flow in AuthController: a new password
        // has to end every other way in, or changing it after a suspected
        // compromise achieves nothing. The current session id is kept, so the
        // person doing this stays logged in on this tab; every other session
        // and every trusted device is dropped.
        $revoked = $user->revokeOtherLogins($request->session()->getId());

        // The count matters as much as the event: "and 4 other sessions and
        // 2 trusted devices were dropped" is the difference between a
        // routine hygiene change and someone shutting an intruder out.
        StaffLog::record('password_changed', 'users', $user->id,
            "Guest changed their own password — revoked {$revoked['sessions']} other session(s) and {$revoked['devices']} trusted device(s)");

        // Say so plainly — "you have been signed out elsewhere" is the part a
        // worried guest actually wants confirmed.
        $message = 'Password changed successfully.';

        if ($revoked['sessions'] > 0 || $revoked['devices'] > 0) {
            $message .= ' You have been signed out everywhere else, and trusted devices will need to verify by email again.';
        }

        return back()->with('success', $message);
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

            // TURNING THIS OFF IS THE ONE THAT MATTERS. It is a deliberate
            // downgrade of an authentication control, it is reachable from a
            // session an attacker already holds, and it left no trace of any
            // kind — the account simply stopped asking for a code. Logged as
            // its own action so it can be filtered for directly.
            StaffLog::record('two_factor_disabled', 'users', $user->id,
                'Two-factor authentication turned OFF by the account holder');

            return back()->with('success', 'Two-factor authentication has been turned off.');
        }

        $user->update(['two_factor_enabled' => true]);

        StaffLog::record('two_factor_enabled', 'users', $user->id,
            'Two-factor authentication turned on by the account holder');

        return back()->with('success', 'Two-factor authentication is now on. A new device will need to verify via email before logging in.');
    }

    // ── Remove A Trusted Device ──────────────────────────────────────
    public function removeTrustedDevice(TrustedDevice $device)
    {
        abort_if($device->user_id !== Auth::id(), 403);

        // Captured before the delete as a matter of habit, NOT because it is
        // required here — and the difference is worth writing down, because
        // the obvious justification for this pattern is wrong.
        //
        // Measured: after `$device->delete()` the in-memory model still
        // reports `id = 4` and `device_label = 'Probe Device'`; only
        // `exists` flips to false. Eloquent does not clear attributes on a
        // hard delete, so reading them afterwards would work exactly as well.
        // A tamper test that moved these reads after the delete passed, which
        // is how we found out rather than assumed.
        //
        // It stays in this order anyway: it costs nothing, it does not depend
        // on that framework detail holding, and it reads in the order the
        // events happen. What it is NOT is the fix for the deleted_user /
        // deleted_booking call sites, which pass a null target_id outright
        // (F6, reported and out of scope for this task) — that is a real gap
        // and this is not an instance of it.
        $label = $device->device_label ?: 'unlabelled device';
        $deviceId = $device->id;

        $device->delete();

        StaffLog::record('trusted_device_removed', 'trusted_devices', $deviceId,
            "Trusted device removed by the account holder: {$label}");

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

        // Recorded while the actor is still authenticated — one line further
        // down Auth::logout() runs and Auth::id() inside record() would be
        // null, turning this into an anonymous event.
        StaffLog::record('account_self_deactivated', 'users', $user->id,
            "Account deactivated by the account holder ({$user->email})");

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Your account has been deactivated. Contact the resort to reactivate it.');
    }
}
