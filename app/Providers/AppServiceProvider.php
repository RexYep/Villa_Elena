<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brevo's HTTPS API is the PRODUCTION transport: Render's free
        // plan blocks outbound SMTP ports entirely, so the live site
        // can't use an SMTP mailer at all. Local dev uses MAIL_MAILER=smtp
        // instead. This only registers a factory — harmless when a
        // different MAIL_MAILER is selected, since the closure runs only
        // when the 'brevo' mailer is actually resolved.
        Mail::extend('brevo', function () {
            $dsn = config('services.brevo.dsn');

            // Kapag MAIL_MAILER=brevo pero walang MAILER_DSN, null ang
            // naipapasa sa Dsn::fromString() — at ang lumalabas ay
            // TypeError na nagturo sa loob ng Symfony ("Argument #1
            // ($dsn) must be of type string, null given"), na hindi man
            // lang binabanggit ang totoong problema: kulang lang pala
            // ang env. Sinasalo na ito rito nang malinaw.
            if (blank($dsn)) {
                throw new \RuntimeException(
                    'MAIL_MAILER is set to "brevo" but MAILER_DSN is empty. Brevo is the '
                    .'PRODUCTION transport (Render blocks outbound SMTP) — local dev should '
                    .'use MAIL_MAILER=smtp. To use Brevo here anyway, set '
                    .'MAILER_DSN=brevo+api://<API-V3-KEY>@default; that key starts with '
                    .'"xkeysib-", NOT the "xsmtpsib-" SMTP key. Run `php artisan config:clear` '
                    .'after editing .env.'
                );
            }

            return (new BrevoTransportFactory)->create(Dsn::fromString($dsn));
        });

        // The app only loads Bootstrap CSS, not Tailwind, so Laravel's
        // default pagination view (which uses Tailwind utility classes)
        // rendered unstyled. Switch to the Bootstrap 5 view instead.
        Paginator::useBootstrapFive();

        // NOTE: we intentionally do NOT force https here. Forcing it
        // broke direct local access via http://127.0.0.1:8000 (the
        // built-in dev server can't speak TLS, so any https link it
        // generated was unreachable). trustProxies(at: '*') in
        // bootstrap/app.php already handles this correctly per-request:
        // it reads ngrok's X-Forwarded-Proto header and only reports
        // https when the request actually came in through the ngrok
        // tunnel, leaving direct 127.0.0.1 access as plain http.

        // The admin notification bell (layouts/admin.blade.php topbar) and its
        // dropdown (admin/partials/topbar_features.blade.php, included on every
        // admin page) both need this data — share it globally here instead of
        // every controller remembering to pass it.
        View::composer(['layouts.admin', 'admin.partials.topbar_features'], function ($view) {
            if (! Auth::check() || Auth::user()->role !== 'admin') {
                $view->with(['notifications' => collect(), 'unreadCount' => 0]);

                return;
            }

            $view->with([
                'notifications' => Notification::where('user_id', Auth::id())->latest()->take(8)->get(),
                'unreadCount' => Notification::where('user_id', Auth::id())->where('is_read', 0)->count(),
            ]);
        });

        // The customer topbar bell (layouts/customer.blade.php) needs this on
        // every guest-facing page, not just the dashboard — share it globally
        // so pages that don't already pass it still show the unread dot.
        View::composer('layouts.customer', function ($view) {
            if (! Auth::check()) {
                return;
            }

            if (! array_key_exists('unreadNotifications', $view->getData())) {
                $view->with('unreadNotifications', Notification::where('user_id', Auth::id())
                    ->where('is_read', 0)
                    ->get());
            }
        });
    }
}
