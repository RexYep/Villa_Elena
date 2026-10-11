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
        // Ang may email lang ang puwedeng mag-login. Ang walk-in na
        // "guest record lang" ay NULL ang email (FrontDeskController::
        // storeWalkin), kaya kasama sa Total Guests pero hindi dito.
        $registered = User::where('role', 'customer')->whereNotNull('email')->count();
        // Badge ng Housekeeping sa sidebar — nasa bawat admin page.
        $openReports = IssueReport::open()->count();

        // NETO ang revenue ng dashboard mula v7.69: nakolekta BAWAS ang
        // mga refund, ayon sa Payment::netRevenue() — ang parehong
        // panuntunan ng "Net Revenue" sa Reports. Dating "hindi kasama ang
        // refund" lang (gross, at walang `status = success`), kaya
        // ₱49,018 ang buwan dito at ₱49,006 sa Reports: dalawang
        // "revenue ngayong buwan" na magkaiba sa iisang admin.
        //
        // Ang refund ay Payment row na may POSITIBONG halaga, kaya dapat
        // itong IBAWAS, hindi lang laktawan — at lalong hindi idagdag
        // (iyon ang orihinal na bug ng Revenue Today). Maaaring maging
        // negatibo ang araw: refund na inilabas nang walang nakolekta.
        $today = Payment::netRevenue(Payment::whereDate('payment_date', today()));

        $month = Payment::netRevenue(
            Payment::whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)
        );

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
        //
        // `startOfMonth()` muna bago ang `subMonths()` (v7.69). Kapag ika-29
        // hanggang ika-31 ngayon at mas maikli ang buwang tinutumbok,
        // lumalampas ang petsa sa susunod na buwan: Okt 31 − 1 buwan =
        // "Set 31" = Okt 1, kaya dalawang beses lumabas ang Oktubre at
        // nawala ang Setyembre sa chart.
        $revenueByMonth = collect(range(5, 0))->map(function ($i) {
            $m = now()->startOfMonth()->subMonths($i);
            // Neto rin, kagaya ng card: kung gross ang chart, sasalungat
            // ang bar ng kasalukuyang buwan sa Revenue This Month.
            $amount = Payment::netRevenue(
                Payment::whereYear('payment_date', $m->year)->whereMonth('payment_date', $m->month)
            );

            return ['label' => $m->format('M'), 'amount' => $amount];
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
            'registered_guests' => $registered,
            'open_reports' => $openReports,
            'revenue_by_month' => $revenueByMonth,
            'booking_sources' => $sources,
            // Ang format ay nasa server para ang page at ang JSON ay
            // magpakita ng eksaktong iisang anyo. Dating nagbabago mula
            // "₱6,000" sa render tungo sa "₱6,000.00" matapos ang update.
            'formatted' => [
                'pending_bookings' => (string) $pending,
                'total_bookings' => number_format($total),
                'revenue_today' => Payment::formatPesos($today),
                'revenue_this_month' => Payment::formatPesos($month),
                'total_guests' => number_format($guests),
                'registered_guests' => number_format($registered),
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
