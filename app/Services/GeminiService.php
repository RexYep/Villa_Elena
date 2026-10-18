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
     * Why the last ask() returned NULL, read through lastFailure().
     *
     * This is NOT a return value and must never become one: ask() returns the
     * reply or NULL, and nothing else (v7.5 — it used to return
     * "API Error: 429 — {...}", which every caller then rendered, putting
     * Groq's raw body in front of a guest). A separate accessor can be ignored
     * safely, which is the whole difference: a caller that doesn't ask still
     * gets its own wording, while a sentinel string is a guard every caller
     * has to remember to string-match.
     *
     * The distinction that earns its keep is RATE_LIMITED versus the rest.
     * The free Groq tier is 8,000 tokens per minute per model, shared by the
     * chatbot, review moderation and the three admin reports, and one chatbot
     * message costs ~3,200 of them — so "over the limit, try again shortly"
     * is a routine and self-healing state, while every other failure needs a
     * person. Telling a guest "I'm having trouble replying" when the truth is
     * "ask me again in 20 seconds" sends them away for nothing.
     */
    public const FAILURE_RATE_LIMITED = 'rate_limited';

    public const FAILURE_UNAVAILABLE = 'unavailable';

    public const FAILURE_REFUSED = 'refused';

    public const FAILURE_EMPTY = 'empty';

    public const FAILURE_MISCONFIGURED = 'misconfigured';

    private ?string $failure = null;

    private ?int $retryAfter = null;

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
     *
     * `$temperature` defaults to 0.7 — the value every caller ran at before it
     * was a parameter — so moderation, insights, forecast and the prescriptive
     * briefing are unaffected. Lower it for factual-lookup work: the chatbot's
     * intent extractor sends 0.0 (it emits strict JSON and creativity there is
     * only a parse failure waiting to happen) and Elena's reply sends 0.3,
     * because a chattier setting is what let her invent a WiFi password.
     */
    public function ask(string $prompt, int $maxTokens = 1024, float $temperature = 0.7): ?string
    {
        $provider = config('services.ai.provider', 'groq');

        // Cleared per call, so lastFailure() always describes the call that
        // just returned — which is the only thing a caller may read it for.
        // The chatbot makes two in one request (intent, then reply) and reads
        // it after BOTH: after the intent call to decide whether the reply
        // call is worth making at all (ChatbotController::
        // intentFailureIsTerminal(), v7.78), and after the reply call to word
        // the apology. So neither outcome may be inferred from the other.
        $this->failure = null;
        $this->retryAfter = null;

        try {
            return match ($provider) {
                'groq' => $this->askGroq($prompt, $maxTokens, $temperature),
                'gemini' => $this->askGemini($prompt, $maxTokens, $temperature),
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

            return $this->fail(self::FAILURE_UNAVAILABLE);
        }
    }

    /**
     * Why the last ask() returned NULL, or NULL when it succeeded.
     *
     * @return self::FAILURE_*|null
     */
    public function lastFailure(): ?string
    {
        return $this->failure;
    }

    /** True when the last ask() failed because the provider's limit was hit. */
    public function rateLimited(): bool
    {
        return $this->failure === self::FAILURE_RATE_LIMITED;
    }

    /**
     * How long to wait, in whole seconds, when the provider said — or NULL when
     * it didn't. Never 0: a caller prints this, and "try again in 0 seconds"
     * reads as a bug (same rule as ThrottlesAiRefresh).
     */
    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfter;
    }

    // ── Groq ────────────────────────────────────────────────────────────

    private function askGroq(string $prompt, int $maxTokens, float $temperature): ?string
    {
        $key = config('services.groq.key');
        $model = (string) config('services.groq.model');

        if (blank($key)) {
            Log::error('AI_PROVIDER is groq but GROQ_API_KEY is not set');

            return $this->fail(self::FAILURE_MISCONFIGURED);
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
            'temperature' => $temperature,
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
            // A 429 on the free tier is the tier working as sold, not a fault,
            // so it is logged as a WARNING. Left at ERROR it fires during
            // ordinary use, and a log that cries wolf is a log nobody reads
            // when something is really wrong.
            $context = [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ];

            if ($this->requestTooLargeForModel($response)) {
                // An ERROR, not a WARNING, and it names the knob: this one
                // does not clear on its own, and "rate limit hit" would send
                // whoever reads the log to the wrong place entirely.
                Log::error("Groq refused the request: GROQ_MODEL's per-minute output limit is below the max_tokens we send. Lower max_tokens for this caller or choose another model.", $context + [
                    'max_tokens' => $maxTokens,
                ]);
            } elseif ($response->status() === 429) {
                Log::warning('Groq API rate limit hit', $context + [
                    'retry_after_seconds' => $this->retryAfterSecondsFrom($response),
                ]);
            } else {
                Log::error('Groq API call failed', $context);
            }

            return $this->fail($this->statusFailure($response), $response);
        }

        $content = $this->stripReasoning((string) $response->json('choices.0.message.content', ''));

        if ($content === '') {
            Log::warning('Groq API returned an empty reply', [
                'model' => $model,
            ]);

            return $this->fail(self::FAILURE_EMPTY);
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

    private function askGemini(string $prompt, int $maxTokens, float $temperature): ?string
    {
        $key = config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if (blank($key)) {
            Log::error('AI_PROVIDER is gemini but GEMINI_API_KEY is not set');

            return $this->fail(self::FAILURE_MISCONFIGURED);
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
            'temperature' => $temperature,
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

            return $this->fail($this->statusFailure($response), $response);
        }

        $candidate = $response->json('candidates.0');

        if ($candidate === null) {
            // No candidate at all means the prompt itself was blocked.
            Log::warning('Gemini returned no candidate', [
                'model' => $model,
                'block_reason' => $response->json('promptFeedback.blockReason'),
            ]);

            return $this->fail(self::FAILURE_REFUSED);
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

            return $this->fail(self::FAILURE_EMPTY);
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

        return $this->fail(self::FAILURE_MISCONFIGURED);
    }

    // ── Failure reporting ───────────────────────────────────────────────

    /**
     * Records why this call failed and returns NULL, so every failure path
     * stays a one-line `return $this->fail(...)` and none of them can set the
     * reason without also returning NULL.
     */
    private function fail(string $reason, ?Response $response = null): ?string
    {
        $this->failure = $reason;
        $this->retryAfter = $response ? $this->retryAfterSecondsFrom($response) : null;

        return null;
    }

    /**
     * A 429 that is NOT a budget state: the request itself is too large for
     * this model, whatever the bucket says.
     *
     * Groq answers 429 for two unrelated things, and they need opposite
     * handling. The ordinary one is tokens-per-minute — routine on a free
     * tier, self-healing, and the guest only needs a few seconds. The other
     * is a per-minute **output** ceiling (OTPM) below the `max_tokens` we
     * send, and it never heals: every call fails identically until somebody
     * changes a config value. Real body, from this project's own log:
     *
     *   Request too large for model `qwen/qwen3.6-27b` ... on output tokens
     *   per minute (OTPM): Limit 1000, Requested 1024. The request's expected
     *   output tokens exceed the enforced limit; reduce max_tokens ...
     *
     * WHY THIS MATTERS MORE THAN IT LOOKS. Classified as RATE_LIMITED it is
     * logged as a WARNING saying "rate limit hit", and the chatbot's v7.78
     * terminal-failure path then tells the guest "I've had a lot of questions
     * at once — send that again in 20 seconds" **forever**, while the admin
     * reports say the AI is over its limit. Both messages promise a wait that
     * fixes nothing, and the log points at the budget instead of at
     * `GROQ_MODEL`. MISCONFIGURED is the honest class — it is the same kind of
     * problem as a missing API key: a person has to change something.
     *
     * NOT AUTO-RETRIED WITH A SMALLER CAP, deliberately. The error names the
     * limit, so a retry is tempting, but the only model on this key that
     * produced it has since been decommissioned (`qwen3.6-27b`;
     * `qwen3.8-27b`, `gpt-oss-120b` and `gpt-oss-20b` all report no OTPM
     * bucket at all, measured in v7.78). Retry code for a condition that
     * cannot currently be reproduced is code that has never run — and section
     * 9's rule here is that four confident diagnoses were already wrong. Fail
     * loudly and name the cause; a retry can be added by whoever can
     * reproduce it.
     */
    private function requestTooLargeForModel(Response $response): bool
    {
        if ($response->status() !== 429) {
            return false;
        }

        $body = $response->body();

        return str_contains($body, 'OTPM')
            || str_contains($body, 'reduce max_tokens')
            || str_contains($body, 'Request too large');
    }

    /** Which failure an HTTP status is. 429 is the one callers act on. */
    private function statusFailure(Response $response): string
    {
        return match (true) {
            // Order matters: this is a 429 too, and it is NOT a budget state.
            $this->requestTooLargeForModel($response) => self::FAILURE_MISCONFIGURED,
            $response->status() === 429 => self::FAILURE_RATE_LIMITED,
            $response->serverError() => self::FAILURE_UNAVAILABLE,
            default => self::FAILURE_REFUSED,
        };
    }

    /**
     * How long the provider said to wait, in whole seconds.
     *
     * `retry-after` first, since that is the provider's own answer. Groq also
     * sends the bucket refill times, and those are consulted next because the
     * limit this app actually exhausts is tokens-per-minute, not requests:
     * `x-ratelimit-reset-tokens` is the honest wait, and it is the shorter of
     * the two whenever the token bucket is what ran dry.
     *
     * NULL rather than a guessed default — the caller words "in a moment"
     * better than this method can invent a number.
     */
    private function retryAfterSecondsFrom(Response $response): ?int
    {
        foreach (['retry-after', 'x-ratelimit-reset-tokens', 'x-ratelimit-reset-requests'] as $header) {
            $seconds = $this->parseSeconds((string) $response->header($header));

            if ($seconds !== null) {
                return $seconds;
            }
        }

        return null;
    }

    /**
     * Seconds from either shape these headers come in: a bare count of seconds
     * (`retry-after: 60`) or Groq's duration string, which is written `21.915s`
     * and, over a minute, `1m26.4s` — both measured on a live response.
     */
    private function parseSeconds(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return max(1, (int) ceil((float) $raw));
        }

        if (! preg_match_all('/(\d+(?:\.\d+)?)(ms|m|s)/', $raw, $parts, PREG_SET_ORDER)) {
            return null;
        }

        $total = 0.0;

        foreach ($parts as $part) {
            $total += match ($part[2]) {
                'ms' => ((float) $part[1]) / 1000,
                'm' => ((float) $part[1]) * 60,
                's' => (float) $part[1],
            };
        }

        return max(1, (int) ceil($total));
    }
}
