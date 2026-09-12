<?php

namespace App\Http\Controllers\Concerns;

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
}
