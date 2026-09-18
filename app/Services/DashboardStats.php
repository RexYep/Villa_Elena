<?php

namespace App\Services;

use App\Events\DashboardStatsChanged;
use App\Models\Booking;
use App\Models\IssueReport;
use App\Models\Payment;
use App\Models\User;

/**
 * Ang mga "live" na KPI ng admin dashboard, at ang signal na nagbago ang mga ito.
 *
 * IISANG pinagmulan ang kpis() para sa dalawang gumagamit: ang unang
 * render ng dashboard, at ang GET /admin/dashboard/stats na tinatanong
 * ng page kapag may nagbago. Kung dalawang kopya ang mga query, ang
 * numerong kusang nag-update ay maaaring hindi tumugma sa numerong
 * nakikita pagkatapos ng refresh — ang mismong reklamong nilulutas dito.
 *
 * Sinasadyang HINDI naka-cache ang mga ito, taliwas sa iba pang stats
 * ng dashboard (`admin_dashboard_stats`, 60s). Apat na COUNT/SUM na may
 * index ang mga ito; ang cache ay nangangahulugang ang refresh sa loob
 * ng isang minuto matapos ang pagbabago ay nagpapakita ng LUMANG numero,
 * gayong ang page na kusang nag-update ay nagpakita na ng bago.
 */
class DashboardStats
{

    /**
     * Bilang ng booking na naghihintay ng aksyon.
     *
     * Hiwalay dahil dalawa na ang gumagamit: ang `pending_bookings` na
     * KPI ng dashboard, at ang badge sa tabi ng "Bookings" sa sidebar na
     * nakikita sa BAWAT admin page. Iisang query para hindi kailanman
     * magkaiba ang dalawang numerong magkatabi sa iisang screen.
     */
    public static function pendingBookings(): int
    {
        return Booking::where('status', 'pending')->count();
    }

    public static function kpis(): array
    {
        $pending = static::pendingBookings();
        $total = Booking::count();
        // Iisang query sa dating `total_guests` ng dashboard: bawat
        // customer account, verified o hindi.
        $guests = User::where('role', 'customer')->count();
        // Badge ng Housekeeping sa sidebar — nasa bawat admin page.
        $openReports = IssueReport::open()->count();

        // Ang refund ay naitatala bilang Payment row na `payment_type =
        // refund` na may POSITIBONG halaga, kaya kailangang hindi isama —
        // ganito rin ang dating query ng dashboard.
        $today = (float) Payment::whereDate('payment_date', today())
            ->where('payment_type', '!=', 'refund')
            ->sum('amount');

        $month = (float) Payment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->where('payment_type', '!=', 'refund')
            ->sum('amount');

        // ── Mga chart ──────────────────────────────────────────────
        //
        // Dating nasa 60s na `admin_dashboard_stats` cache at iginuguhit
        // MINSAN sa page load — walang anumang nag-a-update sa mga ito,
        // gayong may berdeng "Live" badge ang Revenue Overview. Inilipat
        // dito nang walang pagbabago sa query, para ang render at ang
        // JSON na refetch ay iisang pinagmulan, at ang bar ng kasalukuyang
        // buwan ay hindi kailanman sumalungat sa Revenue This Month card.
        //
        // Pitong dagdag na query sa refetch (anim na SUM na may index at
        // isang GROUP BY). Ang refetch ay tumatakbo LANG kapag may
        // `stats.changed` o tuwing 60s sa bukas na dashboard — hindi sa
        // anumang public page.
        $revenueByMonth = collect(range(5, 0))->map(function ($i) {
            $m = now()->subMonths($i);
            $amount = Payment::whereYear('payment_date', $m->year)
                ->whereMonth('payment_date', $m->month)
                ->where('payment_type', '!=', 'refund')
                ->sum('amount');

            return ['label' => $m->format('M'), 'amount' => (float) $amount];
        })->values()->toArray();

        $sources = collect(['online' => 0, 'walk_in' => 0, 'phone' => 0, 'partner' => 0])
            ->merge(
                Booking::selectRaw('source, COUNT(*) as count')
                    ->groupBy('source')
                    ->pluck('count', 'source')
            )
            ->map(fn ($c) => (int) $c)
            ->toArray();

        return [
            'pending_bookings' => $pending,
            'total_bookings' => $total,
            'revenue_today' => $today,
            'revenue_this_month' => $month,
            'total_guests' => $guests,
            'open_reports' => $openReports,
            'revenue_by_month' => $revenueByMonth,
            'booking_sources' => $sources,
            // Ang format ay nasa server para ang page at ang JSON ay
            // magpakita ng eksaktong iisang anyo. Dating nagbabago mula
            // "₱6,000" sa render tungo sa "₱6,000.00" matapos ang update.
            'formatted' => [
                'pending_bookings' => (string) $pending,
                'total_bookings' => number_format($total),
                'revenue_today' => '₱'.number_format($today, 0),
                'revenue_this_month' => '₱'.number_format($month, 0),
                'total_guests' => number_format($guests),
                'open_reports' => (string) $openReports,
            ],
        ];
    }

    /**
     * Markahan na nagbago ang mga numero; ipinapadala ang signal nang
     * MINSAN, pagkatapos ng request.
     *
     * Tinatawag mula sa model events ng Booking at Payment, kaya sakop
     * nito ang BAWAT daanan — kumpirmasyon dahil sa bayad, check-in sa
     * front desk, awtomatikong pagkansela ng stale hold, pagkansela ng
     * guest, cash na itinala ng staff — nang hindi kinakailangang
     * tandaan ng bawat controller na magpadala. Ang pagkalimot ng isang
     * controller ay ang eksaktong dahilan ng stuck na Pending Bookings.
     *
     * Iisa kada request, pagkatapos ng response, hindi kailanman naghahagis
     * — tingnan ang BroadcastOnce kung bakit ang bawat isa.
     */
    public static function touch(): void
    {
        BroadcastOnce::dispatch('dashboard-stats', fn () => new DashboardStatsChanged);
    }
}
