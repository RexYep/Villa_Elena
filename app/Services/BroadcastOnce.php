<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Magpadala ng isang "magtanong muli" na broadcast, MINSAN kada request.
 *
 * Ang mga live na bahagi ng app (admin dashboard, staff availability) ay
 * hindi nagbabasa ng numero mula sa event — nagtatanong ang page sa server.
 * Kaya ang kailangan ay "may nagbago", minsan, pagkatapos ng request:
 *
 * - MINSAN: iisang bayad ay nagsusulat sa Payment at sa Booking nang
 *   maraming beses, at ang sweeper ay kayang magkansela ng maraming
 *   booking sa iisang takbo. Kung iisang broadcast kada save, iyon ay
 *   maraming HTTP call sa Pusher para sa iisang tanong.
 * - PAGKATAPOS (`terminating`): hindi pinababagal ang response, at
 *   tumatakbo matapos ang anumang transaction — hindi nagba-broadcast ng
 *   pagbabagong hindi pa committed.
 * - HINDI KAILANMAN NAGHAHAGIS: tumatakbo ito sa loob ng PayMongo webhook,
 *   na hindi kailanman dapat magbalik ng non-2xx. Ang pana-panahong refetch
 *   sa bawat page ay ang salo kapag nabigo ito.
 *
 * Ang `$key` ay naghihiwalay sa mga uri: ang dashboard at ang availability
 * ay magkaibang signal, at ang isa ay hindi dapat lumulon sa kabila.
 */
class BroadcastOnce
{
    /** @var array<string, bool> */
    private static array $queued = [];

    public static function dispatch(string $key, \Closure $makeEvent): void
    {
        if (isset(self::$queued[$key])) {
            return;
        }

        self::$queued[$key] = true;

        app()->terminating(function () use ($key, $makeEvent) {
            unset(self::$queued[$key]);

            try {
                event($makeEvent());
            } catch (\Throwable $e) {
                Log::warning("Broadcast '{$key}' failed: ".$e->getMessage());
            }
        });
    }
}
