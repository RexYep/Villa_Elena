<?php

namespace App\Http\Middleware;

use App\Services\SecurityMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes authorization failures visible (Task 12 F2).
 *
 * THE MEASUREMENT THAT MOTIVATED THIS. An authenticated customer requesting
 * another guest's payment data:
 *
 *     GET /pay/{other guest's booking}/status  ->  HTTP 403
 *       log lines written:  0
 *       staff_logs rows:    0
 *       log levels invoked: 0
 *
 * Every ownership guard in this app — `abort_if($x->user_id !== Auth::id(), 403)`
 * across all five payment endpoints and every customer controller, plus
 * RoleMiddleware — was firing with no trace whatsoever. Someone walking booking
 * ids against `/pay/{id}/status`, which returns amounts paid and balances due,
 * left no evidence anywhere.
 *
 * WHY A MIDDLEWARE AND NOT AN EXCEPTION HANDLER. The obvious place looks like
 * `withExceptions()->report()`, and it does not work: `Handler::report()` checks
 * `shouldntReport()` BEFORE running any reportable callback, and
 * `$internalDontReport` (framework Handler.php:149) lists both
 * `AuthorizationException` and `HttpException` — which is every `abort(403)`.
 * Registering a report callback there would silently never fire.
 *
 * A global middleware does see these, because Illuminate\Routing\Pipeline
 * catches an exception at the point it was thrown and renders it there, so the
 * 403 travels back out through the middleware stack as an ordinary response.
 * Verified rather than assumed: a 403 from the cron gate carries the
 * `X-Frame-Options` header that SecurityHeaders (also global) adds.
 *
 * Registered GLOBALLY, not in `web`, for the same reason SecurityHeaders is:
 * routes/cron.php sits outside that group, and a security control that skips a
 * route because of how the route is grouped is the gap it exists to close.
 */
class RecordSecurityResponses
{
    /**
     * A 403 here is always exceptional. Normal refusals in this app use other
     * codes — an already-paid booking is 400, a missing record is 404, an
     * unauthenticated visitor is a 302 to the login page. So there is no
     * routine-403 noise to filter out, and filtering would be the thing most
     * likely to hide a real attempt.
     */
    private const WATCHED = [403];

    /**
     * Alert once an actor has been refused this many times in
     * SecurityMonitor::WINDOW_MINUTES.
     *
     * Five, not one: a guest who bookmarked a booking that has since been
     * cancelled, or who has two accounts and is signed into the wrong one, can
     * produce a 403 or two honestly. Five separate refusals in an hour is
     * someone trying things. The number is a judgement, not a measurement — it
     * has no production data behind it yet, and it is the first thing to tune
     * once there is some.
     */
    private const ALERT_AFTER = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            // A site that already recorded something specific about this request
            // wins — see SecurityMonitor::markHandled(). A wrong CRON_SECRET, for
            // instance, is reported as `cron_secret_rejected` (which names what
            // was being guessed) rather than twice, once as that and once as a
            // generic refusal.
            if (in_array($response->getStatusCode(), self::WATCHED, true)
                && ! SecurityMonitor::wasHandled()) {
                $this->record($request);
            }
        } catch (\Throwable) {
            // SecurityMonitor already swallows and logs its own failures; this
            // is the belt to that braces. This middleware wraps EVERY response,
            // including the PayMongo webhook, which must never return non-2xx.
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $user = $request->user();
        $ip = (string) $request->ip();

        // Bucketed by actor, not by target: the signal worth alerting on is one
        // person being refused repeatedly, not one record being asked for by
        // many people. A signed-in actor is identified by id, because an IP
        // changes as a phone moves between networks.
        $bucket = $user ? 'user:'.$user->id : 'ip:'.$ip;

        $who = $user
            ? "{$user->full_name} (#{$user->id}, {$user->role})"
            : "a signed-out visitor at {$ip}";

        $where = $request->route()?->getName() ?? $request->path();

        SecurityMonitor::recordAndEscalate(
            event: SecurityMonitor::AUTHZ_FAILED,
            bucket: $bucket,
            summary: "Refused (403): {$who} tried {$request->method()} {$where}",
            threshold: self::ALERT_AFTER,
            title: 'Repeated permission failures',
            message: "{$who} has been refused ".self::ALERT_AFTER
                .' times in the last hour, most recently on '.$request->method().' '.$where
                .'. This is what an attempt to read another guest\'s booking or payment'
                .' details looks like. The Audit Log has the full list.',
            context: [
                'ip' => $ip,
                'user_id' => $user?->id,
                'role' => $user?->role,
                'method' => $request->method(),
                'route' => $where,
                // The path can carry the id being probed, which is the single
                // most useful detail when reading these back. Capped because a
                // URL is attacker-controlled input.
                'path' => mb_substr($request->path(), 0, 200),
            ],
            link: route('admin.audit.index', [], false),
        );
    }
}
