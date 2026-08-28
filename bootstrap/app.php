<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
   ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    then: function () {
        Route::middleware('web')->group(base_path('routes/admin.php'));
        Route::middleware('web')->group(base_path('routes/staff.php'));
        Route::middleware('web')->group(base_path('routes/customer.php'));
    },
)
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['web', 'auth']],
    )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);

    // Trust the reverse proxy (ngrok) so Laravel reads the
    // X-Forwarded-Proto header and knows the original request
    // was https, even though it forwards to us over plain http.
    // Without this, signed URLs (like the email verification link)
    // can fail validation with a scheme mismatch.
    $middleware->trustProxies(at: '*');

    // Ang PayMongo webhook ay server-to-server — walang session,
    // walang CSRF token. Nasa routes/web.php ito, kaya saklaw ito ng
    // `web` middleware group at sinasalo ng ValidateCsrfTokens:
    // 419 Page Expired ang isinasagot bago pa man marating ang
    // controller. Ang pagpapatunay dito ay ang HMAC signature sa
    // `Paymongo-Signature` header (PayMongoService::verifyWebhook),
    // hindi ang CSRF token — kaya kailangan itong i-exempt para
    // gumana talaga ang endpoint.
    $middleware->validateCsrfTokens(except: [
        'webhooks/paymongo',
        // Ang callback ng Send Money — ipinapadala ito ng PayMongo sa
        // `callback_url` ng transfer kapag na-settle na ito. Server-to-
        // server rin ito, kaya pareho ang dahilan sa itaas.
        'webhooks/paymongo/transfer',
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();