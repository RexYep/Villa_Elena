<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response security headers, set by the application rather than by nginx.
 *
 * WHY THIS MOVED OUT OF nginx.conf.template. It was measured against the live
 * site, and two of the four headers the config file claims were simply not
 * there:
 *
 *     X-Content-Type-Options       present
 *     X-Frame-Options              present
 *     Referrer-Policy              ABSENT
 *     Strict-Transport-Security    ABSENT
 *
 * The two missing ones are exactly the two that exist only as uncommitted
 * working-tree edits to `docker/nginx.conf.template`. Nothing was broken —
 * the config was just never deployed, and there was no way to notice, because
 * a header that lives in the web-server layer cannot be asserted by the test
 * suite and is invisible in local development (`php artisan serve` never runs
 * nginx at all).
 *
 * Here, the four headers are present in every environment, and
 * tests/Feature/SecurityHeadersTest.php fails if one goes missing. That is the
 * actual repair: not "add the header again", but "make its absence
 * detectable".
 *
 * nginx keeps ONE of them. Files under /storage/ and the static-extension
 * location are served straight off disk by nginx and never reach PHP, so
 * `X-Content-Type-Options` is still set there — that is the case where
 * nosniff matters most, since /storage/ is the one directory guests can write
 * into. The other three are meaningless on an image and are not duplicated.
 *
 * DO NOT ALSO SET THESE IN nginx. `add_header` appends rather than replaces,
 * and a duplicated `X-Frame-Options` is treated as a conflict by browsers,
 * which then ignore the header entirely — the duplicate would silently undo
 * the protection it looks like it is reinforcing.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Uploaded content is served from this origin, so a file the browser
        // is willing to re-interpret as HTML or a script is a real risk.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking. SAMEORIGIN rather than DENY: the payment and calendar
        // views embed same-origin frames.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // This app puts secrets in URLs and cannot stop doing so: the cron
        // pinger calls /cron/run-schedule/{CRON_SECRET}, and password-reset
        // and email-verification links carry a signature in the query string.
        // The browser default (strict-origin-when-cross-origin) still sends
        // the full URL on SAME-ORIGIN navigations, which is how a secret ends
        // up in a Referer header in our own logs. strict-origin sends only
        // scheme+host, in every direction.
        $response->headers->set('Referrer-Policy', 'strict-origin');

        // Only over https. Render terminates TLS in front of the container
        // and `trustProxies(at: '*')` lets isSecure() see that, so this is set
        // in production and correctly skipped on local http — where a stray
        // HSTS pin on localhost would be a nuisance to undo.
        //
        // No `includeSubDomains`: onrender.com is not ours to speak for.
        // Revisit if a custom domain is ever attached.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
