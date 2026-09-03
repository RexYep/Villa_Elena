<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        // Hit on every admin dashboard load; the underlying counts/sums
        // rarely need to be to-the-second fresh, so a short TTL cache cuts
        // repeat-load DB traffic without stats visibly going stale.
        $stats = Cache::remember('admin_dashboard_stats', 60, function () {
            return [
                'total_bookings' => Booking::count(),
                'todays_checkins' => Booking::whereDate('check_in_date', today())
                    ->where('status', 'confirmed')->count(),
                'todays_checkouts' => Booking::whereDate('check_out_date', today())
                    ->where('status', 'checked_in')->count(),
                'pending_bookings' => Booking::where('status', 'pending')->count(),
                'total_guests' => User::where('role', 'customer')->count(),
                'total_properties' => Property::count(),
                'current_guest' => Booking::where('status', 'checked_in')
                    ->with('user:id,full_name')
                    ->latest('check_in_date')
                    ->first(),
                'revenue_today' => Payment::whereDate('payment_date', today())
                    ->where('payment_type', '!=', 'refund')
                    ->sum('amount'),
                'revenue_this_month' => Payment::whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->where('payment_type', '!=', 'refund')
                    ->sum('amount'),
                'revenue_by_month' => collect(range(5, 0))->map(function ($i) {
                    $month = now()->subMonths($i);
                    $amount = Payment::whereYear('payment_date', $month->year)
                        ->whereMonth('payment_date', $month->month)
                        ->where('payment_type', '!=', 'refund')
                        ->sum('amount');

                    return ['label' => $month->format('M'), 'amount' => (float) $amount];
                })->values()->toArray(),
                'booking_sources' => collect(['online' => 0, 'walk_in' => 0, 'phone' => 0, 'partner' => 0])
                    ->merge(
                        Booking::selectRaw('source, COUNT(*) as count')
                            ->groupBy('source')
                            ->pluck('count', 'source')
                    )->toArray(),
            ];
        });

        // SADYANG NASA LABAS ng `Cache::remember` sa itaas. Ang mga KPI ay
        // maaaring maluma nang isang minuto nang walang masamang epekto,
        // pero ang isang rekomendasyong kaka-apply o kaka-dismiss lang ay
        // dapat MAWALA agad dito — kung hindi, mukhang hindi tumalab ang
        // pinindot ng admin. Mura naman ang query: tatlong row, may index.
        $topActions = Recommendation::open()
            ->whereDate('target_end', '>=', today())
            ->orderByDesc('expected_impact')
            ->limit(3)
            ->get();

        return view('admin.dashboard.index', compact('stats', 'topActions'));
    }

    // ── Global Search ──────────────────────────────────────────────
    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['bookings' => [], 'guests' => [], 'properties' => []]);
        }

        $bookings = Booking::where(function ($query) use ($q) {
            $query->where('booking_ref', 'like', "%{$q}%")
                ->orWhere('status', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$q}%"));
        })
            ->with(['user:id,full_name', 'property:id,property_name'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'booking_ref' => $b->booking_ref,
                'guest' => $b->user->full_name ?? '—',
                'property' => $b->property->property_name ?? '—',
                'status' => $b->status,
            ]);

        $guests = User::where('role', 'customer')
            ->where(fn ($q2) => $q2->where('full_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"))
            ->withCount('bookings')
            ->take(4)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'email' => $u->email,
                'bookings' => $u->bookings_count,
            ]);

        $properties = Property::where('property_name', 'like', "%{$q}%")
            ->orWhere('type', 'like', "%{$q}%")
            ->take(4)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->property_name,
                'type' => ucfirst($p->type),
                'status' => $p->status,
                'capacity' => $p->max_capacity,
            ]);

        return response()->json(compact('bookings', 'guests', 'properties'));
    }
}
