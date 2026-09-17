<?php

namespace App\Services;

use App\Events\FrontdeskUpdated;
use Illuminate\Support\Facades\Log;

/**
 * Nagpapadala ng `FrontdeskUpdated` sa staff frontdesk nang hindi kailanman
 * naghahagis. Ang task o report ay nakaimbak na bago ito tawagin — ang
 * pagkabigo ng Pusher ay hindi dapat maging 500 para sa guest na nag-ulat
 * o sa admin na nagpadala ng task.
 */
class FrontdeskBroadcast
{
    public static function send(
        string $action,
        string $message,
        ?int $taskId = null,
        ?int $reportId = null,
        ?string $actor = null,
    ): void {
        try {
            event(new FrontdeskUpdated($action, $message, taskId: $taskId, actor: $actor, reportId: $reportId));
        } catch (\Throwable $e) {
            Log::error("Failed to broadcast FrontdeskUpdated ({$action}): " . $e->getMessage());
        }
    }
}
