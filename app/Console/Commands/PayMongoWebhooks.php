<?php

namespace App\Console\Commands;

use App\Services\PayMongoService;
use Illuminate\Console\Command;

/**
 * Tinitingnan, inaayos at binubuhay muli ang mga PayMongo webhook.
 *
 * Bakit ito umiiral: awtomatikong dini-disable ng PayMongo ang isang
 * webhook na paulit-ulit na sumasagot ng 4xx/5xx, at HINDI ito
 * bumabalik nang kusa. Habang naka-disable, walang kahit anong event na
 * dumarating — tahimik na hindi naitatala ang lahat ng online na bayad,
 * at ang tanging senyales ay isang email mula sa PayMongo.
 *
 * Ang unang pagkakataong nangyari ito (v6.1) ay dahil sa isang MALING
 * URL: nakarehistro ang production webhook sa bare origin
 * (`https://villa-elena.onrender.com`) sa halip na sa buong path
 * (`.../webhooks/paymongo`). Ang POST sa `/` ay 405 Method Not Allowed
 * — walang bakas sa application log, dahil hindi man lang naaabot ang
 * controller. Kaya ang unang tanong sa tuwing may na-disable na webhook
 * ay HINDI "tama ba ang secret?" kundi "saan ba talaga ito nakaturo?",
 * at iyon ang unang ipinapakita ng command na ito.
 *
 * Walang shell access ang produksyon (Render), kaya ito ay tumatakbo sa
 * dev machine laban sa PayMongo API gamit ang secret key sa `.env` —
 * ganoon din ang ginagawa ng `migrate --database=aiven`.
 *
 * MAHALAGA: PER-MODE ang listahan. Ang test key ay nakakakita LANG ng
 * test-mode na webhook, ang live key ay live-mode lang. Kung wala ang
 * hinahanap mong webhook dito, malamang ay nasa kabilang mode ito —
 * ipinapakita ng command kung aling mode ang tinitingnan.
 */
class PayMongoWebhooks extends Command
{
    protected $signature = 'paymongo:webhooks
        {--enable= : Buhayin ang webhook na ito (ang id nito, o `all` para sa lahat ng disabled)}
        {--webhook= : Ang webhook na babaguhin ng --url}
        {--url= : Itama ang URL ng webhook na tinukoy ng --webhook}
        {--disable= : Patayin ang webhook na ito (id, o `tunnels` para sa lahat ng hindi tumutugon)}';

    protected $description = 'Ilista, itama, patayin at buhayin muli ang mga naka-rehistrong PayMongo webhook.';

    /**
     * Ang path na dapat tinuturo ng bawat webhook. Kung iba ito,
     * hindi maaabot ang controller kahit gaano katama ang secret.
     */
    private const EXPECTED_PATH = '/webhooks/paymongo';

    public function handle(PayMongoService $paymongo): int
    {
        $mode = $paymongo->isTestMode() ? 'TEST' : 'LIVE';
        $this->line("Mode: <options=bold>{$mode}</> (mula sa PAYMONGO_SECRET_KEY — ang webhook ng kabilang mode ay hindi lalabas dito)");
        $this->newLine();

        // Ang pag-aayos ng URL ay nauuna sa pagbuhay: ang pagbuhay ng
        // isang webhook na mali pa rin ang URL ay agad ding madi-disable.
        if ($this->option('url') !== null && ($exit = $this->repairUrl($paymongo)) !== null) {
            return $exit;
        }

        try {
            $webhooks = $paymongo->listWebhooks();
        } catch (\Throwable $e) {
            $this->error('Hindi makuha ang listahan: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($webhooks === []) {
            $this->warn("Walang naka-rehistrong webhook sa {$mode} mode.");

            return self::SUCCESS;
        }

        $rows = [];
        $disabled = [];
        $misrouted = [];

        foreach ($webhooks as $webhook) {
            $id = $webhook['id'] ?? '';
            $attributes = $webhook['attributes'] ?? [];
            $status = $attributes['status'] ?? 'unknown';
            $url = $attributes['url'] ?? '';

            if ($status !== 'enabled') {
                $disabled[] = $id;
            }

            $pathOk = rtrim((string) parse_url($url, PHP_URL_PATH), '/') === self::EXPECTED_PATH;

            if (! $pathOk) {
                $misrouted[$id] = $url;
            }

            $rows[] = [
                $id,
                $status === 'enabled' ? '<info>enabled</info>' : "<fg=red>{$status}</>",
                $pathOk ? $url : "<fg=red>{$url}</>",
                implode(', ', $attributes['events'] ?? []),
            ];
        }

        $this->table(['ID', 'Status', 'URL', 'Events'], $rows);

        // Ang secret ay ipinapakita LANG sa paggawa ng webhook, hindi sa
        // listahan — kaya ito ang paalala kung saan ito dapat ilagay.
        $this->line("Ang secret ng bawat webhook ay nasa <options=bold>PAYMONGO_WEBHOOK_SECRET_{$mode}</> (o sa generic na PAYMONGO_WEBHOOK_SECRET).");

        foreach ($misrouted as $id => $url) {
            $this->newLine();
            $this->error("Mali ang URL ng {$id}: {$url}");
            $this->line('Dapat nagtatapos ito sa <options=bold>'.self::EXPECTED_PATH.'</>. Kung hindi, POST ito sa maling route at 404/405 ang bawat delivery — na siyang dahilan ng pagka-disable.');
            $this->line("Itama: php artisan paymongo:webhooks --webhook={$id} --url=".rtrim($url, '/').self::EXPECTED_PATH);
        }

        if (($disableTarget = $this->option('disable')) !== null) {
            return $this->disable($paymongo, $disableTarget, $webhooks);
        }

        $target = $this->option('enable');

        if (! $target) {
            if ($disabled !== []) {
                $this->newLine();
                $this->warn(count($disabled).' na webhook ang naka-disable. Buhayin gamit ang: php artisan paymongo:webhooks --enable=all');
            }

            return self::SUCCESS;
        }

        $toEnable = $target === 'all' ? $disabled : [$target];

        if ($toEnable === []) {
            $this->info('Walang naka-disable na webhook — wala nang dapat gawin.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($toEnable as $id) {
            // Ang pagbuhay ng webhook na mali ang URL ay walang saysay —
            // babagsak lang ulit ang bawat delivery at madi-disable din
            // ito muli. Ipinapaalam bago tumuloy.
            if (isset($misrouted[$id])) {
                $this->warn("Binubuhay ang {$id} pero MALI PA RIN ang URL nito — malamang na madi-disable ulit ito.");
            }

            try {
                $result = $paymongo->enableWebhook($id);
                $status = $result['attributes']['status'] ?? 'unknown';
                $this->info("Enabled {$id} — status ngayon: {$status}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Hindi ma-enable ang {$id}: ".$e->getMessage());
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Ang `--disable=...` na sangay.
     *
     * Ang `tunnels` ay pinapatay ang bawat enabled na webhook na ang
     * HOST ay wala roon — 5xx mula sa edge, o walang koneksyon. Sa
     * praktika: ang ngrok tunnel ng dev machine kapag hindi tumatakbo.
     *
     * Ang 4xx ay SINASADYANG hindi kasama: may sumasagot doon, kaya
     * buhay ang host at isyu sa code ang dapat ayusin, hindi ang
     * rehistro. Kung 4xx ang isinama, mapapatay nito ang produksyon.
     *
     * @param  list<array<string, mixed>>  $webhooks
     */
    private function disable(PayMongoService $paymongo, string $target, array $webhooks): int
    {
        $toDisable = [];

        if ($target === 'tunnels') {
            foreach ($webhooks as $webhook) {
                $id = $webhook['id'] ?? '';
                $url = $webhook['attributes']['url'] ?? '';

                if (($webhook['attributes']['status'] ?? '') !== 'enabled') {
                    continue;
                }

                $status = $paymongo->probeEndpoint($url);

                // Ang panuntunan ng "patay" ay nasa serbisyo —
                // PayMongoService::endpointIsDead(). Ang 4xx ay
                // SINASADYANG hindi kasama; tingnan doon kung bakit.
                if (! $paymongo->endpointIsDead($status)) {
                    $this->line("May sumasagot sa {$url} (HTTP {$status}) — hindi ginagalaw.");

                    continue;
                }

                $this->warn("Patay ang {$url} (".($status === null ? 'walang koneksyon' : "HTTP {$status}").') — papatayin.');
                $toDisable[] = $id;
            }
        } else {
            $toDisable[] = $target;
        }

        if ($toDisable === []) {
            $this->info('Walang dapat patayin — tumutugon ang lahat ng enabled na webhook.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($toDisable as $id) {
            try {
                $result = $paymongo->disableWebhook($id);
                $status = $result['attributes']['status'] ?? 'unknown';
                $this->info("Disabled {$id} — status ngayon: {$status}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Hindi ma-disable ang {$id}: ".$e->getMessage());
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Ang `--webhook=... --url=...` na sangay.
     *
     * @return int|null exit code, o null para ipagpatuloy ang listahan
     */
    private function repairUrl(PayMongoService $paymongo): ?int
    {
        $id = $this->option('webhook');
        $url = (string) $this->option('url');

        if (! $id) {
            $this->error('Kailangan ng --webhook=<id> kasama ang --url.');

            return self::FAILURE;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error("Hindi wastong URL: {$url}");

            return self::FAILURE;
        }

        try {
            $result = $paymongo->updateWebhook($id, ['url' => $url]);
            $this->info("Updated {$id} → ".($result['attributes']['url'] ?? $url));
            $this->newLine();
        } catch (\Throwable $e) {
            $this->error("Hindi ma-update ang {$id}: ".$e->getMessage());

            return self::FAILURE;
        }

        return null;
    }
}
