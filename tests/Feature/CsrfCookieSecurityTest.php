<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task 5 — CSRF, cookies and request security.
 *
 * Tables are built by hand: the real migrations are MySQL-only and don't run
 * on the in-memory SQLite test connection.
 */
class CsrfCookieSecurityTest extends TestCase
{
    /** Routes that are allowed to answer a state-changing request with no CSRF token. */
    private const CSRF_EXEMPT_URIS = [
        // Server-to-server. Authenticated by the Paymongo-Signature HMAC.
        'webhooks/paymongo',
        'webhooks/paymongo/transfer',
        // Exempted by the FRAMEWORK, not by us — BroadcastManager::routes()
        // calls ->withoutMiddleware([VerifyCsrfToken::class]) itself. Safe only
        // because the response is not cross-origin readable; see the CORS test
        // below, which is what actually guards it.
        'broadcasting/auth',
        // Dev-only (local disk + uncached routes). Gated by a URL signature in
        // the framework's ReceiveFile, not by session state.
        'storage/{path}',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Resolving the kernel is what syncs the middleware GROUPS onto the
        // router. Without it 'web' stays an unexpanded string and every route
        // looks like it has no CSRF middleware.
        app(\Illuminate\Contracts\Http\Kernel::class);
    }

    /**
     * Run a callback with the app pretending not to be under test.
     *
     * ValidateCsrfToken::handle() opens with `$this->runningUnitTests()` and
     * skips the whole check when it is true, so by default EVERY assertion
     * about 419s in a PHPUnit test passes without exercising one line of CSRF
     * code. Flipping the container's `env` binding is what makes these tests
     * mean anything — it is also the only honest way to prove the webhook
     * exemptions still work.
     */
    private function withCsrfEnforced(callable $callback): mixed
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            return $callback();
        } finally {
            $this->app['env'] = $original;

            // Restoring the env binding is not enough. Any request made while
            // it said `production` also made TrustHosts believe it should act,
            // and TrustHosts pins the host list in a STATIC on Symfony's
            // Request — which outlives this test, this class and the container
            // rebuild between tests. It leaked {^(.+\.)?127\.0\.0\.1$} into the
            // rest of the process, so a later test requesting any other host
            // got a 400 raised before its own middleware ever ran. Measured:
            // XssAndHeadersTest passed alone and failed after this file.
            \Symfony\Component\HttpFoundation\Request::setTrustedHosts([]);
        }
    }

    // ── CSRF coverage ──────────────────────────────────────────────

    /**
     * The whole point of the task in one assertion: walk every route that can
     * change state and prove the CSRF middleware is actually in its resolved,
     * priority-sorted stack — not just in a group name we assume expands.
     */
    public function test_every_state_changing_route_carries_the_csrf_middleware(): void
    {
        $router = app('router');
        $unprotected = [];
        $checked = 0;
        $expansionSeen = false;

        foreach ($router->getRoutes() as $route) {
            if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            $stack = array_map(
                fn ($m) => is_string($m) ? $m : (is_object($m) ? get_class($m) : gettype($m)),
                $router->gatherRouteMiddleware($route)
            );

            // Guard against the test passing for the wrong reason. Before the
            // kernel syncs its groups to the router, 'web' comes back as the
            // literal string and EVERY route looks unprotected. Seeing a class
            // from inside the group proves the expansion happened.
            if (in_array(\Illuminate\Cookie\Middleware\EncryptCookies::class, $stack, true)) {
                $expansionSeen = true;
            }

            $checked++;
            $protected = (bool) array_filter($stack, fn ($m) => str_contains($m, 'CsrfToken'));

            if (! $protected && ! in_array($route->uri(), self::CSRF_EXEMPT_URIS, true)) {
                $unprotected[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertTrue($expansionSeen, 'Middleware groups were never expanded — this test would pass vacuously.');
        $this->assertGreaterThan(50, $checked, 'Expected the app to have plenty of state-changing routes.');
        $this->assertSame([], $unprotected, "State-changing routes with no CSRF middleware:\n".implode("\n", $unprotected));
    }

    /**
     * A POST with no token is rejected before it reaches any controller.
     *
     * Note the 419 arrives ahead of validation: /login below sends a password
     * too short to pass its own rules, and without CSRF enforcement this
     * returns 302 with a validation error instead — which is exactly what the
     * first version of this test asserted against, and it proved nothing.
     */
    public function test_a_tokenless_post_is_rejected_with_419(): void
    {
        $this->withCsrfEnforced(function () {
            $this->post('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertStatus(419);
            $this->post('/contact', [])->assertStatus(419);
        });
    }

    /**
     * The exemption list must stay exactly two entries long. PayMongo disables
     * a webhook that answers 4xx, so these two cannot be protected — but every
     * addition here is a hole, so the list itself is the thing worth pinning.
     */
    public function test_only_the_two_paymongo_webhooks_are_csrf_exempt(): void
    {
        $excluded = app(ValidateCsrfToken::class)->getExcludedPaths();

        sort($excluded);

        $this->assertSame(['webhooks/paymongo', 'webhooks/paymongo/transfer'], $excluded);
    }

    /**
     * ...and they must genuinely still work without one. PayMongo disables a
     * webhook that repeatedly answers 4xx and never re-enables it on its own,
     * so a 419 here would silently stop every payment from being recorded.
     */
    public function test_the_webhooks_still_accept_a_tokenless_post(): void
    {
        $this->makeTables();

        $this->withCsrfEnforced(function () {
            $this->post('/webhooks/paymongo', [])->assertStatus(200);
            $this->post('/webhooks/paymongo/transfer', [])->assertStatus(200);
        });
    }

    /**
     * /broadcasting/auth has no CSRF middleware — the framework removes it. The
     * only reason that is harmless is that a forged cross-site call cannot READ
     * the Pusher channel signature it returns.
     *
     * Two things stop that, and neither is "there is no CORS config": the
     * framework merges its own default even though config/cors.php was never
     * published, and that default is NOT empty — it is
     * `['api/*', 'sanctum/csrf-cookie']` with `allowed_origins => ['*']`.
     * What actually holds is that no path matches broadcasting/auth, and that
     * credentials are not supported (with which `*` would be void anyway).
     */
    public function test_cors_cannot_expose_the_broadcasting_auth_response(): void
    {
        $matching = array_values(array_filter(
            config('cors.paths', []),
            fn ($path) => Str::is($path, 'broadcasting/auth')
        ));

        $this->assertSame([], $matching, 'A CORS path now matches broadcasting/auth, which is CSRF-exempt.');

        $this->assertFalse(
            (bool) config('cors.supports_credentials'),
            'Credentialed CORS would make the broadcasting/auth channel signature readable cross-origin.'
        );
    }

    // ── F1: the payment cancel callback ────────────────────────────

    /**
     * /pay/{booking}/cancel is PayMongo's cancel_url, so it has to be a GET and
     * CSRF cannot cover it. Nulling paymongo_session_id is not cosmetic: it is
     * the field reusableCheckout() keys off, so losing it makes the guest's
     * next attempt open a SECOND live checkout session for the same booking.
     */
    public function test_an_unsigned_cancel_does_not_clear_the_checkout_session(): void
    {
        [$user, $booking] = $this->makeBooking();

        $this->actingAs($user)
            ->get(route('payment.cancel', $booking))
            ->assertRedirect(route('customer.bookings.show', $booking));

        $this->assertSame(
            'cs_existing_session',
            $booking->fresh()->paymongo_session_id,
            'An unsigned hit must leave the reusable checkout session alone.'
        );
    }

    /** The real callback, with the signature createCheckout() put on it. */
    public function test_a_signed_cancel_clears_the_checkout_session(): void
    {
        [$user, $booking] = $this->makeBooking();

        $url = URL::temporarySignedRoute('payment.cancel', now()->addHours(24), $booking->id);

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('customer.bookings.show', $booking));

        $this->assertNull($booking->fresh()->paymongo_session_id);
    }

    /** An expired signature is treated exactly like a forged one. */
    public function test_an_expired_signature_does_not_clear_the_checkout_session(): void
    {
        [$user, $booking] = $this->makeBooking();

        $url = URL::temporarySignedRoute('payment.cancel', now()->addHours(24), $booking->id);

        $this->travel(25)->hours();

        $this->actingAs($user)->get($url);

        $this->assertSame('cs_existing_session', $booking->fresh()->paymongo_session_id);
    }

    /** Ownership is still checked first — the signature never replaces it. */
    public function test_a_signed_cancel_for_someone_elses_booking_is_forbidden(): void
    {
        [, $booking] = $this->makeBooking();
        $intruder = $this->makeUser('intruder@example.test');

        $url = URL::temporarySignedRoute('payment.cancel', now()->addHours(24), $booking->id);

        $this->actingAs($intruder)->get($url)->assertStatus(403);

        $this->assertSame('cs_existing_session', $booking->fresh()->paymongo_session_id);
    }

    // ── F3/F4: cookie flags ────────────────────────────────────────

    /**
     * The flag used to live only in render.yaml, which governs a service only
     * if that service is blueprint-managed — and parts of this deployment were
     * created by hand in the dashboard. config/session.php now defaults it from
     * APP_ENV so production is Secure whether or not the variable arrives.
     */
    public function test_the_session_cookie_is_secure_by_default_in_production(): void
    {
        $this->assertTrue($this->sessionConfigUnder('production')['secure']);
        $this->assertFalse($this->sessionConfigUnder('local')['secure']);
        $this->assertFalse($this->sessionConfigUnder('testing')['secure']);
    }

    /** An explicit opt-out must still win over the environment default. */
    public function test_an_explicit_false_still_overrides_the_production_default(): void
    {
        $this->assertFalse($this->sessionConfigUnder('production', 'false')['secure']);
    }

    public function test_the_session_cookie_is_http_only(): void
    {
        $this->assertTrue(config('session.http_only'));
    }

    /**
     * "strict" looks safer and breaks two live flows: PayMongo's redirect back
     * to /pay/{booking}/success (the guest lands unauthenticated, right after
     * paying) and emailed verification links opened from a webmail tab.
     */
    public function test_same_site_stays_lax(): void
    {
        $this->assertSame('lax', config('session.same_site'));
        $this->assertSame('lax', $this->sessionConfigUnder('production')['same_site']);
    }

    /**
     * The trusted-device cookie is a stored "skip 2FA" grant with a 60-day life,
     * so it must carry the same flags as the session cookie. It does, but only
     * because CookieJar takes its defaults from the session config — which is
     * easy to break by hand-rolling a Cookie instance somewhere.
     */
    public function test_the_trusted_device_cookie_inherits_the_session_flags(): void
    {
        $cookie = app('cookie')->make('trusted_device', 'token-value', 60 * 24 * 60);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame(config('session.secure'), $cookie->isSecure());
    }

    // ── F6: response headers ───────────────────────────────────────

    /**
     * RETARGETED. This used to grep docker/nginx.conf.template for the four
     * header NAMES, on the reasoning that nginx was where they lived and there
     * was no middleware to interrogate. Both halves stopped being true:
     *
     *   - Two of the four were measured ABSENT from the live site while that
     *     file claimed them, because the edit adding them was never committed
     *     — a grep of a config file cannot see that.
     *   - Worse, the grep passes on prose. Once the file carried a comment
     *     explaining where the headers moved to, the old assertion went on
     *     passing while nginx set none of them.
     *
     * They are asserted against a real response in XssAndHeadersTest now. What
     * is left here is the part that genuinely still belongs to nginx: files
     * under /storage/ and the static extensions are served off disk without
     * ever entering PHP, so the middleware cannot reach them.
     */
    public function test_nginx_still_sets_nosniff_on_files_that_bypass_php(): void
    {
        $conf = file_get_contents(base_path('docker/nginx.conf.template'));

        // The two locations nginx answers by itself. /storage/ is the one
        // directory guests can write into, so it is the one that matters most.
        foreach (['location ^~ /storage/', 'location ~* \.(jpg|jpeg|png'] as $location) {
            $block = substr($conf, strpos($conf, $location));
            $block = substr($block, 0, strpos($block, '}'));

            $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff" always;', $block,
                "Files served directly by `{$location}` never reach the middleware.");
        }

        // And nginx must NOT set the other three: `add_header` appends rather
        // than replaces, and browsers ignore a duplicated X-Frame-Options
        // entirely, so a well-meaning re-add would quietly remove the
        // protection it looks like it is doubling.
        foreach (['X-Frame-Options', 'Referrer-Policy', 'Strict-Transport-Security'] as $header) {
            $this->assertStringNotContainsString("add_header {$header}", $conf,
                "{$header} is set by the middleware; setting it here too duplicates it.");
        }
    }

    // ── Forms ──────────────────────────────────────────────────────

    /**
     * A missing @csrf does not fail loudly — the form just 419s the first guest
     * who uses it, which is the kind of thing that ships. Scan them all.
     */
    public function test_every_state_changing_blade_form_has_a_csrf_token(): void
    {
        $open = '<'.'form';
        $close = '</'.'form>';
        $missing = [];
        $scanned = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $src = file_get_contents($file->getPathname());
            $offset = 0;

            while (($start = stripos($src, $open, $offset)) !== false) {
                $end = stripos($src, $close, $start);
                $block = $end === false ? substr($src, $start) : substr($src, $start, $end - $start);
                $offset = $start + 5;

                $tagEnd = strpos($block, '>');
                $tag = substr($block, 0, $tagEnd === false ? 200 : $tagEnd + 1);

                $method = preg_match('/method\s*=\s*["\']?\s*(\w+)/i', $tag, $m) ? strtoupper($m[1]) : 'GET';

                if ($method === 'GET') {
                    continue;
                }

                $scanned++;

                if (! preg_match('/@csrf|csrf_field\(|csrf-token|_token/i', $block)) {
                    $line = substr_count(substr($src, 0, $start), "\n") + 1;
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.$line;
                }
            }
        }

        $this->assertGreaterThan(50, $scanned, 'Expected to find plenty of state-changing forms.');
        $this->assertSame([], $missing, "Forms with no CSRF token:\n".implode("\n", $missing));
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Re-evaluate config/session.php with APP_ENV swapped, so the fallback in
     * that file is what is under test rather than the value already booted.
     */
    private function sessionConfigUnder(string $env, ?string $secure = null): array
    {
        $keys = ['APP_ENV' => $env, 'SESSION_SECURE_COOKIE' => $secure];

        // env() reads through three sources and PHPUnit does not populate them
        // identically — phpunit.xml's <env> entries land in $_ENV and putenv()
        // but NOT in $_SERVER. Snapshotting only one of them and then clearing
        // all three on the way out is how an earlier version of this helper
        // deleted APP_ENV for the whole process: every later test then booted
        // as `production`, CSRF started firing, and 18 unrelated tests failed.
        // Snapshot each source separately and put each back as it was.
        $snapshot = [];

        foreach (array_keys($keys) as $key) {
            $snapshot[$key] = [
                'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
                'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
                'putenv' => getenv($key),
            ];
        }

        foreach ($keys as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key], $_ENV[$key]);
                putenv($key);
            } else {
                $_SERVER[$key] = $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        try {
            return require base_path('config/session.php');
        } finally {
            foreach ($snapshot as $key => $was) {
                if ($was['server'] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $was['server'];
                }

                if ($was['env'] === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $was['env'];
                }

                if ($was['putenv'] === false) {
                    putenv($key);
                } else {
                    putenv("$key={$was['putenv']}");
                }
            }
        }
    }

    /** @return array{0: User, 1: Booking} */
    private function makeBooking(): array
    {
        Event::fake();

        if (! Schema::hasTable('users')) {
            $this->makeTables();
        }

        $user = $this->makeUser('guest@example.test');

        $property = Property::create([
            'property_name' => 'Villa Elena (Whole Villa)',
            'type' => 'villa',
            'status' => 'available',
            'base_price' => 4000,
            'weekend_price' => 6000,
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'check_in_date' => now()->addDays(7)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'check_in_time' => '08:00:00',
            'check_out_time' => '17:00:00',
            'num_guests' => 2,
            'base_amount' => 4000,
            'total_amount' => 4000,
            'amount_paid' => 0,
            'balance_due' => 4000,
            'status' => 'pending',
            'source' => 'online',
        ]);

        $booking->forceFill(['paymongo_session_id' => 'cs_existing_session'])->save();

        return [$user, $booking];
    }

    private function makeUser(string $email): User
    {
        $user = User::create([
            'full_name' => 'Test Person',
            'email' => $email,
            'password' => 'irrelevant-for-these-tests',
            'role' => 'customer',
            'status' => 1,
        ]);

        $user->markEmailAsVerified();

        return $user->fresh();
    }

    private function makeTables(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('customer');
            $table->integer('status')->default(1);
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->string('phone')->nullable();
            $table->string('profile_image')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('properties', function ($table) {
            $table->id();
            $table->string('property_name');
            $table->string('type')->default('villa');
            $table->string('status')->default('available');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('weekend_price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->string('booking_ref')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->integer('num_guests')->default(1);
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('source')->nullable();
            $table->string('paymongo_session_id')->nullable();
            $table->string('paymongo_payment_type')->nullable();
            $table->timestamp('overpayment_notified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // transferCallback() looks up the transfer the callback names before
        // it trusts anything in the payload.
        Schema::create('refund_transfers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('provider_transfer_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
}
