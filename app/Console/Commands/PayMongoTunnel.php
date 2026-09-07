<?php

namespace App\Console\Commands;

use App\Services\PayMongoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Isinasabay ang estado ng ngrok webhook sa tunay nitong kalusugan.
 *
 * ANG PROBLEMANG NILULUTAS NITO. Nasa iisang test-mode na account ang
 * webhook ng produksyon at ang webhook ng dev machine, kaya ipinapadala
 * ng PayMongo ang BAWAT test-mode na event sa PAREHO — kasama ang mga
 * galing sa produksyon. Kapag walang nakahawak sa dulo ng ngrok, 502
 * ang bawat isa sa mga iyon, at ang paulit-ulit na 502 ang eksaktong
 * bagay na nagpapa-disable ng webhook. Ibig sabihin, ang webhook ng dev
 * ay tahimik na namamatay habang nakasara ang laptop, at ang mapapansin
 * mo na lang ay sa SUSUNOD mong pag-test: tumatakbo ang ngrok, nagbayad
 * ka, at walang naitala.
 *
 * ANG SENYAS AY HINDI ANG NGROK AGENT. Ito ang natutunan sa pagsukat:
 * puwedeng buhay na buhay ang ngrok agent (may tunnel sa `/api/tunnels`,
 * 200 ang agent API) samantalang 502 pa rin ang endpoint — dahil walang
 * `php artisan serve` sa likod nito. Kaya HINDI sapat na tanungin ang
 * ngrok kung may tunnel ba; ang tanong ay kung SUMASAGOT BA ANG URL
 * MISMO, buong daan. Iyon ang sinusukat dito, sa parehong probe na
 * gagamitin ng PayMongo.
 *
 * HINDI NITO PINAPATAKBO ANG NGROK. Sinusundan lang nito ang ngrok —
 * kaya walang pakialam ito kung paano mo sinimulan iyon (anong port,
 * anong config, anong flags), at nakakabawi ito kapag nag-restart ang
 * ngrok sa gitna ng session.
 *
 * KAPAG NAPUTOL NANG PATAY. Kung hindi natuloy ang paglabas — sarado
 * ang terminal, nawalan ng kuryente — mananatiling enabled ang webhook
 * at magsisimula itong mangolekta ng 502. Hindi iyon trahedya: ang
 * susunod na takbo nito ay ibabalik sa tama ang estado, at nandiyan pa
 * rin ang `paymongo:webhooks --disable=tunnels` bilang manwal na linis.
 */
class PayMongoTunnel extends Command
{
    protected $signature = 'paymongo:tunnel
        {--webhook= : Ang webhook na susundan (default: PAYMONGO_TUNNEL_WEBHOOK, o hinuhulaan mula sa ngrok)}
        {--interval=10 : Ilang segundo sa pagitan ng bawat pagsusuri}
        {--once : Isang beses lang magsuri, tapos lumabas — hindi nagbabantay}
        {--dry-run : Ipakita ang gagawin nang hindi ginagalaw ang PayMongo}';

    protected $description = 'Bantayan ang ngrok webhook: buhayin kapag umaandar, patayin kapag hindi.';

    /** Ang lokal na API ng ngrok agent. */
    private const NGROK_API = 'http://127.0.0.1:4040/api/tunnels';

    /**
     * Ang mga domain na pag-aari ng ngrok.
     *
     * Ito ang bakod na pumipigil sa command na ito na hawakan ang
     * anumang HINDI tunnel. Kung wala ito, ang isang `--webhook=` na
     * nakaturo sa produksyon ay may dalawang paraan ng pagkasira:
     * ipupunto ng reconcile() ang rehistro ng produksyon sa ngrok URL,
     * at papatayin nito ang webhook ng produksyon sa bawat paglabas.
     * Isang maling id lang ang layo ng dalawang iyon, kaya sinusuri ang
     * host bago pa man magsimula.
     */
    private const NGROK_HOSTS = ['.ngrok-free.dev', '.ngrok-free.app', '.ngrok.io', '.ngrok.app', '.ngrok.dev'];

    private ?PayMongoService $paymongo = null;

    private ?string $webhookId = null;

    /** Ang huling alam nating estado, para tumawag lang tayo sa mga transition. */
    private ?bool $enabled = null;

    private bool $stopping = false;

    public function handle(PayMongoService $paymongo): int
    {
        $this->paymongo = $paymongo;

        if ($paymongo->isTestMode() === false) {
            // Ang produksyon ay hindi kailanman naka-ngrok. Kung live
            // keys ang hawak, tiyak na mali ang command na ito — at ang
            // mapapatay nito ay ang webhook ng tunay na pera.
            $this->error('Live keys ang nakatakda. Para sa test-mode na ngrok webhook lang ito.');

            return self::FAILURE;
        }

        if (($this->webhookId = $this->resolveWebhook()) === null) {
            return self::FAILURE;
        }

        if (! $this->assertIsTunnel()) {
            return self::FAILURE;
        }

        $this->line("Sinusundan ang <options=bold>{$this->webhookId}</>.");

        if ($this->option('once')) {
            $this->reconcile();

            return self::SUCCESS;
        }

        $this->registerShutdownHandlers();

        $interval = max(2, (int) $this->option('interval'));
        $this->line("Nagbabantay tuwing {$interval}s. Ctrl+C para tumigil (papatayin ang webhook paglabas).");
        $this->newLine();

        while (! $this->stopping) {
            $this->reconcile();

            // Hinahati ang paghihintay para tumugon agad sa Ctrl+C sa
            // halip na matulog pa nang buong interval bago mapansin.
            for ($i = 0; $i < $interval && ! $this->stopping; $i++) {
                sleep(1);
            }
        }

        $this->shutdown();

        return self::SUCCESS;
    }

    /**
     * Isang pagsusuri: sukatin ang endpoint, itugma ang estado.
     */
    private function reconcile(): void
    {
        $webhook = $this->fetchWebhook();

        if ($webhook === null) {
            return;
        }

        $url = $webhook['attributes']['url'] ?? '';
        $registeredStatus = ($webhook['attributes']['status'] ?? '') === 'enabled';

        // Kung ibang URL na ang ibinibigay ng ngrok ngayon (nangyayari
        // kapag walang reserved domain), ituro muna ang rehistro doon —
        // walang saysay ang pagbuhay ng webhook na nakaturo sa isang
        // tunnel na wala na.
        if (($current = $this->ngrokUrl()) !== null && $current !== $url) {
            $this->warn("Iba na ang ngrok URL: {$url} → {$current}");

            if (! $this->option('dry-run')) {
                try {
                    $this->paymongo->updateWebhook($this->webhookId, ['url' => $current]);
                    $url = $current;
                } catch (\Throwable $e) {
                    $this->error('Hindi ma-update ang URL: '.$e->getMessage());

                    return;
                }
            }
        }

        $status = $this->paymongo->probeEndpoint($url, 10);
        $healthy = ! $this->paymongo->endpointIsDead($status);
        $label = $status === null ? 'walang koneksyon' : "HTTP {$status}";

        // Tumatawag lang tayo sa PayMongo kapag may TRANSITION. Kung
        // hindi, isang API call ito bawat interval magdamag.
        if ($this->enabled === $healthy && $registeredStatus === $healthy) {
            return;
        }

        $this->enabled = $healthy;

        if ($healthy) {
            $this->apply('enable', "Buhay ang {$url} ({$label})", fn () => $this->paymongo->enableWebhook($this->webhookId));

            return;
        }

        $this->apply('disable', "Patay ang {$url} ({$label})", fn () => $this->paymongo->disableWebhook($this->webhookId));
    }

    /**
     * Isinasagawa ang isang pagbabago, o iniuulat lang kung dry-run.
     */
    private function apply(string $action, string $why, \Closure $call): void
    {
        $stamp = now()->format('H:i:s');

        if ($this->option('dry-run')) {
            $this->line("[{$stamp}] {$why} — <options=bold>{$action}</> (dry-run, walang ginalaw)");

            return;
        }

        try {
            $call();
            $verb = $action === 'enable' ? 'Binuhay' : 'Pinatay';
            $this->info("[{$stamp}] {$why} — {$verb} ang webhook.");
        } catch (\Throwable $e) {
            $this->error("[{$stamp}] Hindi ma-{$action}: ".$e->getMessage());
            // Kalimutan ang natandaan para subukan ulit sa susunod na
            // pagsusuri sa halip na ipagpalagay na natuloy ito.
            $this->enabled = null;
        }
    }

    /**
     * Aling webhook ang susundan.
     *
     * Tatlong hakbang: ang `--webhook`, ang env, at kung wala pareho,
     * hinuhulaan ito sa pamamagitan ng paghahanap ng rehistrong ang
     * host ay katugma ng ibinibigay ng ngrok ngayon.
     */
    private function resolveWebhook(): ?string
    {
        if ($explicit = ($this->option('webhook') ?: config('services.paymongo.tunnel_webhook'))) {
            return $explicit;
        }

        $url = $this->ngrokUrl();

        if ($url === null) {
            $this->error('Hindi maabot ang ngrok agent ('.self::NGROK_API.') at walang --webhook na ibinigay.');
            $this->line('Patakbuhin muna ang ngrok, o ituro ang webhook: --webhook=hook_xxx (o PAYMONGO_TUNNEL_WEBHOOK sa .env).');

            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        try {
            foreach ($this->paymongo->listWebhooks() as $webhook) {
                if (parse_url($webhook['attributes']['url'] ?? '', PHP_URL_HOST) === $host) {
                    return $webhook['id'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            $this->error('Hindi makuha ang listahan ng webhook: '.$e->getMessage());

            return null;
        }

        $this->error("Walang naka-rehistrong webhook para sa host na {$host}.");
        $this->line('Gumawa ng isa sa PayMongo dashboard na nakaturo sa '.rtrim($url, '/').'/webhooks/paymongo, o ibigay ang --webhook=hook_xxx.');

        return null;
    }

    /**
     * Tinitiyak na ang susundan ay tunay ngang isang ngrok tunnel.
     *
     * Ito ang tanging bagay na humahadlang sa command na ito na
     * makagalaw ng isang totoong endpoint. Tumatanggi ito nang malinaw
     * kaysa gumawa ng pinsalang mahirap na mapansin.
     */
    private function assertIsTunnel(): bool
    {
        $webhook = $this->fetchWebhook();

        if ($webhook === null) {
            return false;
        }

        $url = $webhook['attributes']['url'] ?? '';
        $host = (string) parse_url($url, PHP_URL_HOST);

        foreach (self::NGROK_HOSTS as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        $this->error("Hindi ngrok URL ang {$this->webhookId}: {$url}");
        $this->line('Para sa mga tunnel lang ito. Ang pagsunod sa isang totoong endpoint ay ipupunto ito sa ngrok at papatayin sa bawat paglabas.');

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchWebhook(): ?array
    {
        try {
            foreach ($this->paymongo->listWebhooks() as $webhook) {
                if (($webhook['id'] ?? null) === $this->webhookId) {
                    return $webhook;
                }
            }
        } catch (\Throwable $e) {
            $this->error('Hindi makuha ang webhook: '.$e->getMessage());

            return null;
        }

        $this->error("Wala na ang webhook na {$this->webhookId}.");
        $this->stopping = true;

        return null;
    }

    /**
     * Ang kasalukuyang https public URL ng ngrok, kung tumatakbo ito.
     */
    private function ngrokUrl(): ?string
    {
        try {
            $tunnels = Http::timeout(5)->get(self::NGROK_API)->json('tunnels') ?? [];
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($tunnels as $tunnel) {
            $url = $tunnel['public_url'] ?? '';

            if (str_starts_with($url, 'https://')) {
                return rtrim($url, '/').'/webhooks/paymongo';
            }
        }

        return null;
    }

    /**
     * Para tumakbo pa rin ang paglilinis kapag Ctrl+C.
     *
     * Magkaiba ang mekanismo sa bawat OS at wala sa dalawa ang tiyak na
     * naroroon, kaya pareho silang binabantayan nang may `function_exists`
     * — at may `register_shutdown_function` pa bilang huling lambat.
     * Ang dev machine dito ay Windows, kung saan ang `pcntl` ay wala at
     * ang `sapi_windows_set_ctrl_handler` ang katumbas nito.
     */
    private function registerShutdownHandlers(): void
    {
        $stop = function () {
            $this->stopping = true;
        };

        if (function_exists('sapi_windows_set_ctrl_handler')) {
            @sapi_windows_set_ctrl_handler($stop);
        }

        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, $stop);
            pcntl_signal(SIGTERM, $stop);
        }

        register_shutdown_function(function () {
            $this->shutdown();
        });
    }

    /**
     * Pinapatay ang webhook paglabas — ito ang buong punto ng command.
     * Ligtas itong tawagin nang paulit-ulit.
     */
    private function shutdown(): void
    {
        if ($this->enabled !== true || $this->option('dry-run')) {
            return;
        }

        $this->enabled = false;

        try {
            $this->paymongo->disableWebhook($this->webhookId);
            $this->newLine();
            $this->info("Pinatay ang {$this->webhookId} paglabas.");
        } catch (\Throwable $e) {
            $this->error('Hindi mapatay paglabas: '.$e->getMessage());
            $this->line('Linisin nang manwal: php artisan paymongo:webhooks --disable=tunnels');
        }
    }
}
