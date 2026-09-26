<?php

namespace App\Http\Middleware;

use App\Services\SecurityMonitor;
use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Records when a rate limiter actually fires (Task 12 F3).
 *
 * THE PROBLEM. This app has twenty named limiters and `configureRateLimiting()`
 * contained zero `Log::` calls, so nothing anywhere knew when one tripped.
 * Worse, most of them deliberately return a redirect-with-flash rather than a
 * 429 — the right call for a guest filling in a form, and a rule CLAUDE.md
 * states explicitly — which means a tripped limiter is not even distinguishable
 * by status code. Measured: twelve rapid POSTs to /contact returned twelve 302s
 * and wrote nothing.
 *
 * That matters more here than the word "throttle" suggests. These limiters are
 * what protect Brevo's 300 emails/day, shared between 2FA codes, password resets
 * and booking confirmations, and the Groq token budget shared by the chatbot,
 * review moderation and the admin reports. Exhausting either is a denial of
 * service against booking confirmations and sign-in codes — and it would present
 * to the owner as "email is broken", with nothing pointing at the cause.
 *
 * WHY buildException() IS THE RIGHT SEAM. It is reached on every throttled
 * request (ThrottleRequests::handleRequest, framework line 158) and it is
 * upstream of the branch that chooses between a custom `responseCallback` and a
 * plain 429 (line 252). So overriding it covers all twenty limiters, in both
 * response styles, without touching a single limiter definition — and a limiter
 * added later is covered automatically, which is the part a per-limiter edit
 * could not give.
 *
 * `$limiterName` has to be captured separately: by the time buildException()
 * sees the key it is `md5($limiterName.$limit->key)` (line 135) and the name is
 * not recoverable from it. Knowing WHICH protection was hit is most of the value.
 *
 * Wired in bootstrap/app.php by re-aliasing `throttle` to this class. Note that
 * replaces the framework's own alias, which resolves to ThrottleRequestsWithRedis
 * when `throttleWithRedis()` has been called — this project never calls it, and
 * `cache.limiter` is pinned to the `database` store, so there is nothing to lose.
 */
class ThrottleRequestsWithMonitoring extends ThrottleRequests
{
    /**
     * Alert once one actor has been throttled this many times inside
     * SecurityMonitor::WINDOW_MINUTES.
     *
     * Higher than the 403 threshold on purpose. Hitting a limiter is a normal
     * thing a real guest does — double-clicking Pay, retrying a form, asking the
     * chatbot several questions quickly. Twenty separate refusals in an hour is
     * not that. A guess, not a measurement; tune it from the first month of data.
     */
    private const ALERT_AFTER = 20;

    /** The limiter currently being evaluated, for buildException() to name. */
    protected ?string $villaLimiterName = null;

    protected function handleRequestUsingNamedLimiter($request, Closure $next, $limiterName, Closure $limiter)
    {
        $this->villaLimiterName = $limiterName;

        return parent::handleRequestUsingNamedLimiter($request, $next, $limiterName, $limiter);
    }

    protected function buildException($request, $key, $maxAttempts, $responseCallback = null)
    {
        try {
            $this->recordThrottle($request, $maxAttempts);
        } catch (\Throwable) {
            // Monitoring must never turn a throttle into a 500. SecurityMonitor
            // swallows its own failures; this covers anything above it.
        }

        return parent::buildException($request, $key, $maxAttempts, $responseCallback);
    }

    private function recordThrottle($request, int $maxAttempts): void
    {
        $limiter = $this->villaLimiterName ?? 'unnamed';
        $user = $request->user();
        $ip = (string) $request->ip();

        // Per actor AND per limiter: someone hammering the chatbot is a
        // different fact from someone hammering password resets, and collapsing
        // them would let the noisier one hide the other.
        $bucket = ($user ? 'user:'.$user->id : 'ip:'.$ip).'|'.$limiter;

        $who = $user ? "{$user->full_name} (#{$user->id})" : "IP {$ip}";

        SecurityMonitor::recordAndEscalate(
            event: SecurityMonitor::RATE_LIMITED,
            bucket: $bucket,
            summary: "Rate limit '{$limiter}' stopped {$who}",
            threshold: self::ALERT_AFTER,
            title: "Rate limit '{$limiter}' being hit repeatedly",
            message: "{$who} has been stopped by the '{$limiter}' limit "
                .self::ALERT_AFTER.' times in the last hour. If this limit guards'
                .' email (2FA, password resets, booking confirmations) or the AI,'
                .' the daily budget it protects may be under pressure.',
            context: [
                'limiter' => $limiter,
                'max_attempts' => $maxAttempts,
                'ip' => $ip,
                'user_id' => $user?->id,
                'route' => $request->route()?->getName() ?? $request->path(),
            ],
            link: route('admin.audit.index', [], false),
        );
    }
}
