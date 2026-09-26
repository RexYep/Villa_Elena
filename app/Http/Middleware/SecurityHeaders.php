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

        // WAS `strict-origin` (v7.37). THAT BROKE EVERY `back()` IN THE APP.
        //
        // strict-origin sends scheme+host and nothing else, in every
        // direction — same-origin navigations included. Laravel's
        // UrlGenerator::previous() reads the Referer FIRST and only falls
        // back to the session when it is empty:
        //
        //     $url = $referrer ? $this->to($referrer) : $this->getPreviousUrlFromSession();
        //
        // A bare origin is not empty, so it won it, and `back()` resolved to
        // `/` — the public landing page. Measured, two identical POSTs to
        // /login with a wrong password, differing only in the Referer:
        //
        //     Referer: http://127.0.0.1:8000/        -> 302 Location: /
        //     Referer: http://127.0.0.1:8000/login   -> 302 Location: /login
        //
        // That is 135 `back()` calls across 22 controllers, plus every
        // validation failure in the app (the framework redirects those to
        // url()->previous() too) — all of them landing a guest or an admin on
        // the landing page, errors flashed to a page that never shows them.
        // Saving /admin/settings was just the instance that got reported.
        // Note a bare-origin Referer is WORSE than none: with no Referer at
        // all, previous() uses the session and is correct.
        //
        // `same-origin` restores the full URL on our own requests and sends
        // NOTHING cross-origin — strictly less leakage to third parties than
        // strict-origin, which still hands out the hostname.
        //
        // What that gives back is the same-origin case v7.37 was written for:
        // a page whose own URL holds a secret leaks it to our access log
        // through its CSS/JS requests. That is now handled per route below
        // instead of by punishing every form in the app.
        //
        // (The cron secret named in the old comment is no longer in a URL at
        // all — it moved to the X-Cron-Secret header. See routes/cron.php.)
        $response->headers->set(
            'Referrer-Policy',
            $this->carriesSecretInUrl($request) ? 'no-referrer' : 'same-origin'
        );

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

    /**
     * Pages whose own URL is a credential.
     *
     * `password.reset` is the one that genuinely leaks: it RENDERS a form, so
     * every stylesheet and script that page pulls carries
     * `Referer: /reset-password/{token}` into our access log under
     * `same-origin`. `verification.verify` only redirects, so it has no
     * subresources to leak through — it is here because it is the same class
     * of URL and the cost of listing it is one line.
     *
     * Read AFTER $next(), so the route is resolved: this middleware is global
     * and runs before routing on the way in, where $request->route() is null.
     *
     * This does NOT break the reset form. Its POST then carries no Referer at
     * all, which sends previous() to the session — where StartSession stored
     * the GET of this very page — so a validation failure returns to the form
     * rather than to `/`.
     */
    private function carriesSecretInUrl(Request $request): bool
    {
        return in_array(
            $request->route()?->getName(),
            ['password.reset', 'verification.verify'],
            true
        );
    }
}
