<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The app's single door to a language model. Every AI feature — chatbot,
 * review moderation, insights, forecast, prescriptive briefing — calls ask()
 * and nothing else.
 *
 * The provider is chosen by `AI_PROVIDER` (`config('services.ai.provider')`):
 * `groq` (the default) or `gemini`. Switching is a `.env` change plus
 * `php artisan config:clear` — never an edit to this file. Before this switch
 * existed, testing Gemini meant replacing this file's contents, which is how
 * the working Groq implementation got overwritten and the Gemini replacement
 * shipped with a URL that glued the API key onto the hostname.
 *
 * The class name is legacy: it has called Groq since v5.x and now calls
 * whichever provider is configured.
 */
class GeminiService
{
    private const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    private const GEMINI_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * Returns the model's reply, or NULL when the call failed or came back empty.
     *
     * NULL, never the provider's error text. Every caller renders what it gets,
     * so returning "API Error: 429 — {...}" as the reply puts Groq's raw body —
     * model id, org id, rate-limit internals — into a guest's chat bubble and
     * into the admin panel, and reads as if the villa itself said it. The detail
     * belongs in the log; the caller decides what the user sees.
     *
     * Same contract for every provider. Callers must never need to know which
     * one answered.
     */
    public function ask(string $prompt, int $maxTokens = 1024): ?string
    {
        $provider = config('services.ai.provider', 'groq');

        try {
            return match ($provider) {
                'groq' => $this->askGroq($prompt, $maxTokens),
                'gemini' => $this->askGemini($prompt, $maxTokens),
                default => $this->unknownProvider($provider),
            };
        } catch (ConnectionException $e) {
            // DNS failure, refused connection, timeout. Log the class and the
            // provider, NOT $e->getMessage(): cURL puts the full request URL in
            // it, and a URL is exactly where an API key ends up when a request
            // is built wrong — the broken Gemini version wrote most of its key
            // into laravel.log this way.
            Log::error('AI provider unreachable', [
                'provider' => $provider,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    // ── Groq ────────────────────────────────────────────────────────────

    private function askGroq(string $prompt, int $maxTokens): ?string
    {
        $key = config('services.groq.key');
        $model = (string) config('services.groq.model');

        if (blank($key)) {
            Log::error('AI_PROVIDER is groq but GROQ_API_KEY is not set');

            return null;
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        $effort = $this->groqReasoningEffort($model);
        if ($effort !== null) {
            $payload['reasoning_effort'] = $effort;
        }

        $response = $this->sendGroq($key, $payload);

        // Groq rejects reasoning_effort with a 400 whenever the configured model
        // uses a different vocabulary than we guessed (or supports none at all).
        // Retry once without it rather than failing the whole feature — this is
        // what keeps swapping GROQ_MODEL a one-line change.
        if ($response->status() === 400 && str_contains($response->body(), 'reasoning_effort')) {
            Log::warning('Groq rejected reasoning_effort; retrying without it', [
                'model' => $model,
                'sent' => $effort,
                'body' => $response->body(),
            ]);

            unset($payload['reasoning_effort']);
            $response = $this->sendGroq($key, $payload);
        }

        if ($response->failed()) {
            Log::error('Groq API call failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $content = $this->stripReasoning((string) $response->json('choices.0.message.content', ''));

        if ($content === '') {
            Log::warning('Groq API returned an empty reply', [
                'model' => $model,
            ]);

            return null;
        }

        return $content;
    }

    private function sendGroq(string $key, array $payload): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->post(self::GROQ_ENDPOINT, $payload);
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
    private function groqReasoningEffort(string $model): ?string
    {
        $configured = config('services.groq.reasoning_effort');

        if (filled($configured)) {
            return $configured === 'omit' ? null : $configured;
        }

        return match (true) {
            str_starts_with($model, 'openai/gpt-oss') => 'low',
            str_starts_with($model, 'qwen/') => 'none',
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

    // ── Gemini ──────────────────────────────────────────────────────────

    private function askGemini(string $prompt, int $maxTokens): ?string
    {
        $key = config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if (blank($key)) {
            Log::error('AI_PROVIDER is gemini but GEMINI_API_KEY is not set');

            return null;
        }

        // Deliberately NO `responseMimeType: application/json`. The broken
        // Gemini version forced it on every call, and JSON mode wraps plain-text
        // answers: moderation came back as `"CLEAN"` (quotes included, so the
        // `starts with CLEAN` check failed and every review went to the manual
        // queue) and insights as a JSON array. The one caller that wants JSON —
        // the chatbot's intent step — asks for it in its prompt and parsed
        // cleanly 3/3 on gemini-3.6-flash without JSON mode.
        $generationConfig = [
            'maxOutputTokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        $thinkingLevel = $this->geminiThinkingLevel();
        if ($thinkingLevel !== null) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $thinkingLevel];
        }

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => $generationConfig,
        ];

        $response = $this->sendGemini($key, $model, $payload);

        // An unsupported thinking setting comes back as a bare
        // "Request contains an invalid argument." — it doesn't name the field
        // (measured: `thinkingBudget: 0` on gemini-3.6-flash), so unlike Groq we
        // can't match on the message. Retry once without thinkingConfig on any
        // 400 when we sent one; a 400 that survives the retry is a real error.
        if ($response->status() === 400 && isset($payload['generationConfig']['thinkingConfig'])) {
            Log::warning('Gemini returned 400 with thinkingConfig; retrying without it', [
                'model' => $model,
                'sent' => $thinkingLevel,
                'body' => $response->body(),
            ]);

            unset($payload['generationConfig']['thinkingConfig']);
            $response = $this->sendGemini($key, $model, $payload);
        }

        if ($response->failed()) {
            // A retired model answers 404 here even while GET /models still
            // lists it — gemini-2.5-flash did exactly that. The body names the
            // replacement, so it is worth logging in full.
            Log::error('Gemini API call failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $candidate = $response->json('candidates.0');

        if ($candidate === null) {
            // No candidate at all means the prompt itself was blocked.
            Log::warning('Gemini returned no candidate', [
                'model' => $model,
                'block_reason' => $response->json('promptFeedback.blockReason'),
            ]);

            return null;
        }

        // A reply can hold several parts, and a part can carry fields besides
        // `text` (every reply measured so far carries a `thoughtSignature`).
        // Take the text of every part that isn't itself a thought.
        $content = trim(collect($candidate['content']['parts'] ?? [])
            ->reject(fn (array $part) => $part['thought'] ?? false)
            ->pluck('text')
            ->filter()
            ->implode(''));

        $finish = $candidate['finishReason'] ?? null;

        if ($finish === 'MAX_TOKENS') {
            // Returned anyway, same as Groq does with a truncated reply, but
            // flagged: on Gemini this is usually thinking having eaten the
            // budget rather than the answer being long.
            Log::warning('Gemini reply hit maxOutputTokens', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'thoughts_tokens' => $response->json('usageMetadata.thoughtsTokenCount'),
                'output_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
            ]);
        }

        if ($content === '') {
            Log::warning('Gemini API returned an empty reply', [
                'model' => $model,
                'finish_reason' => $finish,
            ]);

            return null;
        }

        return $content;
    }

    private function sendGemini(string $key, string $model, array $payload): Response
    {
        // Key in the `x-goog-api-key` header, never in the URL. A key in a URL
        // is a key in every error message, proxy log and exception that
        // mentions the URL.
        return Http::withHeaders([
            'x-goog-api-key' => $key,
            'Content-Type' => 'application/json',
        ])->timeout(90)->post(sprintf(self::GEMINI_ENDPOINT, rawurlencode($model)), $payload);
    }

    /**
     * Gemini's thinking tokens count against `maxOutputTokens`, the same trap as
     * Groq's hidden reasoning. Measured on gemini-3.6-flash with a 500-token cap
     * (the prescriptive briefing's limit): the default and `low` both spent ~476
     * tokens thinking and returned a ~100-character fragment; `minimal` spent 0
     * and returned the whole answer. Hence `minimal` by default.
     *
     * `GEMINI_THINKING_LEVEL` overrides it; `omit` sends no thinkingConfig.
     * Don't try `thinkingBudget: 0` — that 400s on the 3.x models.
     */
    private function geminiThinkingLevel(): ?string
    {
        $configured = config('services.gemini.thinking_level');

        if (filled($configured)) {
            return $configured === 'omit' ? null : $configured;
        }

        return 'minimal';
    }

    private function unknownProvider(mixed $provider): ?string
    {
        Log::error('Unknown AI_PROVIDER; expected groq or gemini', [
            'provider' => $provider,
        ]);

        return null;
    }
}
