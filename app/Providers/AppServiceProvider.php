<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
            if (!Auth::check() || Auth::user()->role !== 'admin') {
                $view->with(['notifications' => collect(), 'unreadCount' => 0]);
                return;
            }

            $view->with([
                'notifications' => Notification::where('user_id', Auth::id())->latest()->take(8)->get(),
                'unreadCount'   => Notification::where('user_id', Auth::id())->where('is_read', 0)->count(),
            ]);
        });

        // The customer topbar bell (layouts/customer.blade.php) needs this on
        // every guest-facing page, not just the dashboard — share it globally
        // so pages that don't already pass it still show the unread dot.
        View::composer('layouts.customer', function ($view) {
            if (!Auth::check()) {
                return;
            }

            if (!array_key_exists('unreadNotifications', $view->getData())) {
                $view->with('unreadNotifications', Notification::where('user_id', Auth::id())
                    ->where('is_read', 0)
                    ->get());
            }
        });
    }
}