<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "May nagbago sa availability ng villa — kunin muli ang grid."
 *
 * Para sa staff Availability page, na dating iginuguhit MINSAN: ang slot
 * na na-book, nakumpirma, kinansela o hinarangan matapos na-load ang page
 * ay nanatiling "Available" hanggang i-reload — kayang alukin ni staff sa
 * walk-in ang slot na nakuha na.
 *
 * WALANG laman nang sadya. Ang grid ay muling ire-render ng server
 * (GET /staff/availability/grid) gamit ang iisang Blade at hasConflict(),
 * kaya walang anumang panuntunan ng availability na kinokopya sa JS, at ang
 * signal na nadoble, naantala o nawala ay walang masamang epekto.
 *
 * `staff-frontdesk` ay public channel, pero walang data ang event; ang grid
 * mismo — may mga pangalan ng guest — ay nasa likod ng `role:staff,admin`.
 *
 * Ipinapadala sa pamamagitan ng BroadcastOnce mula sa model hooks ng
 * Booking at AvailabilityBlock, hindi mula sa mga controller.
 */
class StaffAvailabilityChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function broadcastOn(): array
    {
        return [new Channel('staff-frontdesk')];
    }

    public function broadcastAs(): string
    {
        return 'availability.changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
