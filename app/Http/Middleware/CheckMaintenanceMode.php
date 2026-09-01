<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Gates the anonymous, guest-facing side of routes/web.php (home,
// property browsing, booking form/submit, chatbot) behind the
// "Maintenance Mode" toggle in Admin Settings. Logged-in admins bypass
// it, matching the toggle's own description ("Admin access still
// works") — everything else (auth routes, payment webhooks, the cron
// endpoint, staff/customer portals) is intentionally left outside this
// middleware's route group so they keep working during maintenance.
class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::get('maintenance_mode', '0') !== '1') {
            return $next($request);
        }

        if (Auth::check() && Auth::user()->role === 'admin') {
            return $next($request);
        }

        return response()->view('maintenance', [], 503);
    }
}
