<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "May nagbago sa mga numero ng dashboard — magtanong muli."
 *
 * WALANG laman nang sadya. Ang dating KPI ng dashboard ay nagdadagdag
 * o nagbabawas ng 1 ayon sa payload ng mga event, at ganito ito
 * nasira: ang Pending Bookings ay binabawasan LANG kapag binago ng
 * admin ang status sa booking page, kaya ang booking na nakumpirma
 * dahil sa bayad, na-check-in sa front desk, o awtomatikong kinansela
 * ay hindi kailanman nabawas — nanatili sa 1 hanggang i-refresh.
 * At ang Revenue Today ay idinadagdag ang halaga ng refund.
 *
 * Kapag ang signal ay "magtanong muli" sa halip na "+1", walang
 * masamang mangyayari kung dumoble, maantala o mawala ito: ang
 * GET /admin/dashboard/stats ang sumasagot, mula sa database.
 *
 * Ipinapadala sa pamamagitan ng DashboardStats::touch(), hindi
 * direkta — tingnan roon kung bakit iisa kada request.
 */
class DashboardStatsChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function broadcastOn(): array
    {
        return [new Channel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'stats.changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
