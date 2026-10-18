<?php

namespace App\Http\Controllers\Concerns;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One AI refresh per minute, per admin, per report.
 *
 * Caching the reports removes the accidental calls; this removes the
 * deliberate ones. Groq's output-tokens-per-minute budget is what actually
 * bites (`rate_limit_exceeded` … `on output tokens per minute (OTPM)`), and an
 * admin who clicks Refresh five times while waiting is the fastest way to
 * exhaust it — which then makes the page look *more* broken, so they click
 * again. A minute is roughly how long a report takes to stop being interesting.
 *
 * Deliberately not the `throttle` middleware: that answers 429 with a bare
 * error page. Here the admin gets their own page back with a message that says
 * how long to wait.
 */
trait ThrottlesAiRefresh
{
    /**
     * Returns the seconds left to wait, or NULL when the refresh may proceed.
     * Records the attempt as a side effect when it may proceed.
     */
    protected function aiRefreshCooldown(string $report): ?int
    {
        $key = sprintf('ai-refresh:%s:%s', $report, Auth::id() ?? 'guest');

        if (RateLimiter::tooManyAttempts($key, 1)) {
            // Never report "0 seconds" — that reads as a bug to whoever sees it.
            return max(1, RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);

        return null;
    }

    protected function aiRefreshCooldownMessage(string $label, int $seconds): string
    {
        return sprintf(
            'The %s was refreshed moments ago — please wait %d second%s before refreshing again.',
            $label,
            $seconds,
            $seconds === 1 ? '' : 's'
        );
    }

    /**
     * Why a Refresh produced nothing, for the admin.
     *
     * "The service did not respond" is wrong for the commonest cause by far:
     * the AI tier allows 8,000 tokens a minute across the whole site, and a
     * report costs up to ~2,400 of them — so a Refresh pressed while a guest
     * is chatting is refused on budget, not broken. An admin told "did not
     * respond" investigates; an admin told "over its limit, try again in 40
     * seconds" waits 40 seconds.
     *
     * $what is the subject ('Insights could not be refreshed'), $reassurance
     * the sentence about what is unaffected — the figures on the page are
     * computed from the database and never came from the AI, and saying so is
     * what stops a failed Refresh looking like a failed page.
     */
    protected function aiFailureMessage(GeminiService $ai, string $what, string $reassurance): string
    {
        if (! $ai->rateLimited()) {
            return $what.' right now — the AI service did not respond. '.$reassurance;
        }

        $wait = $ai->retryAfterSeconds();

        $when = $wait === null
            ? 'Please try again shortly.'
            : sprintf('Please try again in %d second%s.', $wait, $wait === 1 ? '' : 's');

        return $what.' — the AI service is over its limit for the moment. '.$when.' '.$reassurance;
    }
}
