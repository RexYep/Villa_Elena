<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Takes a guest back to the page they asked for once they have signed in.
 *
 * Every email button ("View your booking", "Add account details") opens a
 * page behind the login. Until now the login always ended on the role's
 * landing page, so the guest signed in and then had to find the booking the
 * email was about.
 *
 * The page is remembered in the session by `remember()`, called from the
 * `auth` middleware's redirect (bootstrap/app.php), and read back by
 * `pathFor()` from AuthController::afterLoginRedirect(). Nothing a visitor
 * types decides where a login lands: there is no `?redirect=` parameter, and
 * what is stored is a path on this site, never a full address.
 *
 * It does not use the framework's own `url.intended`. That key is written
 * for EVERY bounced GET, including a background request from a page left
 * open after the session ran out (the dashboard's stats, the availability
 * grid), and a login would then land on a JSON reply or a fragment of a page.
 */
class LoginReturn
{
    private const SESSION_KEY = 'login_return';

    /**
     * How long a remembered page is honoured. Long enough to find a password
     * and enter an emailed code; short enough that the next person to sign in
     * on a shared computer is not sent to a page someone else asked for.
     */
    public const MINUTES = 30;

    /**
     * Called when a signed-out visitor is turned away from a page.
     *
     * Only a real page load is remembered, and the browser is what says so:
     * it sends `Sec-Fetch-Mode: navigate` for an address bar, a link or a
     * bookmark, and something else for a `fetch()`. The `Accept` header cannot
     * be used for this: the availability grid asks for `text/html` too. A
     * browser too old to send the header gets the landing page, as before.
     */
    public static function remember(Request $request): void
    {
        if (! $request->hasSession()
            || ! $request->isMethod('GET')
            || $request->header('Sec-Fetch-Mode') !== 'navigate'
            || $request->header('Sec-Fetch-Dest', 'document') !== 'document') {
            return;
        }

        $query = $request->getQueryString();

        $request->session()->put(self::SESSION_KEY, [
            // Relative to the application, so it is the same string the
            // router matches and `redirect()->to()` extends.
            'path' => $request->getPathInfo().($query ? '?'.$query : ''),
            'at' => now()->timestamp,
        ]);
    }

    /**
     * The remembered page, if this person may open it; otherwise null and
     * the caller uses the role's landing page.
     *
     * It is always forgotten here, used or not, so it can never be applied
     * to a later login.
     */
    public static function pathFor(User $user, Request $request): ?string
    {
        $remembered = $request->hasSession() ? $request->session()->pull(self::SESSION_KEY) : null;

        if (! is_array($remembered) || ! is_string($remembered['path'] ?? null)) {
            return null;
        }

        if (now()->timestamp - (int) ($remembered['at'] ?? 0) > self::MINUTES * 60) {
            return null;
        }

        $path = $remembered['path'];

        // One leading slash exactly. "//host" and "/\host" are read by a
        // browser as another site.
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        return self::mayOpen($user, $path) ? $path : null;
    }

    /**
     * Whether the page is one that asks for a login and that this role is
     * let into. The page's own `role:` middleware is the rule, so there is no
     * second list of who may go where to keep in step.
     *
     * Without this a staff member signing in on a browser that last asked for
     * an admin page would sign in straight into a 403.
     */
    private static function mayOpen(User $user, string $path): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return false;
        }

        $middleware = $route->gatherMiddleware();

        if (! in_array('auth', $middleware, true)) {
            return false;
        }

        foreach ($middleware as $name) {
            if (is_string($name) && str_starts_with($name, 'role:')
                && ! in_array($user->role, explode(',', substr($name, 5)), true)) {
                return false;
            }
        }

        return true;
    }
}
