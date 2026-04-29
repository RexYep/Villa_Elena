<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
                        'payment_method_types' => ['gcash', 'card', 'paymaya', 'grab_pay', 'dob', 'brankas_landbank', 'brankas_metrobank'],
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

    // ── Handle Webhook ─────────────────────────────────────────────
    public function verifyWebhook(string $payload, string $signature): bool
    {
        $webhookSecret = config('services.paymongo.webhook_secret', env('PAYMONGO_WEBHOOK_SECRET', ''));
        $computedSig   = hash_hmac('sha256', $payload, $webhookSecret);
        return hash_equals($computedSig, $signature);
    }
}