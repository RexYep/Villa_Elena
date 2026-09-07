<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    private string $baseUrl = 'https://api.paymongo.com/v1';
    private string $secretKey;
    private string $publicKey;

    public function __construct()
    {
      

        $this->secretKey = config('services.paymongo.secret_key', env('PAYMONGO_SECRET_KEY', ''));
        $this->publicKey = config('services.paymongo.public_key', env('PAYMONGO_PUBLIC_KEY', ''));
    }

    /**
     * Nasa test mode ba tayo?
     *
     * Mahalaga ito dahil ang Send Money ay TAHIMIK na nawawala sa test
     * mode: ang `GET /v2/wallets/` ay sumasagot ng `{"data":[]}` — 200,
     * walang error, walang wallet. Kung wala itong tseke, ang tanging
     * makikita ng admin ay "Could not reach the PayMongo wallet", na
     * mukhang problema sa network gayong ang totoo ay walang wallet ang
     * test mode kailanman.
     */
    public function isTestMode(): bool
    {
        return str_starts_with($this->secretKey, 'sk_test_');
    }

    // ── Create a Payment Link ──────────────────────────────────────
    // Best for resort — send link to guest via email/SMS
    public function createPaymentLink(array $data): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/links", [
                'data' => [
                    'attributes' => [
                        'amount'      => (int) round($data['amount'] * 100), // convert to centavos
                        'description' => $data['description'],
                        'remarks'     => $data['remarks'] ?? '',
                    ]
                ]
            ]);

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: ' . $response->body());
        }

        return $response->json('data');
    }

    // ── Create a Checkout Session ──────────────────────────────────
    // Redirects guest to PayMongo hosted checkout page
    public function createCheckoutSession(array $data): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/checkout_sessions", [
                'data' => [
                    'attributes' => [
                        'billing'           => [
                            'name'  => $data['guest_name'],
                            'email' => $data['guest_email'],
                            'phone' => $data['guest_phone'] ?? '',
                        ],
                        'line_items'        => [
                            [
                                'currency'  => 'PHP',
                                'amount'    => (int) round($data['amount'] * 100),
                                'name'      => $data['description'],
                                'quantity'  => 1,
                            ]
                        ],
                        // QR Ph lang — sakop na nito ang GCash, Maya at
                        // ang mga bank app, sa isang aktibasyon. Ang
                        // paisa-isang pag-e-enable ng 'gcash'/'paymaya'
                        // ay nangangailangan ng bukod na aplikasyon sa
                        // PayMongo bawat isa.
                        'payment_method_types' => ['qrph'],
                        'success_url'       => $data['success_url'],
                        'cancel_url'        => $data['cancel_url'],
                        'reference_number'  => $data['reference_number'],
                        'statement_descriptor' => 'Villa Elena Resort',
                        'metadata'          => [
                            'booking_id'   => $data['booking_id'],
                            'booking_ref'  => $data['reference_number'],
                            'payment_type' => $data['payment_type'] ?? 'deposit',
                        ],
                    ]
                ]
            ]);

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: ' . $response->body());
        }

        return $response->json('data');
    }

    // ── Retrieve a Checkout Session ────────────────────────────────
    public function getCheckoutSession(string $sessionId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}/checkout_sessions/{$sessionId}");

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: ' . $response->body());
        }

        return $response->json('data');
    }

    // ── Retrieve a Payment Link ────────────────────────────────────
    public function getPaymentLink(string $linkId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}/links/{$linkId}");

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: ' . $response->body());
        }

        return $response->json('data');
    }

    // ── Receiving Institutions (para sa refunds via Send Money) ────
    /**
     * Ang mga bangko at e-wallet na kayang TUMANGGAP ng transfer sa
     * napiling rail. Ito ang pinagmumulan ng dropdown na pinipilian ng
     * guest kung saan niya gustong tanggapin ang refund.
     *
     * HINDI ITO HARDCODE, at sinasadya iyon. Ang `bic` na ipinapadala
     * natin sa `destination_account` ay dapat eksaktong tugma sa
     * kinikilala ng PayMongo — at nagbabago ang listahan (dumadagdag
     * ang mga institusyon, may nawawala). Ang isang hardcoded na
     * listahan ay tahimik na mauubos ang bisa, at ang tanging senyales
     * ay isang bumagsak na transfer na may perang nakatengga.
     *
     * Sinasala sa mga may kakayahang `receiver` — may ilang institusyon
     * na `sender` lang, at ang pagpapakita sa kanila ay garantisadong
     * pagkabigo.
     *
     * Dalawa ang cache key nang sinasadya:
     *   - ang normal na 24-oras na cache, at
     *   - isang `.stale` na kopyang hindi nag-e-expire.
     *
     * Kapag hindi maabot ang PayMongo, mas mabuti nang ipakita ang
     * listahan kahapon kaysa sa isang walang lamang dropdown na
     * tahimik na sisira sa form ng guest.
     */
    public function receivingInstitutions(string $provider = 'instapay'): array
    {
        $key      = "paymongo.receiving_institutions.{$provider}";
        $staleKey = "{$key}.stale";

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(15)
                ->get("{$this->baseUrl}/wallets/receiving_institutions", [
                    'provider' => $provider,
                ]);

            if ($response->failed()) {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }

            $list = collect($response->json('data') ?? [])
                ->map(fn ($row) => [
                    'name' => $row['attributes']['name']          ?? '',
                    'bic'  => $row['attributes']['provider_code'] ?? '',
                    'type' => $row['attributes']['type']          ?? [],
                ])
                ->filter(fn ($i) => $i['name'] !== ''
                    && $i['bic'] !== ''
                    && in_array('receiver', (array) $i['type'], true))
                ->map(fn ($i) => ['name' => $i['name'], 'bic' => $i['bic']])
                ->sortBy('name')
                ->values()
                ->all();

            if ($list) {
                Cache::put($key, $list, now()->addDay());
                Cache::forever($staleKey, $list);
            }

            return $list;

        } catch (\Throwable $e) {
            Log::error('PayMongo receiving_institutions lookup failed: ' . $e->getMessage());

            // Puwedeng wala pa ring laman ito sa kauna-unahang tawag —
            // hinahawakan iyon ng controller bilang "pansamantalang
            // hindi available" sa halip na ipakita ang isang blangkong
            // dropdown na mukhang gumagana.
            return Cache::get($staleKey, []);
        }
    }

    // ── Send Money (Disbursements) ─────────────────────────────────

    /**
     * Ang PayMongo Wallet ng merchant — pinagmumulan ng bawat refund.
     *
     * BITAG: hindi ibinibigay ng `GET /v2/wallets/` ang `balance`,
     * `account` at `limits` maliban kung hihingin nang tahasan, at ang
     * `fields` ay INUULIT na parameter, hindi comma-separated. Wala
     * sila sa payload — hindi null — kaya ang pagbasa ng
     * `$w['balance']['available']` nang wala ito ay tahimik na
     * nagbibigay ng 0 at kamukhang-kamukha ng walang lamang wallet.
     *
     * Kailangan din ang trailing slash; 301 kung wala.
     *
     * ANG QUERY STRING AY SINASADYANG NAKASULAT NANG BUO.
     *
     * Ang pagpasa ng `['fields' => ['balance', 'account']]` bilang
     * array ay hindi gumagana: ginagamit ng Laravel ang
     * `http_build_query()`, na gumagawa ng
     * `fields[0]=balance&fields[1]=account`. Binabalewala iyon ng
     * PayMongo at ibinabalik ang wallet na WALANG `account` — na
     * kamukhang-kamukha ng isang wallet na walang source account.
     * Nangyari na ito: `"The PayMongo wallet has no source account"`
     * sa isang wallet na may laman naman.
     */
    public function wallet(): ?array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(20)
            ->get('https://api.paymongo.com/v2/wallets/?fields=balance&fields=account&fields=limits');

        if ($response->failed()) {
            Log::error('PayMongo wallet lookup failed: HTTP ' . $response->status() . ' ' . $response->body());
            return null;
        }

        $wallets = $response->json('data') ?? [];

        // Puwedeng listahan o iisang object, depende sa endpoint.
        if (isset($wallets['id'])) {
            $wallets = [$wallets];
        }

        return $wallets[0] ?? null;
    }

    /**
     * Available na balanse ng wallet, sa piso.
     *
     * `null` kapag hindi maabot ang PayMongo — sinasadyang IBA ito sa
     * `0.0`. Ang zero ay "wala kang pera"; ang null ay "hindi ko
     * alam". Ang pagsasama ng dalawa ay magdudulot ng maling
     * "kulang ang balanse" tuwing may hiccup ang network.
     */
    public function walletBalance(): ?float
    {
        $wallet = $this->wallet();

        if ($wallet === null || ! isset($wallet['balance']['available'])) {
            return null;
        }

        return ((int) $wallet['balance']['available']) / 100;
    }

    /**
     * Nagpapadala ng ISANG transfer sa pamamagitan ng InstaPay.
     *
  
     * @param  array  $destination  ['number' => …, 'name' => …, 'bic' => …]
     */
    public function sendTransfer(float $amount, array $destination, string $referenceNumber, string $description = '', ?string $callbackUrl = null, string $provider = 'instapay', string $purpose = ''): array
    {
        $wallet = $this->wallet();

        if ($wallet === null) {
            throw new \RuntimeException('Could not reach the PayMongo wallet. Nothing was sent.');
        }

        $account = $wallet['account'] ?? [];

        if (empty($account['account_number']) || empty($account['account_name'])) {
            throw new \RuntimeException('The PayMongo wallet has no source account. Nothing was sent.');
        }

        $payload = [
            'source_account' => [
                'number' => $account['account_number'],
                'name'   => $account['account_name'],
               
                'bic'    => 'PAEYPHM2XXX',
            ],
            'destination_account' => [
                'number' => $destination['number'],
                'name'   => $destination['name'],
                'bic'    => $destination['bic'],
            ],
            'amount'           => (int) round($amount * 100),
            'currency'         => 'PHP',
            'provider'         => $provider,
            'reference_number' => $referenceNumber,
            'description'      => $description,
        ];

        if ($purpose !== '') {
            $payload['purpose'] = $purpose;
        }

        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(45)
            ->post('https://api.paymongo.com/v2/batch_transfers', [
                'transfers' => [$payload],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'PayMongo rejected the transfer request (HTTP ' . $response->status() . '): ' . $response->body()
            );
        }

        $transfer = $response->json('data.transfers.0');

        if (! is_array($transfer) || empty($transfer['id'])) {
            throw new \RuntimeException('PayMongo returned no transfer. Response: ' . $response->body());
        }

        return $transfer;
    }

    /**
     * Ang kasalukuyang estado ng isang transfer.
     *
     * Dito lumalabas ang `provider_error_code` at ang TUNAY nang
     * `provider_reference_number` — sa unang sagot ay echo lamang iyon
     * ng sarili nating reference.
     */
    public function getTransfer(string $transferId): ?array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(20)
            ->get("https://api.paymongo.com/v2/transfers/{$transferId}");

        if ($response->failed()) {
            Log::error("PayMongo transfer lookup failed for {$transferId}: HTTP "
                . $response->status() . ' ' . $response->body());
            return null;
        }

        return $response->json('data');
    }

    // ── Handle Webhook ─────────────────────────────────────────────
    /**
     * Bineberipika ang `Paymongo-Signature` header ng isang webhook.
     *
     * Ganito ang aktwal na ipinapadala ng PayMongo:
     *
     *     Paymongo-Signature: t=1496734173,te=5f1a3b...,li=9c2d7e...
     *
     * kung saan `t` ang timestamp, `te` ang test-mode na signature, at
     * `li` ang live-mode. Ang pinipirmahan ay HINDI ang raw body lang —
     * ito ay `"{timestamp}.{rawBody}"`, HMAC-SHA256 gamit ang webhook
     * secret.
     *
     * Dating hino-hash lang nito ang raw body tapos ikinukumpara sa
     * BUONG header string. Imposibleng magtugma iyon, kaya lahat ng
     * tunay na PayMongo event ay 401 sana — tahimik na hindi tatakbo
     * ang webhook kahit tama ang pagkaka-setup nito sa dashboard.
     *
     * MAGKAIBA ANG SECRET NG BAWAT MODE. Ang isang test-mode na webhook
     * at ang live-mode na katapat nito ay dalawang magkahiwalay na
     * rehistro sa PayMongo, bawat isa'y may sariling `whsk_...`. Dati,
     * iisang secret ang isinusukat natin sa parehong `te` at `li` slot,
     * kaya ang pagtuturo ng test-mode na webhook sa isang deployment na
     * may hawak na live secret ay nangangahulugang BIGO ang signature ng
     * BAWAT delivery — at pagkaraan ng ilang sunod-sunod na 401, dini-
     * disable ng PayMongo ang endpoint nang tuluyan. Iyon mismo ang
     * nangyari (v6.1).
     *
     * Kaya bawat slot ngayon ay may sariling listahan ng kandidatong
     * secret: ang mode-specific muna, tapos ang generic na
     * `PAYMONGO_WEBHOOK_SECRET` bilang fallback (kaya walang nasisira sa
     * mga setup na iisa lang ang secret). Kapag nakatakda ang pareho,
     * puwedeng magkasabay na naka-rehistro ang test at live na webhook
     * sa iisang URL at pareho silang beripikado.
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        // Header → ['t' => ..., 'te' => ..., 'li' => ...]
        $parts = [];
        foreach (explode(',', $signature) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? null;

        if (! $timestamp) {
            return false;
        }

        foreach ($this->webhookSecretsByMode() as $mode => $secrets) {
            if (! isset($parts[$mode])) {
                continue;
            }

            foreach ($secrets as $secret) {
                $computed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

                if (hash_equals($computed, $parts[$mode])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Aling secret ang susubukan sa aling signature slot.
     *
     * @return array<string, list<string>>
     */
    private function webhookSecretsByMode(): array
    {
        $generic = (string) config('services.paymongo.webhook_secret', '');
        $test    = (string) config('services.paymongo.webhook_secret_test', '');
        $live    = (string) config('services.paymongo.webhook_secret_live', '');

        return [
            'te' => array_values(array_unique(array_filter([$test, $generic]))),
            'li' => array_values(array_unique(array_filter([$live, $generic]))),
        ];
    }

    /**
     * May nakatakda bang kahit isang webhook secret?
     *
     * Ginagamit ng webhook handler para pag-ibahin ang "mali ang secret"
     * sa "walang secret" sa log — magkaiba ang lunas ng dalawa.
     */
    public function hasWebhookSecret(): bool
    {
        foreach ($this->webhookSecretsByMode() as $secrets) {
            if ($secrets !== []) {
                return true;
            }
        }

        return false;
    }

    // ── Webhook administration ─────────────────────────────────────
    /**
     * Inililista ang mga naka-rehistrong webhook para sa mode ng key
     * na hawak natin ngayon (test keys → test webhooks lang).
     *
     * @return list<array<string, mixed>>
     */
    public function listWebhooks(): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}/webhooks");

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: '.$response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Binabago ang URL at/o events ng isang naka-rehistrong webhook.
     *
     * Bakit kailangan: ang URL ng isang webhook ay itinatakda sa
     * paggawa nito at madaling mali — kung nakarehistro ito sa bare
     * origin (`https://halimbawa.com`) sa halip na sa buong path
     * (`https://halimbawa.com/webhooks/paymongo`), ang bawat delivery ay
     * POST sa `/`, na 405 Method Not Allowed sa isang route na GET lang
     * — at pagkaraan ng ilang ganoon, dini-disable ito ng PayMongo.
     * Iyon mismo ang nangyari sa produksyon (v6.1). Ang pagbuhay dito
     * nang hindi inaayos muna ang URL ay agad ding madi-disable ulit.
     *
     * @param  array<string, mixed>  $attributes  `url` at/o `events`
     * @return array<string, mixed>
     */
    public function updateWebhook(string $webhookId, array $attributes): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->put("{$this->baseUrl}/webhooks/{$webhookId}", [
                'data' => ['attributes' => $attributes],
            ]);

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: '.$response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Muling binubuhay ang isang na-disable na webhook.
     *
     * Dini-disable ng PayMongo ang isang endpoint kapag paulit-ulit
     * itong sumagot ng 4xx/5xx, at HINDI ito bumabalik nang kusa —
     * kailangang tawagin ang endpoint na ito (o pindutin ito sa
     * dashboard). Habang naka-disable, walang darating na kahit anong
     * event: tahimik na hindi naitatala ang lahat ng bayad.
     *
     * @return array<string, mixed>
     */
    public function enableWebhook(string $webhookId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/webhooks/{$webhookId}/enable");

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: '.$response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Pansamantalang pinapatay ang isang webhook.
     *
     * Para ito sa mga endpoint na hindi laging bukas — pangunahin, ang
     * ngrok tunnel ng dev machine. Habang naka-rehistro at enabled ito,
     * ipinapadala pa rin ng PayMongo ang BAWAT test-mode na event doon,
     * kasama ang mga galing sa produksyon; at kapag hindi tumatakbo ang
     * tunnel, 502 ang isinasagot ng ngrok edge sa bawat isa. Iyon ang
     * eksaktong uri ng bilang na nagpapa-disable ng webhook — kaya mas
     * mabuting sadyain ang pagpatay kaysa hintaying gawin ito ng
     * PayMongo sa panahong hindi natin alam.
     *
     * @return array<string, mixed>
     */
    public function disableWebhook(string $webhookId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/webhooks/{$webhookId}/disable");

        if ($response->failed()) {
            throw new \Exception('PayMongo Error: '.$response->body());
        }

        return $response->json('data') ?? [];
    }}