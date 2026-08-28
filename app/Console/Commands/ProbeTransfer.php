<?php

namespace App\Console\Commands;

use App\Services\PayMongoService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Isang laboratoryo para sa Send Money — TUNAY na pera, kontroladong payload.
 *
 * Bakit ito umiiral: ang tanong kung bakit tinatanggihan ng GCash ang
 * mga transfer natin ay nasasagot lang sa pamamagitan ng pagpapadala ng
 * mga payload na magkaiba sa IISANG field. Hindi kaya iyon ng Send
 * button ng admin — nakakabit ito sa isang tunay na refund, at ang
 * `description` at `reference_number` ay ginagawa ng code, hindi
 * pinipili. Ang paglalagay ng probe knobs doon ay maglalagay ng
 * pang-eksperimentong code sa daanan ng tunay na pera.
 *
 * WALANG hinahawakang refund, payment o transfer row ang command na ito.
 * Direkta itong dumadaan sa PayMongo at ang sagot ay ipinipinta lang sa
 * screen — kaya walang maiiwang maling tala kapag nagkamali ang probe.
 *
 * Libre ang bigong transfer; ang matagumpay ay may ₱10 fee. Kaya mura
 * ang mali, at iyon ang dahilan kung bakit puwedeng magtanong nang paisa-isa.
 */
class ProbeTransfer extends Command
{
    protected $signature = 'paymongo:probe-transfer
        {amount : Halaga sa piso, hal. 6}
        {--to= : Account/mobile number ng tatanggap}
        {--name= : Pangalang nakarehistro sa account}
        {--bic=GXCHPHM2XXX : Institution BIC (GCash ang default)}
        {--purpose=own-account : Ang purpose code}
        {--description= : Description; default ay katulad ng purpose, gaya ng dashboard}
        {--reference= : Reference; default ay 24-character lowercase hex, gaya ng dashboard}
        {--provider=instapay : instapay o pesonet}
        {--force : Laktawan ang pagtatanong}';

    protected $description = 'Magpadala ng isang kontroladong Send Money transfer para sukatin ang isang variable (TUNAY na pera).';

    public function handle(PayMongoService $paymongo): int
    {
        $amount = (float) $this->argument('amount');
        $number = $this->option('to');
        $name   = $this->option('name');

        if (! $number || ! $name) {
            $this->error('Kailangan ang --to at --name.');

            return self::FAILURE;
        }

        $purpose = (string) $this->option('purpose');

        // Ang default ay ang hugis na napatunayang gumagana mula sa
        // dashboard: ang `description` ay kaparehong-kapareho ng
        // `purpose`, at ang reference ay 24 na lowercase hex — walang
        // uppercase, walang gitling.
        $description = ((string) $this->option('description')) ?: $purpose;
        $reference   = ((string) $this->option('reference')) ?: Str::lower(Str::random(24));

        $this->table(['Field', 'Halaga'], [
            ['amount', '₱' . number_format($amount, 2)],
            ['destination.number', $number],
            ['destination.name', $name],
            ['destination.bic', $this->option('bic')],
            ['provider', $this->option('provider')],
            ['purpose', $purpose],
            ['description', $description],
            ['reference_number', $reference],
        ]);

        if (! $this->option('force') && ! $this->confirm('Ipadala ito? Tunay na pera ito.', false)) {
            $this->line('Walang ipinadala.');

            return self::SUCCESS;
        }

        try {
            $sent = $paymongo->sendTransfer(
                amount:          $amount,
                destination:     ['number' => $number, 'name' => $name, 'bic' => $this->option('bic')],
                referenceNumber: $reference,
                description:     $description,
                callbackUrl:     null,
                provider:        (string) $this->option('provider'),
                purpose:         $purpose,
            );
        } catch (\Throwable $e) {
            $this->error('Hindi tinanggap ng PayMongo ang request: ' . $e->getMessage());

            return self::FAILURE;
        }

        $id = $sent['id'] ?? null;
        $this->info("Tinanggap ang utos: {$id}");

        // Ang `201` ay hindi nangangahulugang dumating ang pera —
        // ~2s bago sumagot ang tumatanggap na institusyon.
        $data = null;

        for ($i = 0; $i < 10; $i++) {
            sleep(1);
            $data = $paymongo->getTransfer($id);

            if (($data['status'] ?? 'pending') !== 'pending') {
                break;
            }

            $this->line('  … pending');
        }

        $status = $data['status'] ?? 'pending';

        $this->newLine();
        $this->line('status   : ' . $status);
        $this->line('code     : ' . ($data['provider_error_code'] ?? '—'));
        $this->line('message  : ' . ($data['provider_error_message'] ?? '—'));
        $this->line('sub_code : ' . ($data['metadata']['sub_code'] ?? '—'));
        $this->line('fee      : ' . ($status === 'succeeded' ? '₱' . number_format(($data['fee'] ?? 0) / 100, 2) : '₱0.00 (libre ang bigo)'));

        $status === 'succeeded'
            ? $this->info('DUMATING. Isulat ang variable na ito sa project.md.')
            : $this->warn('HINDI dumating. Walang sinisingil. Palitan ang ISANG variable at ulitin.');

        return self::SUCCESS;
    }
}
