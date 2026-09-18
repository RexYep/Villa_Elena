<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "May bagong ulat, o nagbago ang status ng isang ulat — kunin muli ang listahan."
 *
 * Para sa staff frontdesk (Issue Reports + urgent banner + bilang) at sa
 * admin Housekeeping page. WALANG laman nang sadya, tulad ng
 * StaffAvailabilityChanged: ang listahan ay muling ire-render ng server
 * gamit ang parehong Blade partial, kaya ang signal na nadoble, naantala
 * o nawala ay walang masamang epekto — ang 60s na refetch ang salo.
 *
 * Ipinapadala sa pamamagitan ng BroadcastOnce mula sa IssueReport
 * (`created` hook at HasWorkStatus::transition()), hindi mula sa mga
 * controller — kaya sakop ang bawat daanan nang hindi ito tinatandaan.
 */
class IssueReportsChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function broadcastOn(): array
    {
        return [new Channel('staff-frontdesk'), new Channel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'issues.changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
