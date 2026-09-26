<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
    /**
     * Refuse to run a schema-destroying command against a remote database
     * without an explicit, deliberate opt-in (Task 12 F1).
     *
     * CLAUDE.md documents this as routine maintenance:
     *
     *     php artisan migrate:fresh --seed --force --database=aiven
     *
     * It drops every table in production. Until v7.41 there was no backup of any
     * kind, so a mistyped `--database` — or that line pasted from the docs into
     * the wrong terminal — destroyed the only copy of every booking and payment
     * the resort had. `db:backup` is the recovery half of that finding; this is
     * the prevention half, and prevention is the cheaper one.
     *
     * WHAT COUNTS AS REMOTE: anything that is not a loopback or the Docker host
     * gateway. That deliberately catches more than just `aiven` — if a second
     * production-ish connection is ever added, it is protected by default rather
     * than by having been remembered here.
     *
     * The opt-in is an environment variable rather than an interactive prompt on
     * purpose: these commands are normally run with `--force`, which exists
     * precisely to suppress prompts, so a prompt would be the one guard the
     * documented invocation already bypasses.
     */
    private function guardDestructiveMigrations(): void
    {
        // Tests build their schema by hand and must not be affected.
        if ($this->app->runningUnitTests()) {
            return;
        }

        Event::listen(function (\Illuminate\Console\Events\CommandStarting $event) {
            $command = (string) $event->command;

            if (! in_array($command, \App\Support\DestructiveCommandGuard::DESTRUCTIVE, true)) {
                return;
            }

            $name = $event->input->hasOption('database')
                ? ($event->input->getOption('database') ?: config('database.default'))
                : config('database.default');

            $override = filter_var(env('ALLOW_DESTRUCTIVE_MIGRATIONS'), FILTER_VALIDATE_BOOLEAN);

            $refusal = \App\Support\DestructiveCommandGuard::refusalFor(
                $command,
                (string) $name,
                config("database.connections.{$name}.host"),
                $override,
            );

            if ($refusal !== null) {
                throw new \RuntimeException($refusal);
            }

            if ($override && ! in_array(
                (string) config("database.connections.{$name}.host"),
                \App\Support\DestructiveCommandGuard::LOCAL_HOSTS,
                true,
            )) {
                $event->output?->writeln(
                    "<comment>ALLOW_DESTRUCTIVE_MIGRATIONS is set — running {$command} against '{$name}'.</comment>"
                );
            }
        });
    }

    /**
     * Make GET /up mean something (Task 12 F7).
     *
     * The framework's health route only proves PHP booted and the container is
     * listening. The outage this deployment is actually likely to have is the
     * free-tier Aiven database being unreachable — at which point the app is up,
     * answering, and useless. So the check touches the database.
     *
     * `SELECT 1` and nothing else. Two reasons it is this small:
     *
     *   - The endpoint is public and unauthenticated. Anything expensive here is
     *     a load amplifier somebody else controls. As it stands a request to /up
     *     costs strictly less than the homepage, which runs dozens of queries —
     *     so this is not a new denial-of-service surface.
     *   - Redis is deliberately NOT checked. Under CACHE_STORE=failover a Redis
     *     outage is designed to be survivable (it falls through to MySQL), so
     *     failing the health check on it would report an outage the app is
     *     specifically built not to have.
     *
     * A throw here becomes a 500 from the health route, which is the signal.
     */
    private function registerHealthCheck(): void
    {
        Event::listen(function (\Illuminate\Foundation\Events\DiagnosingHealth $event) {
            DB::connection()->select('SELECT 1');
        });
    }

    public function boot(): void
    {
        $this->guardDestructiveMigrations();
        $this->registerHealthCheck();

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

        // CACHE_STORE=failover drops to MySQL when Redis can't answer: the
        // site keeps working, just slower. Without this, a Redis outage
        // would leave no trace at all in Render's logs.
        Event::listen(CacheFailedOver::class, function (CacheFailedOver $event) {
            Log::warning("Cache store [{$event->storeName}] failed; fell back to the next store.", [
                'error' => $event->exception->getMessage(),
            ]);
        });

        $this->configureRateLimiting();

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

    /**
     * Named limiters for every public route that sends mail, calls the AI
     * provider, or checks a secret.
     *
     * Two things drive the numbers. Brevo's free tier is 300 emails/day for
     * the WHOLE app, and Groq's free tier is a per-minute and per-day request
     * budget for the whole account — so a per-minute limit alone is not
     * enough: 3/min is ~4,300 emails a day from one script, and once the
     * quota is gone 2FA codes, password resets and booking confirmations
     * all stop together. Hence the hourly/daily caps next to the per-minute
     * ones.
     *
     * Every Limit in one limiter needs its own key prefix: the throttle
     * middleware keys a counter by limiter name + key, so two Limits with
     * the same key would share one counter with two different decay windows.
     *
     * Over-limit answers are a normal form error (or JSON for AJAX), never
     * Laravel's bare 429 page — that page is what a guest saw when the old
     * `throttle:5,60` on booking submit ran out.
     */
    private function configureRateLimiting(): void
    {
        // The two token-gated utility routes: the external cron pinger and the
        // cache diagnostics probe. `hash_equals()` already makes the secret
        // itself impractical to guess, so this is not a guessing guard — it
        // caps what a HOLDER of the token can spend. Both routes do real work
        // on a valid hit (`schedule:run`; hundreds of cache round trips), and
        // neither has any other ceiling. 20/min is far above the once-a-minute
        // the pinger actually needs. The bare 429 is right here: these answer
        // machines, not people.
        RateLimiter::for('token-gated', fn (Request $request) => Limit::perMinute(20)
            ->by('m:'.$request->ip()));

        // Booking submit. It used to be `throttle:5,60`, which counted every
        // POST — including ones rejected by validation, the pending-booking
        // rule or a slot conflict — for a full hour. The real anti-abuse rules
        // live in submitBooking(); this only stops scripted hammering.
        RateLimiter::for('booking-submit', fn (Request $request) => Limit::perMinutes(10, 10)
            ->by('booking:'.$this->throttleIdentity($request))
            ->response($this->throttledBack('Too many booking attempts.', 'dates')));

        // Network-level flood guard only, set high so a shared network
        // (household, resort Wi-Fi, mobile carrier NAT) never trips it by
        // accident. The lockout that stops password guessing counts FAILED
        // logins per account, in AuthController::login().
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(30)
            ->by('m:'.$request->ip())
            ->response($this->throttledBack('Too many login attempts from this network.', 'email')));

        // Profile forms that check the current password (change password,
        // turn off 2FA, deactivate). Without a limit a hijacked session could
        // guess the password here indefinitely. Each form reads its errors
        // from its own bag — see ProfileController — so the message goes
        // into that bag, or it would never appear on the page.
        RateLimiter::for('password-confirm', function (Request $request) {
            [$bag, $field] = match ($request->route()?->getName()) {
                'customer.profile.password' => ['updatePassword', 'current_password'],
                'customer.profile.2fa.toggle' => ['twoFactor', 'password'],
                'customer.profile.deactivate' => ['deactivate', 'password'],
                default => ['default', 'password'],
            };

            return Limit::perMinute(5)
                ->by('u:'.$this->throttleIdentity($request))
                ->response(fn (Request $request, array $headers) => back()->withErrors([
                    $field => 'Too many attempts. Please wait '.$this->retryText($headers).' and try again.',
                ], $bag));
        });

        // Guest-initiated changes to an existing booking. A reschedule runs
        // `reserveSlot()` (a locked transaction on the villa row) and a cancel
        // runs the refund maths and notifies staff, so neither is free — and
        // both can be replayed by a stuck double-tap. The two pages read their
        // errors differently: the reschedule form prints `$errors->first()`
        // and never looks at the `error` flash, while cancel is fired from the
        // bookings list and the booking detail page, which both show `error`.
        RateLimiter::for('booking-change', function (Request $request) {
            $field = $request->route()?->getName() === 'customer.bookings.reschedule.update'
                ? 'checkin'
                : null;

            $respond = $this->throttledBack('Too many changes in a row.', $field);

            return [
                Limit::perMinute(6)->by('m:'.$this->throttleIdentity($request))->response($respond),
                Limit::perHour(30)->by('h:'.$this->throttleIdentity($request))->response($respond),
            ];
        });

        // Walk-in creation. Staff-only, so this is abuse containment rather
        // than a guard against strangers: each walk-in can create a `customer`
        // row AND send that guest an email, both of which draw on the shared
        // Brevo quota. The form prints `$errors->first()`, so the message is
        // attached to a key no input is bound to — it shows at the top without
        // marking an unrelated field invalid.
        RateLimiter::for('walkin-create', function (Request $request) {
            $respond = $this->throttledBack('Too many walk-ins in a row.', 'walkin');

            return [
                Limit::perMinute(5)->by('m:'.$this->throttleIdentity($request))->response($respond),
                Limit::perHour(40)->by('h:'.$this->throttleIdentity($request))->response($respond),
            ];
        });

        // Send Money — this one moves REAL money out of the merchant wallet,
        // ₱10 a transfer, and `RefundTransferService::send()` may retry up to
        // MAX_ATTEMPTS times per click. The service already claims the refund
        // in a locked transaction so a double-click cannot send twice; this is
        // the ceiling for everything a double-click isn't. Deliberately low:
        // there is no legitimate reason to fire these in bursts.
        RateLimiter::for('refund-send', function (Request $request) {
            $respond = $this->throttledBack('Too many transfer attempts.');

            return [
                Limit::perMinute(3)->by('m:'.$this->throttleIdentity($request))->response($respond),
                Limit::perHour(20)->by('h:'.$this->throttleIdentity($request))->response($respond),
            ];
        });

        // Where a guest types the bank/wallet account their refund should go
        // to. No mail and no AI, but it is the one guest-writable field that
        // decides where money lands, and a wrong name only surfaces as a
        // runtime rejection — so repeated rapid rewrites are worth capping.
        RateLimiter::for('refund-destination', fn (Request $request) => Limit::perMinute(10)
            ->by('m:'.$this->throttleIdentity($request))
            ->response($this->throttledBack('Too many updates in a row.')));

        // Review submit/edit re-runs AI moderation (a Groq call) whenever the
        // text changes.
        RateLimiter::for('review-write', fn (Request $request) => Limit::perMinute(10)
            ->by('u:'.$this->throttleIdentity($request))
            ->response($this->throttledBack('Too many review submissions.', 'content')));

        // Issue reports (guest + staff). No mail or AI, but each one alerts
        // the frontdesk and every admin, so a stuck double-tap or a bored
        // guest shouldn't be able to flood both.
        RateLimiter::for('issue-report', fn (Request $request) => [
            Limit::perMinute(3)->by('m:'.$this->throttleIdentity($request))
                ->response($this->throttledBack('Too many reports in a row.')),
            Limit::perHour(20)->by('h:'.$this->throttleIdentity($request))
                ->response($this->throttledBack('Too many reports this hour.')),
        ]);

        // Payment checkout and the status endpoint its page polls. These were
        // `throttle:8,1` and `throttle:60,1`, but a numeric throttle keys its
        // counter by user id alone — no route, no limit — so both shared one
        // counter. The checkout page polls status 15 times a minute, so after
        // ~32s of reading it, "Pay Now" answered a bare 429. Named limiters
        // key by limiter name as well, so each route counts only itself.
        RateLimiter::for('payment-checkout', fn (Request $request) => Limit::perMinute(8)
            ->by('m:'.$this->throttleIdentity($request))
            ->response($this->throttledBack('Too many payment attempts.')));

        // No custom response: the watcher treats any non-2xx as "retry on
        // the next tick", so the default 429 is already the right answer.
        RateLimiter::for('payment-status', fn (Request $request) => Limit::perMinute(60)
            ->by('m:'.$this->throttleIdentity($request)));

        // Contact form emails the resort. The page reads `contact_error`,
        // not `error`, so it has its own response.
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)
            ->by('h:'.$request->ip())
            ->response(fn (Request $request, array $headers) => back()->withInput()->with(
                'contact_error',
                'You have sent several messages already. Please wait '.$this->retryText($headers).' before sending another.',
            )));

        // Registration sends a verification email.
        RateLimiter::for('register', function (Request $request) {
            $respond = $this->throttledBack('Too many sign-up attempts from this network.');

            return [
                Limit::perMinute(3)->by('m:'.$request->ip())->response($respond),
                Limit::perHour(10)->by('h:'.$request->ip())->response($respond),
                Limit::perDay(20)->by('d:'.$request->ip())->response($respond),
            ];
        });

        // Forgot password sends an email to ANY address typed in, so it is
        // capped per address too (the broker's own `throttle` is only one
        // per address per 60s). The same cap applies whether or not the
        // address exists, so it leaks nothing about which emails are
        // registered.
        RateLimiter::for('password-email', function (Request $request) {
            $respond = $this->throttledBack('Too many password reset requests.');

            return [
                Limit::perMinute(3)->by('m:'.$request->ip())->response($respond),
                Limit::perHour(10)->by('h:'.$request->ip())->response($respond),
                Limit::perHour(3)->by('e:'.strtolower(trim((string) $request->input('email'))))->response($respond),
            ];
        });

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by('m:'.$request->ip())
            ->response($this->throttledBack('Too many password reset attempts.')));

        // 2FA resend emails the account owner a new code. Keyed by the
        // pending login (the session's 2fa_user_id) so one account can't be
        // flooded, plus the network so one script can't cycle accounts.
        RateLimiter::for('two-factor-resend', function (Request $request) {
            $respond = $this->throttledBack('Too many code requests.');
            $pending = 'u'.(int) $request->session()->get('2fa_user_id');

            return [
                Limit::perMinute(2)->by('m:'.$pending)->response($respond),
                Limit::perHour(6)->by('h:'.$pending)->response($respond),
                Limit::perHour(20)->by('ip:'.$request->ip())->response($respond),
            ];
        });

        // Network-level guard only. The per-account lockout — the part that
        // actually stops guessing a 6-digit code — is in
        // AuthController::verifyTwoFactor(), because a wrong guess has to
        // cancel the code itself, which middleware can't do.
        RateLimiter::for('two-factor-verify', fn (Request $request) => Limit::perMinute(10)
            ->by('m:'.$request->ip())
            ->response($this->throttledBack('Too many verification attempts.', 'code')));

        RateLimiter::for('verification-send', function (Request $request) {
            $respond = $this->throttledBack('Too many verification emails requested.');

            return [
                Limit::perMinute(2)->by('m:'.$this->throttleIdentity($request))->response($respond),
                Limit::perHour(6)->by('h:'.$this->throttleIdentity($request))->response($respond),
            ];
        });

        // Every chatbot message is two Groq calls (intent + reply), and the
        // Groq budget is shared with review moderation and the admin AI
        // reports — a runaway chat would take those down too.
        RateLimiter::for('chatbot', function (Request $request) {
            $who = $this->throttleIdentity($request);
            $respond = fn (Request $request, array $headers) => response()->json([
                'ok' => false,
                'reply' => "You're sending messages a little too fast. Please wait "
                    .$this->retryText($headers).' and try again. 🙏',
                'property_cards' => [],
            ], 429, $headers);

            return [
                Limit::perMinute(10)->by('m:'.$who)->response($respond),
                Limit::perHour(60)->by('h:'.$who)->response($respond),
                Limit::perDay(150)->by('d:'.$who)->response($respond),
            ];
        });
    }

    /** The signed-in user when there is one, otherwise the client IP. */
    private function throttleIdentity(Request $request): string
    {
        return $request->user() ? 'u'.$request->user()->id : 'ip'.$request->ip();
    }

    /**
     * Over-limit response for a form: back to the form with the message,
     * keeping what was typed (never the password). Given a $field, the
     * message is attached to that field's error; otherwise it is flashed as
     * `error`, which layouts/auth.blade.php shows above every auth form.
     */
    private function throttledBack(string $message, ?string $field = null): \Closure
    {
        return function (Request $request, array $headers) use ($message, $field) {
            $text = $message.' Please wait '.$this->retryText($headers).' and try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $text], 429, $headers);
            }

            $redirect = back()->withInput($request->except(['password', 'password_confirmation', 'current_password']));

            return $field ? $redirect->withErrors([$field => $text]) : $redirect->with('error', $text);
        };
    }

    private function retryText(array $headers): string
    {
        $seconds = (int) ($headers['Retry-After'] ?? 60);

        if ($seconds < 60) {
            return $seconds.' second'.($seconds === 1 ? '' : 's');
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' minute'.($minutes === 1 ? '' : 's');
    }
}
