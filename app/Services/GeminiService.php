<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    protected string $apiKey;
    protected string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('services.groq.key');
        $this->endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    }

    public function ask(string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post($this->endpoint, [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ]
            ],
            'max_tokens'  => 1024,
            'temperature' => 0.7,
        ]);

        if ($response->failed()) {
            return 'API Error: ' . $response->status() . ' — ' . $response->body();
        }

        return $response->json('choices.0.message.content', 'No insights returned.');
    }
}