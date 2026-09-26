<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two items carried out of the Task 5 report: hashing the trusted-device
 * token, and moving the cron secret out of the URL path.
 *
 * Tables are built by hand: the real migrations are MySQL-only and don't run
 * on the in-memory SQLite test connection.
 */
class TrustedDeviceAndCronSecretTest extends TestCase
{
    private const SECRET = 'test-cron-secret-value';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->makeTables();

        config(['app.cron_secret' => self::SECRET]);
    }

    // ── Trusted device tokens ──────────────────────────────────────

    /**
     * The point of the whole change: what sits in the table must not be
     * something anyone can put in a cookie.
     */
    public function test_the_raw_token_is_never_written_to_the_table(): void
    {
        $raw = 'a-raw-trusted-device-token';

        TrustedDevice::create([
            'user_id' => $this->makeUser()->id,
            'token_hash' => TrustedDevice::hashToken($raw),
            'device_label' => 'Test device',
            'expires_at' => now()->addDays(30),
        ]);

        $stored = TrustedDevice::first()->token_hash;

        $this->assertNotSame($raw, $stored);
        $this->assertSame(hash('sha256', $raw), $stored);
        $this->assertSame(64, strlen($stored), 'Hex SHA-256 must still fit the string(64) column.');
    }

    /** A lookup by the raw cookie value still finds its row. */
    public function test_a_live_device_is_found_by_its_raw_cookie_value(): void
    {
        $user = $this->makeUser();
        $this->makeDevice($user, 'the-real-token');

        $this->assertTrue(
            TrustedDevice::query()->where('user_id', $user->id)->activeForToken('the-real-token')->exists()
        );
        $this->assertFalse(
            TrustedDevice::query()->where('user_id', $user->id)->activeForToken('a-different-token')->exists()
        );
    }

    /**
     * Looking the row up by the value the DATABASE holds must fail. If this
     * ever passes, the stored value is presentable again and the change has
     * been undone.
     */
    public function test_the_stored_value_cannot_itself_be_used_as_a_token(): void
    {
        $user = $this->makeUser();
        $this->makeDevice($user, 'the-real-token');

        $stored = TrustedDevice::first()->token_hash;

        $this->assertFalse(
            TrustedDevice::query()->where('user_id', $user->id)->activeForToken($stored)->exists(),
            'Someone who can read trusted_devices must not be able to replay what they read.'
        );
    }

    /** An expired grant is not a grant, hash or no hash. */
    public function test_an_expired_device_is_not_matched(): void
    {
        $user = $this->makeUser();
        $this->makeDevice($user, 'stale-token', expired: true);

        $this->assertFalse(
            TrustedDevice::query()->where('user_id', $user->id)->activeForToken('stale-token')->exists()
        );
    }

    /**
     * An empty or absent cookie must match nothing. Without the guard a NULL
     * falls through to hash('') and matches whichever row happens to hold it.
     */
    public function test_an_empty_cookie_matches_no_device(): void
    {
        $user = $this->makeUser();
        $this->makeDevice($user, 'a-token');

        foreach ([null, ''] as $empty) {
            $this->assertFalse(
                TrustedDevice::query()->activeForToken($empty)->exists(),
                'An absent cookie must never select a row.'
            );
            $this->assertFalse(TrustedDevice::first()->matchesToken($empty));
        }
    }

    /** The "This device" tag on the profile page. */
    public function test_matches_token_identifies_only_the_presenting_device(): void
    {
        $user = $this->makeUser();
        $this->makeDevice($user, 'this-browser');
        $this->makeDevice($user, 'other-browser');

        $devices = TrustedDevice::orderBy('id')->get();

        $this->assertTrue($devices[0]->matchesToken('this-browser'));
        $this->assertFalse($devices[1]->matchesToken('this-browser'));
    }

    /** The hash must never ride along in a JSON payload. */
    public function test_the_hash_is_hidden_from_serialisation(): void
    {
        $this->makeDevice($this->makeUser(), 'a-token');

        $this->assertArrayNotHasKey('token_hash', TrustedDevice::first()->toArray());
    }

    /**
     * The login path is what actually has to keep working. AuthController looks
     * devices up through the scope, so a raw-value lookup left behind anywhere
     * would show up as a trusted browser being asked for a code again.
     */
    public function test_the_auth_controller_looks_devices_up_through_the_scope(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/AuthController.php'));

        $this->assertStringContainsString('activeForToken(', $source);
        $this->assertStringContainsString('TrustedDevice::hashToken(', $source);
        $this->assertStringNotContainsString("->where('token',", $source, 'A raw-token lookup is still in place.');
    }

    /**
     * The end-to-end one, and the reason the others exist: a browser holding a
     * valid `trusted_device` cookie must still skip the emailed 2FA code. If
     * the write side and the read side ever disagree about hashing, this is
     * what breaks — every trusted device quietly starts asking for a code
     * again, which looks like an email problem, not a storage change.
     */
    public function test_a_trusted_cookie_still_skips_the_two_factor_code(): void
    {
        $this->makeLoginTables();

        $user = $this->makeUser();
        $user->forceFill([
            'password' => Hash::make('correct-horse-battery'),
            'two_factor_enabled' => true,
        ])->save();
        $user->markEmailAsVerified();

        $this->makeDevice($user, 'browser-cookie-value');

        $this->withCookie('trusted_device', 'browser-cookie-value')
            ->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect('/my/');

        $this->assertAuthenticatedAs($user);
    }

    /** ...and a browser without one is still sent to the 2FA step. */
    public function test_an_unknown_cookie_does_not_skip_the_two_factor_code(): void
    {
        $this->makeLoginTables();

        $user = $this->makeUser();
        $user->forceFill([
            'password' => Hash::make('correct-horse-battery'),
            'two_factor_enabled' => true,
        ])->save();
        $user->markEmailAsVerified();

        $this->makeDevice($user, 'a-different-browser');

        $this->withCookie('trusted_device', 'not-a-real-token')
            ->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('two-factor.verify'));

        $this->assertGuest();
    }

    // ── Cron secret ────────────────────────────────────────────────

    public function test_the_cron_endpoint_accepts_the_secret_as_a_header(): void
    {
        $this->withHeader('X-Cron-Secret', self::SECRET)
            ->get('/cron/run-schedule')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_the_cron_endpoint_rejects_a_missing_or_wrong_header(): void
    {
        $this->get('/cron/run-schedule')->assertForbidden();

        $this->withHeader('X-Cron-Secret', 'not-the-secret')
            ->get('/cron/run-schedule')
            ->assertForbidden();
    }

    /**
     * An unset CRON_SECRET must not become an open door. `hash_equals('', '')`
     * is true, so without the emptiness check a caller sending an empty header
     * would run the scheduler.
     */
    public function test_an_unset_secret_locks_the_endpoint_rather_than_opening_it(): void
    {
        config(['app.cron_secret' => null]);

        $this->withHeader('X-Cron-Secret', '')->get('/cron/run-schedule')->assertForbidden();
        $this->get('/cron/run-schedule')->assertForbidden();
        $this->get('/cron/run-schedule/'.self::SECRET)->assertForbidden();
    }

    /**
     * These are called by a machine that discards cookies. In the `web` group
     * every ping wrote a `sessions` row and returned a Set-Cookie — once a
     * minute, forever, on a free-tier database.
     */
    public function test_the_cron_routes_start_no_session(): void
    {
        $response = $this->withHeader('X-Cron-Secret', self::SECRET)->get('/cron/run-schedule');

        $this->assertSame([], $response->headers->getCookies(), 'A machine endpoint must not be handed a cookie.');

        $router = app('router');

        foreach ($router->getRoutes() as $route) {
            $machineFacing = str_starts_with($route->uri(), 'cron/')
                || str_starts_with($route->uri(), 'diagnostics/')
                // The PayMongo webhooks joined routes/cron.php in v7.28, for
                // the same reason: a `sessions` row on every delivery.
                || str_starts_with($route->uri(), 'webhooks/');

            if (! $machineFacing) {
                continue;
            }

            $stack = array_map(
                fn ($m) => is_string($m) ? $m : get_class($m),
                $router->gatherRouteMiddleware($route)
            );

            $this->assertNotContains(
                \Illuminate\Session\Middleware\StartSession::class,
                $stack,
                $route->uri().' is back in the web group.'
            );
        }
    }

    /**
     * The diagnostics route shares the same secret, so leaving it in the URL
     * path would have defeated the point — it moved too.
     *
     * Only the gate is asserted. What the endpoint returns is a walk over the
     * whole dashboard schema (bookings, payments, properties, users), and
     * rebuilding all of that by hand to prove a header check would be a lot of
     * fixture for no extra coverage. The gate is the thing that changed, so
     * "not 403" is the honest assertion: the request got past it.
     */
    public function test_the_diagnostics_endpoint_uses_the_same_header(): void
    {
        $this->get('/diagnostics/cache')->assertForbidden();
        $this->withHeader('X-Cron-Secret', 'not-the-secret')->get('/diagnostics/cache')->assertForbidden();

        $this->assertNotSame(
            403,
            $this->withHeader('X-Cron-Secret', self::SECRET)->get('/diagnostics/cache?iterations=1')->status(),
            'A correct header must get past the gate.'
        );
    }

    /**
     * The legacy path still works — a deploy must not silently stop the
     * scheduler — but every use is logged, so there is a way to tell when the
     * pinger has been switched over and these routes can be deleted.
     */
    public function test_the_legacy_path_still_works_but_warns(): void
    {
        // A spy, not shouldReceive(): the latter replaces the whole LogManager
        // with a strict mock, and `schedule:run` reports its own failures
        // through Log::error() — which then blows up as an unexpected call and
        // fails this test for a reason that has nothing to do with the route.
        Log::spy();

        $this->get('/cron/run-schedule/'.self::SECRET)->assertOk();

        // `->once()` counted EVERY warning, not just the matching one, and v7.42
        // added a second: `schedule:run` invokes bookings:auto-checkinout, which
        // fails here because the test database has no `bookings` table, and Task
        // 12 F6 now records a reported exception as an application_error. That is
        // the intended behaviour — a scheduled command failing silently is
        // precisely what F6 exists to surface, and this one drives the
        // stale-booking sweep and refund reconciliation.
        //
        // So the count is scoped to the message this test is about. Same
        // narrowing, same reasoning, as test_the_legacy_path_still_rejects_a_wrong_token.
        Log::shouldHaveReceived('warning')
            ->atLeast()->once()
            ->withArgs(fn ($message) => str_contains((string) $message, 'Deprecated cron secret in URL path'));
    }

    public function test_the_legacy_path_still_rejects_a_wrong_token(): void
    {
        Log::spy();

        $this->get('/cron/run-schedule/wrong-token')->assertForbidden();

        // This assertion was `Log::shouldNotHaveReceived('warning')` — no warning
        // of ANY kind — until v7.41 made a rejected secret a recorded security
        // event (Task 12 F4), which is itself a warning.
        //
        // The narrowing is deliberate and the test's purpose is unchanged. What
        // it exists to prove is that a REJECTED token is not counted as legacy
        // usage: that deprecation warning is the signal for "the pinger has not
        // been switched over yet, do not delete these routes", and letting a
        // stranger's wrong guess emit it would make the signal useless. That is
        // still exactly what is asserted, just by message instead of by volume.
        Log::shouldNotHaveReceived('warning', [
            \Mockery::on(fn ($message) => str_contains((string) $message, 'Deprecated cron secret in URL path')),
            \Mockery::any(),
        ]);

        // And the rejection is no longer silent, which is the point of F4: these
        // endpoints are unauthenticated and a shared secret is all that guards
        // them, so a wrong one has to leave a trace.
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains((string) $message, 'cron_secret_rejected'));
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function makeDevice(User $user, string $rawToken, bool $expired = false): TrustedDevice
    {
        return TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => TrustedDevice::hashToken($rawToken),
            'device_label' => 'Test device',
            'last_used_at' => now(),
            'expires_at' => $expired ? now()->subDay() : now()->addDays(30),
        ]);
    }

    private function makeUser(): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'full_name' => 'Test Person',
            'email' => "person{$n}@example.test",
            'password' => 'irrelevant-for-these-tests',
            'role' => 'customer',
            'status' => 1,
        ]);
    }

    /** Extra tables the real login path writes to on the way through. */
    private function makeLoginTables(): void
    {
        Schema::create('staff_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('target_table')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('login_activities', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_label')->nullable();
            $table->boolean('via_new_device_otp')->default(false);
            $table->timestamps();
        });
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

        // CacheDiagnostics::measure() reads through Setting::get().
        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::create('trusted_devices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token_hash', 64);
            $table->string('device_label')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
