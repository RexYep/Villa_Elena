<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('admin.profile.index', ['user' => Auth::user()]);
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

        // The contact details on an admin account are a recovery surface —
        // whoever owns the phone number on file is who the resort will call
        // back. Both sides are kept so a quiet substitution is visible as a
        // substitution rather than as "profile updated".
        StaffLog::record('admin_profile_updated', 'users', $user->id,
            'Admin updated their own profile details',
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

        // Recorded AFTER the save, and never with the password in it — the
        // audit value is "this account's credential changed, by this actor,
        // from this address, at this moment", which is what makes a later
        // "that wasn't me" answerable.
        StaffLog::record('password_changed', 'users', $user->id,
            'Admin changed their own password');

        return back()->with('success', 'Password changed successfully.');
    }
}
