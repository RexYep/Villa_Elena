<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

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
        ]);

        $user = User::create([
            'full_name'  => $request->full_name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'role'       => $request->role,
            'password'   => Hash::make($request->password),
            'address'    => $request->address,
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
            'password'  => 'nullable|string|min:8|confirmed',
        ]);

        $data = [
            'full_name' => $request->full_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'role'      => $request->role,
            'address'   => $request->address,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Snapshot the fields this form can move, BEFORE the write. Only the
        // ones the form owns — a whole-row toArray() would drag in
        // last_login and friends and report them as edits.
        $before = $user->only(['full_name', 'email', 'phone', 'role', 'address', 'password']);

        $user->update($data);

        // THE ROLE IS THE POINT. This endpoint accepts `role` and `password`
        // together, so the two most consequential things an admin can do to
        // another account — hand it admin, or take over its credentials —
        // were both recorded as "Updated user account: Nick Salvador", with
        // old_values and new_values NULL. Two such rows are in the live
        // table right now and nothing can say what either one did.
        //
        // recordChange() names the changed fields in the description and
        // redacts the password's VALUE while still recording THAT it moved.
        StaffLog::recordChange('updated_user', 'users', $user->id,
            "Updated user account: {$user->full_name}",
            $before,
            $user->only(['full_name', 'email', 'phone', 'role', 'address', 'password']));

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Profile updated successfully.");
    }

    // ── Delete User ────────────────────────────────────────────────
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // F6: the id, captured before the delete. It used to pass NULL, so
        // the only handle on the deleted account was a display name — which
        // is not unique, not stable, and not a key. 72 rows in the live
        // table identify their target that way.
        $name = $user->full_name;
        $deletedId = $user->id;
        $role = $user->role;

        $user->delete();

        StaffLog::record('deleted_user', 'users', $deletedId,
            "Deleted {$role} account: {$name} (user #{$deletedId})");

        return redirect()->route('admin.users.index')
            ->with('success', "User \"{$name}\" has been deleted.");
    }

    // ── Toggle Active/Inactive Status ──────────────────────────────
    public function toggleStatus(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['status' => !$user->status]);

        $action = $user->status ? 'activated' : 'deactivated';

        StaffLog::record('toggled_user_status', 'users', $user->id,
            "Account {$action}: {$user->full_name}");

        return back()->with('success', "Account {$action} successfully.");
    }
}