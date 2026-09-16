<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\DashboardStats;
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
            // Ang mga numerong kusang nag-a-update (5 KPI + 2 chart) ay
            // HINDI rito — nasa DashboardStats::kpis(), sariwa. Dating
            // nadoble dito, at ang cache na kopya ay ang dahilan kung bakit
            // ang refresh ay kayang magpakita ng lumang numero matapos ang
            // live na update. Iisang kopya ngayon.
            return [
                'todays_checkins' => Booking::whereDate('check_in_date', today())
                    ->where('status', 'confirmed')->count(),
                'todays_checkouts' => Booking::whereDate('check_out_date', today())
                    ->where('status', 'checked_in')->count(),
                'total_properties' => Property::count(),
                'current_guest' => Booking::where('status', 'checked_in')
                    ->with('user:id,full_name')
                    ->latest('check_in_date')
                    ->first(),
            ];
        });

        // Ang mga live na numero ay sariwa, sa labas ng cache — tingnan ang
        // DashboardStats::kpis() kung bakit.
        $kpis = DashboardStats::kpis();
        $stats = array_merge($stats, $kpis);

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

    // ── Live KPIs ──────────────────────────────────────────────────
    // GET /admin/dashboard/stats
    //
    // Tinatanong ng dashboard kapag may `stats.changed` (o anumang event
    // ng booking/bayad), at tuwing 60s bilang salo kapag patay ang Pusher.
    // Ang AWTORIDAD — ang event ay hindi nagdadala ng numero.
    public function stats()
    {
        return response()->json(DashboardStats::kpis());
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
