<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;

    protected string $endpoint;

    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.groq.key');
        $this->endpoint = 'https://api.groq.com/openai/v1/chat/completions';
        $this->model = config('services.groq.model');
    }

    /**
     * Returns the model's reply, or NULL when the call failed or came back empty.
     *
     * NULL, never the provider's error text. Every caller renders what it gets,
     * so returning "API Error: 429 — {...}" as the reply puts Groq's raw body —
     * model id, org id, rate-limit internals — into a guest's chat bubble and
     * into the admin panel, and reads as if the villa itself said it. The detail
     * belongs in the log; the caller decides what the user sees.
     */
    public function ask(string $prompt, int $maxTokens = 1024): ?string
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        $effort = $this->reasoningEffort();
        if ($effort !== null) {
            $payload['reasoning_effort'] = $effort;
        }

        $response = $this->send($payload);

        // Groq rejects reasoning_effort with a 400 whenever the configured model
        // uses a different vocabulary than we guessed (or supports none at all).
        // Retry once without it rather than failing the whole feature — this is
        // what keeps swapping GROQ_MODEL a one-line change.
        if ($response->status() === 400 && str_contains($response->body(), 'reasoning_effort')) {
            Log::warning('Groq rejected reasoning_effort; retrying without it', [
                'model' => $this->model,
                'sent' => $effort,
                'body' => $response->body(),
            ]);

            unset($payload['reasoning_effort']);
            $response = $this->send($payload);
        }

        if ($response->failed()) {
            Log::error('Groq API call failed', [
                'model' => $this->model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $content = $this->stripReasoning((string) $response->json('choices.0.message.content', ''));

        if ($content === '') {
            Log::warning('Groq API returned an empty reply', [
                'model' => $this->model,
            ]);

            return null;
        }

        return $content;
    }

    private function send(array $payload): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, $payload);
    }

    /**
     * Groq's models disagree on what `reasoning_effort` accepts — gpt-oss wants
     * low/medium/high, qwen wants none/default, and the compound models reject
     * the parameter outright. Derive it from the model id so changing GROQ_MODEL
     * doesn't 400, and let GROQ_REASONING_EFFORT override ('omit' skips it).
     *
     * Either way the goal is the same: keep hidden reasoning short. It is billed
     * against max_tokens, so at full effort the answer comes back truncated.
     */
    private function reasoningEffort(): ?string
    {
        $configured = config('services.groq.reasoning_effort');

        if (filled($configured)) {
            return $configured === 'omit' ? null : $configured;
        }

        return match (true) {
            str_starts_with($this->model, 'openai/gpt-oss') => 'low',
            str_starts_with($this->model, 'qwen/') => 'none',
            default => null,
        };
    }

    /**
     * Some reasoning models inline their scratchpad in the message content as a
     * <think> block instead of a separate field. Callers here parse the reply as
     * JSON or as an exact one-line verdict, so strip it before handing it back.
     */
    private function stripReasoning(string $content): string
    {
        return trim(preg_replace('/<think>.*?<\/think>/s', '', $content));
    }
}
