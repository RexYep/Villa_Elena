<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ang sariling account ng staff. Kapareho ng Admin\ProfileController ang
 * mga panuntunan — dati ay walang paraan ang staff na palitan ang sarili
 * nilang password; admin lang ang nakakagawa niyon para sa kanila.
 */
class ProfileController extends Controller
{
    public function edit()
    {
        return view('staff.profile', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:20',
        ]);

        $user = Auth::user();
        $before = $user->only(['full_name', 'phone']);

        $user->update($request->only(['full_name', 'phone']));

        StaffLog::record('staff_profile_updated', 'users', $user->id,
            'Staff updated their own profile details',
            $before, $user->only(['full_name', 'phone']));

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ], [
            'password.confirmed' => 'Passwords do not match.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Pagkatapos ng save, at hindi kailanman kasama ang password.
        StaffLog::record('password_changed', 'users', $user->id,
            'Staff changed their own password');

        return back()->with('success', 'Password changed successfully.');
    }
}
