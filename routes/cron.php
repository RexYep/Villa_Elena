<?php

use App\Http\Controllers\PaymentController;
use App\Services\CacheDiagnostics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Machine-to-machine routes
|--------------------------------------------------------------------------
|
| These are called by an external cron pinger, never by a browser, and they
| are registered OUTSIDE the `web` group (see bootstrap/app.php).
|
| That is not tidiness. In the `web` group every ping ran StartSession: a row
| written to `sessions` and a Set-Cookie returned to a machine that throws it
| away — once a minute, forever, on a free-tier Aiven database swept only by
| the 2-in-100 lottery. It also dragged EncryptCookies, ShareErrorsFromSession
| and the CSRF machinery onto an endpoint that has no use for any of them.
|
| Authentication is the `X-Cron-Secret` header. It used to be a path segment,
| `/cron/run-schedule/{token}`, and a path is the most-copied part of a
| request: it lands in nginx access logs, in Render's log stream, in the cron
| service's own dashboard and execution history, and in every proxy between.
| None of those are built to hold secrets, and rotating CRON_SECRET meant
| editing the pinger's URL. A header appears in none of them.
|
| The legacy path form still works so a deploy cannot silently stop the
| scheduler — but it logs a warning every time, so the switchover is visible.
| Delete `cronLegacyPath()` and its two routes once that warning stops.
|
*/

/** Constant-time check of the shared secret, wherever it arrived from. */
$cronSecretMatches = function (?string $candidate): bool {
    $expected = (string) config('app.cron_secret');

    return $expected !== ''
        && $candidate !== null
        && hash_equals($expected, $candidate);
};

/**
 * Record a rejected secret (Task 12 F4).
 *
 * These endpoints are unauthenticated, publicly reachable, and one of them runs
 * `schedule:run` while the other reports on the cache. The only thing in front
 * of them is a shared secret, so a wrong one is worth knowing about — and it was
 * previously silent, since `abort(403)` is an HttpException the framework never
 * reports. `RecordSecurityResponses` now catches the 403 generically, but this
 * adds the one fact that middleware cannot know: that the thing being guessed
 * was CRON_SECRET rather than an ownership check.
 *
 * Threshold of 3. Nothing legitimate ever sends a wrong secret: the pinger is
 * configured once and either has it or does not. Three wrong guesses in an hour
 * is either a misconfigured pinger — worth knowing, because the scheduler has
 * stopped — or someone trying the endpoint.
 */
$cronSecretRejected = function (Request $request, string $how): void {
    \App\Services\SecurityMonitor::recordAndEscalate(
        event: \App\Services\SecurityMonitor::CRON_SECRET_REJECTED,
        bucket: 'ip:'.$request->ip(),
        summary: "A wrong cron secret was presented via the {$how} on ".$request->path(),
        threshold: 3,
        title: 'Wrong cron secret being presented',
        message: 'Three or more requests with an incorrect CRON_SECRET have arrived in the'
            .' last hour, from '.$request->ip().'. If this is the scheduled pinger then the'
            .' scheduler is NOT running — pending bookings are not being swept and refund'
            .' transfers are not being reconciled. If it is not the pinger, someone is'
            .' guessing at /cron/run-schedule.',
        context: ['ip' => $request->ip(), 'presented_via' => $how, 'path' => $request->path()],
    );
};

/** The header form: the one the pinger should be using. */
$cronGate = function (Request $request) use ($cronSecretMatches, $cronSecretRejected): void {
    if (! $cronSecretMatches($request->header('X-Cron-Secret'))) {
        $cronSecretRejected($request, 'X-Cron-Secret header');
        abort(403);
    }
};

/**
 * The old form. Identical check, plus a warning naming the caller, so there is
 * a way to tell whether anything still depends on it.
 */
$cronLegacyPath = function (string $token, Request $request) use ($cronSecretMatches, $cronSecretRejected): void {
    if (! $cronSecretMatches($token)) {
        $cronSecretRejected($request, 'URL path (legacy form)');
        abort(403);
    }

    Log::warning(
        'Deprecated cron secret in URL path from '.$request->ip().
        ' — switch the pinger to the X-Cron-Secret header (see routes/cron.php).'
    );
};

// Free-tier cron workaround — Render's free plan has no Cron Jobs feature, so
// an external pinger (cron-job.org) hits this instead of a real server cron,
// to run bookings:auto-checkinout (see routes/console.php).
Route::get('/cron/run-schedule', function (Request $request) use ($cronGate) {
    $cronGate($request);

    Artisan::call('schedule:run');

    return response('ok');
})->middleware('throttle:token-gated')->name('cron.run-schedule');

// Runs the cache measurements INSIDE the production container and returns them
// as JSON. Render's free plan has no Shell tab, and the production Redis
// (Render Key Value) is internal-only, so this is the only way to find out
// whether Redis actually helps there — a developer machine measures its own
// distance to Aiven and Docker, which answers a different question. Safe to
// delete once the numbers are recorded.
//
// WHAT THIS RETURNS, stated as a list rather than as a promise: timings, query
// counts, Redis reachability and version, and three settings that change what
// the timings mean (`app_env`, `cache_store`, `redis_client`).
//
// It used to say "timings only, never config values or credentials", and that
// was not true — `CacheDiagnostics::environment()` also returned the production
// `db_host`, and a Redis failure returned the exception message, which carries
// the host and port. Both were removed in v7.40. The lesson is the wording as
// much as the leak: a comment asserting a property of code somewhere else goes
// stale silently. If you add a field here, add it to the list above.
Route::get('/diagnostics/cache', function (Request $request) use ($cronGate) {
    $cronGate($request);

    return response()->json(
        CacheDiagnostics::measure((int) $request->integer('iterations', 50))
    );
})->middleware('throttle:token-gated')->name('diagnostics.cache');

// ── PayMongo webhooks ────────────────────────────────────────────────────
//
// NO auth, NO CSRF, NO throttle, and never a non-2xx — see
// PaymentController::webhook() for why each of those is deliberate.
// Authentication is the HMAC in the `Paymongo-Signature` header.
//
// They live here rather than in routes/web.php because PayMongo is a machine
// that throws the cookie away: in the `web` group every delivery ran
// StartSession and wrote a `sessions` row, then handed back a Set-Cookie
// nobody would ever return. Measured, then moved.
//
// The `validateCsrfTokens(except: [...])` entries in bootstrap/app.php are
// KEPT even though ValidateCsrfToken no longer runs on these paths. They cost
// nothing and they are what stops a 419 if these routes are ever moved back
// into `web` — which is exactly the kind of move that would otherwise look
// fine and silently kill every payment recording.

Route::post('/webhooks/paymongo', [PaymentController::class, 'webhook'])
    ->name('payment.webhook');

// Callback ng Send Money — inaabisuhan tayo nito kapag na-settle na ang isang
// refund transfer. Hindi pinagkakatiwalaan ang laman; ang tunay na estado ay
// kinukuha sa isang authenticated na GET (v5.9).
Route::post('/webhooks/paymongo/transfer', [PaymentController::class, 'transferCallback'])
    ->name('payment.webhook.transfer');

// ── Legacy: secret in the URL path. Remove once the pinger is switched. ──

Route::get('/cron/run-schedule/{token}', function (string $token, Request $request) use ($cronLegacyPath) {
    $cronLegacyPath($token, $request);

    Artisan::call('schedule:run');

    return response('ok');
})->middleware('throttle:token-gated')->name('cron.run-schedule.legacy');

Route::get('/diagnostics/cache/{token}', function (string $token, Request $request) use ($cronLegacyPath) {
    $cronLegacyPath($token, $request);

    return response()->json(
        CacheDiagnostics::measure((int) $request->integer('iterations', 50))
    );
})->middleware('throttle:token-gated')->name('diagnostics.cache.legacy');
