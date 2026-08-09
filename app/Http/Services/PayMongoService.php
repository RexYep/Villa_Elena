<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    private string $secretKey;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->secretKey     = config('services.paymongo.secret_key');
        $this->webhookSecret = config('services.paymongo.webhook_secret');
    }

    /**
     * Create a PayMongo Checkout Session.
     *
     * Expects: amount (PHP, not centavos), description, guest_name,
     * guest_email, guest_phone, reference_number, booking_id,
     * payment_type, success_url, cancel_url.
     */
    public function createCheckoutSession(array $data): array
    {
        $amountCentavos = (int) round($data['amount'] * 100);

        $payload = [
            'data' => [
                'attributes' => [
                    'send_email_receipt' => true,
                    'show_description'   => true,
                    'show_line_items'    => true,
                    'description'        => $data['description'],
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount'   => $amountCentavos,
                        'name'     => $data['description'],
                        'quantity' => 1,
                    ]],
                    // E-wallets only — card and online banking removed per request.
                    // PayMongo e-wallet types: 'gcash', 'paymaya', 'grab_pay'.
                    'payment_method_types' => ['gcash', 'paymaya', 'grab_pay'],
                    'success_url' => $data['success_url'],
                    'cancel_url'  => $data['cancel_url'],
                    'reference_number' => $data['reference_number'],
                    'billing' => [
                        'name'  => $data['guest_name'],
                        'email' => $data['guest_email'],
                        'phone' => $data['guest_phone'] ?? '',
                    ],
                    // metadata is echoed back on payment.paid webhook events —
                    // this is how the webhook handler maps a payment back to a booking.
                    'metadata' => [
                        'booking_id'   => (string) $data['booking_id'],
                        'payment_type' => $data['payment_type'],
                    ],
                ],
            ],
        ];

        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->post("{$this->baseUrl}/checkout_sessions", $payload);

        if ($response->failed()) {
            Log::error('PayMongo createCheckoutSession failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            throw new \Exception('Unable to create payment checkout session. Please try again.');
        }

        return $response->json('data');
    }

    /**
     * Retrieve a Checkout Session by ID — used server-side to verify
     * actual payment status instead of trusting the browser redirect.
     */
    public function getCheckoutSession(string $sessionId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->get("{$this->baseUrl}/checkout_sessions/{$sessionId}");

        if ($response->failed()) {
            Log::error('PayMongo getCheckoutSession failed', [
                'session_id' => $sessionId,
                'status'     => $response->status(),
                'body'       => $response->json(),
            ]);
            throw new \Exception('Unable to verify payment status.');
        }

        return $response->json('data');
    }

    /**
     * Verify a PayMongo webhook signature (HMAC-SHA256).
     *
     * Header format: "t=<timestamp>,te=<test_signature>,li=<live_signature>"
     * We sign "{timestamp}.{raw_payload}" with the webhook secret and
     * compare against whichever signature matches the current mode.
     */
    public function verifyWebhook(string $payload, string $signatureHeader): bool
    {
        if (empty($signatureHeader) || empty($this->webhookSecret)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = array_pad(explode('=', $part, 2), 2, null);
            $parts[$pair[0]] = $pair[1];
        }

        $timestamp = $parts['t'] ?? null;
        if (!$timestamp) {
            return false;
        }

        // Use 'li' (live) signature if present, otherwise fall back to 'te' (test).
        // PayMongo sends both; only one will actually match your secret's mode.
        $isLiveKey = str_starts_with($this->secretKey, 'sk_live_');
        $signature = $isLiveKey ? ($parts['li'] ?? null) : ($parts['te'] ?? null);

        if (!$signature) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        // hash_equals() prevents timing-attack based signature guessing
        return hash_equals($expected, $signature);
    }
}