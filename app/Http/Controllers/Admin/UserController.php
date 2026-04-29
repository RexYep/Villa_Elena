<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // ── List All Users ─────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = User::withCount('bookings')->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('email',    'like', "%$search%")
                  ->orWhere('phone',    'like', "%$search%");
            });
        }

        $users = $query->paginate(15)->withQueryString();

        $stats = [
            'total'     => User::count(),
            'customers' => User::where('role', 'customer')->count(),
            'staff'     => User::where('role', 'staff')->count(),
            'admins'    => User::where('role', 'admin')->count(),
            'active'    => User::where('status', 1)->count(),
            'inactive'  => User::where('status', 0)->count(),
        ];

        return view('admin.users.index', compact('users', 'stats'));
    }

    // ── Show Create Form ───────────────────────────────────────────
    public function create()
    {
        return view('admin.users.create');
    }

    // ── Store New User ─────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:150',
            'email'     => 'required|email|unique:users,email',
            'phone'     => 'required|string|max:20',
            'role'      => 'required|in:customer,staff,admin',
            'password'  => 'required|string|min:8|confirmed',
            'address'   => 'nullable|string',
            'id_type'   => 'nullable|string',
            'id_number' => 'nullable|string',
        ]);

        $user = User::create([
            'full_name'  => $request->full_name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'role'       => $request->role,
            'password'   => Hash::make($request->password),
            'address'    => $request->address,
            'id_type'    => $request->id_type,
            'id_number'  => $request->id_number,
            'status'     => 1,
        ]);

        StaffLog::record('created_user', 'users', $user->id,
            "Created {$user->role} account: {$user->full_name}");

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Account for \"{$user->full_name}\" created successfully.");
    }

    // ── Show User Profile ──────────────────────────────────────────
    public function show(User $user)
    {
        $user->loadCount('bookings');
        $bookings = $user->bookings()->with('property')->latest()->take(10)->get();
        $totalSpent = $user->bookings()
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount_paid');
        $stats = [
            'total_bookings'   => $user->bookings_count,
            'completed'        => $user->bookings()->where('status', 'checked_out')->count(),
            'cancelled'        => $user->bookings()->where('status', 'cancelled')->count(),
            'total_spent'      => $totalSpent,
        ];
        return view('admin.users.show', compact('user', 'bookings', 'stats'));
    }

    // ── Show Edit Form ─────────────────────────────────────────────
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    // ── Update User ────────────────────────────────────────────────
    public function update(Request $request, User $user)
    {
        $request->validate([
            'full_name' => 'required|string|max:150',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'phone'     => 'required|string|max:20',
            'role'      => 'required|in:customer,staff,admin',
            'address'   => 'nullable|string',
            'id_type'   => 'nullable|string',
            'id_number' => 'nullable|string',
            'password'  => 'nullable|string|min:8|confirmed',
        ]);

        $data = [
            'full_name' => $request->full_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'role'      => $request->role,
            'address'   => $request->address,
            'id_type'   => $request->id_type,
            'id_number' => $request->id_number,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        StaffLog::record('updated_user', 'users', $user->id,
            "Updated user account: {$user->full_name}");

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Profile updated successfully.");
    }

    // ── Delete User ────────────────────────────────────────────────
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->full_name;
        $user->delete();

        StaffLog::record('deleted_user', 'users', null, "Deleted user: {$name}");

        return redirect()->route('admin.users.index')
            ->with('success', "User \"{$name}\" has been deleted.");
    }

    // ── Toggle Active/Inactive Status ──────────────────────────────
    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['status' => !$user->status]);

        $action = $user->status ? 'activated' : 'deactivated';

        StaffLog::record('toggled_user_status', 'users', $user->id,
            "Account {$action}: {$user->full_name}");

        return back()->with('success', "Account {$action} successfully.");
    }
}