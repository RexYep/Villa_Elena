<?php

namespace App\Services;

class ReviewModerationService
{
    public function __construct(protected GeminiService $ai)
    {
    }

    /**
     * @return array{approved: bool, reason: ?string}
     */
    public function evaluate(string $title, string $content): array
    {
        $text = $title . ' ' . $content;

        $preFilterReason = $this->preFilter($text);
        if ($preFilterReason) {
            return ['approved' => false, 'reason' => "Auto-flagged: {$preFilterReason}"];
        }

        return $this->classifyWithAi($title, $content);
    }

    private function preFilter(string $text): ?string
    {
        $lower = strtolower($text);

        foreach (config('moderation.blocked_words', []) as $word) {
            if (str_contains($lower, strtolower($word))) {
                return 'contains inappropriate language';
            }
        }

        foreach (config('moderation.patterns', []) as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                return "possible {$label} detected";
            }
        }

        return null;
    }

    private function classifyWithAi(string $title, string $content): array
    {
        $prompt = <<<PROMPT
        You are a content moderator for a resort review website. Reply with EXACTLY one line, nothing else.
        Format: either "CLEAN" or "FLAGGED: <short reason>".
        Only flag spam, hate speech, harassment/abuse, or personal contact information. Do not flag ordinary negative or critical feedback about the stay.

        Review title: "{$title}"
        Review content: "{$content}"
        PROMPT;

        $response = $this->ai->ask($prompt);

        if ($response === null) {
            // AI unavailable — fail open to the pre-existing manual-review flow
            // rather than guessing either way.
            return ['approved' => false, 'reason' => null];
        }

        $response = trim($response);

        $firstLine = trim(strtok($response, "\n"));

        if (stripos($firstLine, 'CLEAN') === 0) {
            return ['approved' => true, 'reason' => null];
        }

        if (stripos($firstLine, 'FLAGGED') === 0) {
            $reason = trim(preg_replace('/^FLAGGED:?\s*/i', '', $firstLine));
            $reason = $reason !== '' ? $reason : 'flagged by automated moderation';
            return ['approved' => false, 'reason' => 'Auto-flagged: ' . substr($reason, 0, 240)];
        }

        // Unparseable response — same fail-open behavior as an API error.
        return ['approved' => false, 'reason' => null];
    }
}
