<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'brevo' => [
        'dsn' => env('MAILER_DSN'),
    ],
    
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paymongo' => [
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        // Ang webhook secret ay PER-MODE: magkaiba ang ibinibigay ng
        // PayMongo sa isang test-mode na webhook at sa isang live-mode.
        // Dati ay iisang slot lang ito, kaya ang pagpapatakbo ng test-
        // mode na webhook sa isang live-key na deployment (o kabaligtaran)
        // ay nangangahulugang BIGO ang signature ng BAWAT delivery — at
        // dini-disable ng PayMongo ang endpoint kapag paulit-ulit iyon.
        // Puwede nang magkasabay ang dalawa; tingnan ang verifyWebhook().
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET', ''),
        'webhook_secret_test' => env('PAYMONGO_WEBHOOK_SECRET_TEST', ''),
        'webhook_secret_live' => env('PAYMONGO_WEBHOOK_SECRET_LIVE', ''),

        // Aling webhook ang sinusundan ng `paymongo:tunnel`. Kung
        // blangko, hinuhulaan ito mula sa host na ibinibigay ng ngrok.
        'tunnel_webhook' => env('PAYMONGO_TUNNEL_WEBHOOK', ''),

        // Ang `purpose` ng bawat Send Money transfer. Tingnan ang
        // `RefundTransfer::PURPOSE` — ito ang field na nagpapasya kung
        // tatanggapin ng GCash ang isang refund, at nasa env ito para
        // masubukan ang ibang halaga nang hindi nagde-deploy.
        // Ang `?:` ay sinasadya: ang blangkong `PAYMONGO_TRANSFER_PURPOSE=`
        // sa isang `.env` ay nagbibigay ng "", hindi null, at ang blangkong
        // purpose ang eksaktong bagay na tinatanggihan ng GCash.
        'transfer_purpose' => env('PAYMONGO_TRANSFER_PURPOSE') ?: \App\Models\RefundTransfer::PURPOSE,
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),

        // Groq decommissions models on a rolling basis (llama-3.1-8b-instant is
        // already gone). Keep this env-overridable so the next retirement is a
        // config change, not a code deploy. Live list: GET /openai/v1/models.
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        // Leave unset: GeminiService derives the right value per model family,
        // because they disagree (gpt-oss: low/medium/high, qwen: none/default,
        // compound: unsupported). Override only to force one; 'omit' skips it.
        'reasoning_effort' => env('GROQ_REASONING_EFFORT'),
    ],

];
