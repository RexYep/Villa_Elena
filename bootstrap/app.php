<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
   ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    // Task 12 F7 — there was no health endpoint at all. The framework registers
    // this one outside every route group (so no session, no CSRF, no auth) and
    // calls PreventRequestsDuringMaintenance::except() on it, which is the point:
    // a monitor has to be able to tell "the app is down" from "the owner turned
    // maintenance mode on". Note this app's own CheckMaintenanceMode is applied
    // per-group in routes/web.php, so it does not reach here either.
    //
    // It answers 200 normally and 500 when a DiagnosingHealth listener throws;
    // AppServiceProvider adds one that reaches the database. The exception detail
    // is only rendered when APP_DEBUG is on, so production leaks nothing.
    health: '/up',
    then: function () {
        Route::middleware('web')->group(base_path('routes/admin.php'));
        Route::middleware('web')->group(base_path('routes/staff.php'));
        Route::middleware('web')->group(base_path('routes/customer.php'));

        // SADYANG WALANG `web` — ito lang ang route file na ganito.
        //
        // Ang mga route sa routes/cron.php ay tinatawag ng isang panlabas na
        // cron pinger, hindi ng browser. Noong nasa `web` group sila, bawat
        // ping ay dumaraan sa StartSession: isang row sa `sessions` at isang
        // Set-Cookie na itinatapon lang ng makinang tumatawag — kada minuto,
        // habambuhay, sa isang libreng Aiven database. Wala rin silang
        // pakinabang sa EncryptCookies, ShareErrorsFromSession o sa CSRF.
        Route::group([], base_path('routes/cron.php'));
    },
)
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['web', 'auth']],
    )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'maintenance.check' => \App\Http\Middleware\CheckMaintenanceMode::class,

        // Overrides the framework's own `throttle` alias so that every one of
        // the twenty named limiters records when it fires (Task 12 F3). The
        // subclass changes nothing about WHEN a request is throttled or what is
        // returned — it hooks buildException(), which is upstream of the choice
        // between a custom redirect-with-flash and a plain 429, and calls
        // parent::buildException() for the actual behaviour.
        //
        // What this replaces: the default alias resolves to
        // ThrottleRequestsWithRedis when `throttleWithRedis()` has been called.
        // This project never calls it and `cache.limiter` is pinned to the
        // `database` store, so nothing is lost. If Redis-backed limiting is ever
        // wanted, subclass that class instead of this one.
        'throttle' => \App\Http\Middleware\ThrottleRequestsWithMonitoring::class,
    ]);

    // Global, not `web`-only: routes/cron.php is deliberately outside the web
    // group (see the comment in withRouting above), and a security header
    // that skips a route because of how that route is grouped is the same
    // class of gap this middleware exists to close.
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

    // Records authorization failures (Task 12 F2). Global for the same reason,
    // and additionally because this is the one hook that CAN see them: a 403 is
    // an HttpException, and the framework's Handler lists HttpException and
    // AuthorizationException in $internalDontReport, so a report() callback
    // registered in withExceptions() below would never fire. Illuminate's
    // routing Pipeline renders the exception where it was thrown, so the 403
    // comes back out through this middleware as an ordinary response.
    $middleware->append(\App\Http\Middleware\RecordSecurityResponses::class);

    // Trust the reverse proxy (ngrok) so Laravel reads the
    // X-Forwarded-Proto header and knows the original request
    // was https, even though it forwards to us over plain http.
    // Without this, signed URLs (like the email verification link)
    // can fail validation with a scheme mismatch.
    //
    // `at: '*'` means "trust the immediate peer", not "trust everyone":
    // TrustProxies resolves it to [REMOTE_ADDR], and Symfony then returns the
    // right-most UNTRUSTED entry of X-Forwarded-For, so a client-supplied
    // X-Forwarded-For is ignored. Verified against the real middleware.
    $middleware->trustProxies(at: '*');

    // ...but the same setting also trusts X-Forwarded-Host, and THAT was not
    // safe: a request carrying `X-Forwarded-Host: evil.example.com` made
    // $request->getHost() return it, and Laravel builds password-reset and
    // email-verification links from the request host. The reset link in a real
    // guest's inbox could be pointed at an attacker's domain, token and all.
    //
    // Pinning the trusted hosts fixes it wherever the host came from — the
    // forwarded header or a spoofed `Host:` — because Symfony validates the
    // resolved host against these patterns. Laravel skips this check in the
    // `local` environment and under tests (TrustHosts::shouldSpecifyTrustedHosts),
    // so `php artisan serve`, Docker and the ngrok tunnel are unaffected; it
    // bites only on Render, where APP_ENV=production and APP_URL is the real
    // host. TRUSTED_HOSTS is there so an extra hostname (a custom domain, a
    // health check that arrives with an unexpected Host) can be allowed from
    // the dashboard without a code deploy.
    $middleware->trustHosts(at: fn () => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('TRUSTED_HOSTS', '')))
    )));

    // Ang PayMongo webhook ay server-to-server — walang session,
    // walang CSRF token. Nasa routes/web.php ito, kaya saklaw ito ng
    // `web` middleware group at sinasalo ng ValidateCsrfTokens:
    // 419 Page Expired ang isinasagot bago pa man marating ang
    // controller. Ang pagpapatunay dito ay ang HMAC signature sa
    // `Paymongo-Signature` header (PayMongoService::verifyWebhook),
    // hindi ang CSRF token — kaya kailangan itong i-exempt para
    // gumana talaga ang endpoint.
    // HINDI ITO ANG BUONG LISTAHAN NG MGA EXEMPT NA ENDPOINT, at hindi ito
    // makikita rito. May PANGATLO: ang POST /broadcasting/auth. Ang framework
    // mismo ang nag-aalis ng CSRF doon —
    //
    //     BroadcastManager::routes()  (vendor/.../Broadcasting, linya 81)
    //         ->withoutMiddleware([VerifyCsrfToken::class])
    //
    // — kaya wala itong anumang kinalaman sa `except` sa ibaba, at mananatili
    // ito kahit anong gawin dito. Napatunayan sa pamamagitan ng pagbasa sa
    // tunay na middleware stack ng route na iyon, hindi sa config.
    //
    // Hindi ito butas sa ngayon, pero may KONDISYON ang pagiging ligtas nito.
    // Ang isinasagot ng endpoint na iyon ay ang Pusher channel-auth signature.
    // Kayang PILITIN ng ibang site ang isang naka-login na browser na tumawag
    // doon — ang hindi nila kaya ay BASAHIN ang sagot.
    //
    // Dalawang bagay ang pumipigil doon, at pareho itong nasa CORS config.
    // Tandaan: HINDI walang laman ang `cors` kahit hindi nai-publish ang
    // config/cors.php — may sariling default ang framework na isinasama nito.
    // Ito ang tunay na halaga:
    //
    //     paths               => ['api/*', 'sanctum/csrf-cookie']
    //     allowed_origins     => ['*']
    //     supports_credentials => false
    //
    //   1. Walang `paths` entry na tumutugma sa `broadcasting/auth`, kaya
    //      hindi kailanman tumatama ang HandleCors doon at wala itong
    //      ipinapadalang CORS header.
    //   2. `supports_credentials` ay false, kaya kahit tumugma ito, hindi
    //      ibibigay ng browser sa nanghihingi ang sagot ng isang kahilingang
    //      may dalang cookie — `allowed_origins => ['*']` ay walang bisa kapag
    //      may credentials.
    //
    // Kung mabuksan ang alinman sa dalawang iyon, ang channel signature ay
    // magiging nababasa nang cross-origin. Binabantayan iyon ng
    // CsrfCookieSecurityTest.
    $middleware->validateCsrfTokens(except: [
        'webhooks/paymongo',
        // Ang callback ng Send Money — ipinapadala ito ng PayMongo sa
        // `callback_url` ng transfer kapag na-settle na ito. Server-to-
        // server rin ito, kaya pareho ang dahilan sa itaas.
        'webhooks/paymongo/transfer',
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Task 12 F6 — a 500 used to be written to Render's log stream and
        // nothing else: no notification, no error tracker, short free-tier
        // retention, no search. Nobody found out unless a guest complained.
        //
        // This callback DOES fire for real faults, unlike the 403 case in
        // RecordSecurityResponses: Handler::report() skips only the
        // $internalDontReport list (HttpException, AuthorizationException,
        // ValidationException, ModelNotFoundException, TokenMismatchException …),
        // so what reaches here is genuinely unexpected — a TypeError, a
        // QueryException, a failed API call nobody caught.
        //
        // THE FRAMEWORK STILL LOGS IT. Returning nothing (rather than calling
        // ->stop()) leaves the normal ERROR entry with its stack trace intact;
        // this only adds the "tell someone" half.
        $exceptions->report(function (\Throwable $e) {
            try {
                $route = request()?->route()?->getName() ?? request()?->path() ?? 'console';

                // Bucketed by exception class AND route, so one broken page does
                // not suppress alerts about a different one — and a flood from a
                // single bug still costs one alert per hour, not one per request.
                \App\Services\SecurityMonitor::recordAndEscalate(
                    event: \App\Services\SecurityMonitor::APP_ERROR,
                    bucket: $e::class.'|'.$route,
                    summary: 'Unhandled '.class_basename($e).' at '.$route,
                    threshold: 3,
                    title: 'The site is throwing errors',
                    message: class_basename($e).' has been thrown 3 times in the last hour at '
                        .$route.'. Guests hitting this are seeing an error page. The Render log'
                        .' stream has the stack trace.',
                    // NOTE WHERE $e->getMessage() IS AND IS NOT.
                    //
                    // It is deliberately absent from `summary` and `message`
                    // above, because those two become the staff_logs description
                    // and the admin notification — both rendered to people in the
                    // notification bell and the audit viewer. An exception message
                    // can carry SQL with its bound values (QueryException) or a
                    // payment gateway's raw response body, which is the same
                    // mistake v7.38's F6/F7 fixed for guests and admins.
                    //
                    // It is also absent from `context` here, but that is a
                    // smaller point: context goes only to Log::warning, and the
                    // framework's own ERROR entry for this same exception already
                    // carries the message AND the stack trace. Repeating it would
                    // add nothing; the class and the file:line are what make the
                    // two entries findable as a pair.
                    context: [
                        'exception' => $e::class,
                        'route' => $route,
                        'file' => mb_substr($e->getFile(), -80).':'.$e->getLine(),
                    ],
                );
            } catch (\Throwable) {
                // The reporter must never make things worse. A likely cause of
                // the original 500 is the database being unreachable, in which
                // case writing an audit row and a notification will fail too —
                // SecurityMonitor already swallows that, and this is the backstop.
            }
        });
    })->create();