<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 4 — authentication and session security.
 *
 * Tables are built by hand: the real migrations are MySQL-only and don't run
 * on the in-memory SQLite test connection.
 */
class AuthenticationSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->makeTables();
    }

    // ── Finding 1: the verification link is not a login ────────────

    /**
     * The planted-account case: someone registers a stranger's address with
     * their own password, and the stranger clicks the genuine email. The click
     * proves the inbox, not the password, so on its own it must do nothing.
     */
    public function test_the_verification_link_alone_neither_verifies_nor_signs_anyone_in(): void
    {
        $user = $this->makeUser(verified: false);

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertFalse($user->fresh()->hasVerifiedEmail(), 'A click without the password must not verify the account.');
        // A 60-minute emailed URL must not be a complete login.
        $this->assertGuest();
    }

    /** The real path: open the link signed out, sign in, and it completes. */
    public function test_signing_in_after_opening_the_link_finishes_the_verification(): void
    {
        $user = $this->makeUser(verified: false, email: 'pending@example.test');
        $url = $this->verificationUrl($user);

        $this->get($url)->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'pending@example.test', 'password' => 'the-real-password'])
            ->assertRedirect($url);

        $this->get($url)->assertRedirect('/my/');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /** Signing in as a different account must not be bounced to the link. */
    public function test_a_remembered_link_is_dropped_when_someone_else_signs_in(): void
    {
        $pending = $this->makeUser(verified: false);
        $this->makeUser(email: 'other@example.test');

        $this->get($this->verificationUrl($pending))->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'other@example.test', 'password' => 'the-real-password'])
            ->assertRedirect('/my/');

        $this->assertFalse($pending->fresh()->hasVerifiedEmail());
    }

    /**
     * The sharpest version of the old bug: a guest with 2FA switched on could
     * be signed in from a link, with no password and no emailed code.
     */
    public function test_the_verification_link_does_not_bypass_two_factor(): void
    {
        $user = $this->makeUser(verified: false);
        $user->update(['two_factor_enabled' => true]);

        $this->get($this->verificationUrl($user))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_verification_link_does_not_sign_in_a_deactivated_account(): void
    {
        $user = $this->makeUser(verified: false);
        $user->update(['status' => 0]);

        $this->get($this->verificationUrl($user))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** Someone already signed in as that user loses nothing. */
    public function test_a_user_already_signed_in_still_lands_on_their_dashboard(): void
    {
        $user = $this->makeUser(verified: false);

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect('/my/');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /** Opening someone else's link must not redirect by THEIR role. */
    public function test_a_signed_in_user_opening_someone_elses_link_is_not_redirected_by_their_role(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $customer = $this->makeUser(verified: false);

        $this->actingAs($customer)
            ->get($this->verificationUrl($admin))
            ->assertRedirect('/my/')
            ->assertSessionHas('error');
    }

    // ── Findings 2 & 3: a new password ends every other way in ─────

    public function test_changing_the_password_drops_other_sessions_and_all_trusted_devices(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->seedSession('keep-this-one', $user->id);
        $this->seedSession('intruder-session', $user->id);
        $this->seedSession('someone-elses', $other->id);

        $this->makeTrustedDevice($user, 'device-a');
        $this->makeTrustedDevice($user, 'device-b');
        $this->makeTrustedDevice($other, 'other-users-device');

        $revoked = $user->revokeOtherLogins('keep-this-one');

        $this->assertSame(1, $revoked['sessions']);
        $this->assertSame(2, $revoked['devices']);

        $this->assertNotNull(DB::table('sessions')->find('keep-this-one'), 'The caller keeps their own session.');
        $this->assertNull(DB::table('sessions')->find('intruder-session'), 'Every other session must go.');
        $this->assertNotNull(DB::table('sessions')->find('someone-elses'), 'Another user must be untouched.');

        $this->assertSame(0, TrustedDevice::where('user_id', $user->id)->count(), 'A trusted device is a stored 2FA bypass.');
        $this->assertSame(1, TrustedDevice::where('user_id', $other->id)->count());
    }

    /** A reset has no session to spare — everything goes. */
    public function test_a_reset_drops_every_session_including_the_current_one(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->makeUser();
        $this->seedSession('a', $user->id);
        $this->seedSession('b', $user->id);

        $revoked = $user->revokeOtherLogins();

        $this->assertSame(2, $revoked['sessions']);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    /**
     * Local dev runs the `file` driver, which stores no user id. The method
     * must degrade to a no-op there rather than throwing — but it must still
     * revoke trusted devices, which live in the database either way.
     */
    public function test_session_revocation_is_a_no_op_on_a_non_database_driver(): void
    {
        config(['session.driver' => 'file']);

        $user = $this->makeUser();
        $this->seedSession('untouched', $user->id);
        $this->makeTrustedDevice($user, 'still-revoked');

        $revoked = $user->revokeOtherLogins();

        $this->assertSame(0, $revoked['sessions']);
        $this->assertNotNull(DB::table('sessions')->find('untouched'));
        $this->assertSame(1, $revoked['devices'], 'Trusted devices are in the DB regardless of session driver.');
    }

    /** Both password paths must call it — not just the one someone remembered. */
    public function test_both_password_paths_revoke_other_logins(): void
    {
        $this->assertStringContainsString(
            'revokeOtherLogins()',
            file_get_contents(app_path('Http/Controllers/AuthController.php')),
            'The password RESET flow must end other logins.'
        );

        $this->assertStringContainsString(
            'revokeOtherLogins($request->session()->getId())',
            file_get_contents(app_path('Http/Controllers/Customer/ProfileController.php')),
            'The password CHANGE flow must end other logins, keeping the current session.'
        );
    }

    // ── Finding 5: login must not leak who has an account ──────────

    public function test_a_missing_account_costs_the_same_as_a_wrong_password(): void
    {
        $this->makeUser(email: 'real@example.test');

        $time = function (string $email): float {
            $start = microtime(true);
            $this->post('/login', ['email' => $email, 'password' => 'not-the-password']);

            return (microtime(true) - $start) * 1000;
        };

        // Warm the framework so the first call doesn't carry boot cost.
        $time('warmup@example.test');

        $known = $time('real@example.test');
        $unknown = $time('nobody@example.test');

        // Before the fix the unknown branch skipped bcrypt entirely: ~0ms vs
        // ~310ms at production's cost 12.
        //
        // DO NOT TREAT THIS AS THE REGRESSION GUARD. phpunit.xml sets
        // BCRYPT_ROUNDS=4, where a real check costs ~7ms, so the gap this
        // would be measuring is smaller than the request noise — verified by
        // deleting the equaliser, and this test still passed. The test that
        // actually fails in that case is
        // test_the_timing_equaliser_is_built_at_the_configured_bcrypt_cost().
        // This one earns its place only under a production-like cost, where
        // it would catch an equaliser that runs but at the wrong cost.
        $this->assertLessThan(
            150,
            abs($known - $unknown),
            sprintf('Login timing leaks account existence: known=%.0fms unknown=%.0fms', $known, $unknown)
        );
    }

    /**
     * The equaliser must carry the CONFIGURED cost, not a pasted-in one.
     *
     * The first cut of this fix hardcoded a `$2y$12$…` constant. Under
     * phpunit's BCRYPT_ROUNDS=4 that made the unknown-email branch ~319ms
     * against a real login's ~7ms — it did not close the oracle, it inverted
     * it, and an inverted oracle reads just as well.
     */
    public function test_the_timing_equaliser_is_built_at_the_configured_bcrypt_cost(): void
    {
        $property = new \ReflectionProperty(AuthController::class, 'timingEqualiser');
        $property->setValue(null, null);

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever']);

        $hash = $property->getValue();
        $this->assertNotNull($hash, 'The unknown-email branch must build an equaliser.');

        $info = password_get_info($hash);
        $this->assertSame(
            (int) config('hashing.bcrypt.rounds', 12),
            (int) $info['options']['cost'],
            'The equaliser cost must track hashing.bcrypt.rounds, or the timing gap reopens.'
        );
    }

    /** The wording must stay identical for both branches, as it already was. */
    public function test_login_gives_one_answer_for_both_failures(): void
    {
        $this->makeUser(email: 'real@example.test');

        $this->post('/login', ['email' => 'real@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
    }

    // ── Regression guards for what was already right ───────────────

    public function test_logout_invalidates_the_session_and_rotates_the_token(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ── Signing in returns to the page that was asked for (v7.72) ──

    /** The email button case: open a booking signed out, sign in, land on it. */
    public function test_signing_in_returns_to_the_page_that_was_asked_for(): void
    {
        $this->makeUser(email: 'guest@example.test');

        $this->get('/my/bookings/5?from=email', self::PAGE_LOAD)->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'guest@example.test', 'password' => 'the-real-password'])
            ->assertRedirect('/my/bookings/5?from=email');
    }

    /**
     * A page left open after the session ran out keeps asking for data in the
     * background. Remembering one of those would end the next login on a JSON
     * reply. The availability grid even asks for `text/html`, so only the
     * browser's own "this is a page load" header can tell them apart.
     */
    public function test_a_background_request_is_not_remembered(): void
    {
        $this->makeUser(role: 'staff', email: 'desk@example.test');

        $this->get('/staff/availability/grid', ['Accept' => 'text/html', 'Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'empty'])
            ->assertRedirect(route('login'));
        $this->get('/staff/availability/grid', ['Accept' => 'text/html'])->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'desk@example.test', 'password' => 'the-real-password'])
            ->assertRedirect(route('staff.frontdesk'));
    }

    /** Signing in must never end on a 403: the page's own role rule decides. */
    public function test_a_page_this_role_may_not_open_is_not_returned_to(): void
    {
        $this->makeUser(role: 'staff', email: 'desk@example.test');
        $this->makeUser(email: 'guest@example.test');

        $this->get('/admin/users', self::PAGE_LOAD)->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'desk@example.test', 'password' => 'the-real-password'])
            ->assertRedirect(route('staff.frontdesk'));

        $this->post('/logout');

        $this->get('/staff/frontdesk', self::PAGE_LOAD)->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'guest@example.test', 'password' => 'the-real-password'])
            ->assertRedirect('/my/');
    }

    /** Staff routes are `role:staff,admin`: an admin is returned to one. */
    public function test_a_role_listed_second_is_returned_to_the_page(): void
    {
        $this->makeUser(role: 'admin', email: 'owner@example.test');

        $this->get('/staff/availability', self::PAGE_LOAD)->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'the-real-password'])
            ->assertRedirect('/staff/availability');
    }

    /** It is used once, and not at all once it is old. */
    public function test_a_remembered_page_is_used_once_and_expires(): void
    {
        $this->makeUser(email: 'guest@example.test');
        $login = ['email' => 'guest@example.test', 'password' => 'the-real-password'];

        $this->get('/my/bookings/5', self::PAGE_LOAD)->assertRedirect(route('login'));
        $this->post('/login', $login)->assertRedirect('/my/bookings/5');
        $this->post('/logout');
        $this->post('/login', $login)->assertRedirect('/my/');
        $this->post('/logout');

        $this->get('/my/bookings/5', self::PAGE_LOAD)->assertRedirect(route('login'));
        $this->travel(\App\Support\LoginReturn::MINUTES + 1)->minutes();
        $this->post('/login', $login)->assertRedirect('/my/');
    }

    /** Whatever is in the session, a login only ever lands on this site. */
    public function test_a_stored_path_that_leaves_the_site_is_refused(): void
    {
        $user = $this->makeUser();

        foreach (['//evil.example/my/', '/\\evil.example', 'https://evil.example/my/', '/login', '/no-such-page'] as $path) {
            $request = \Illuminate\Http\Request::create('/login', 'POST');
            $request->setLaravelSession($this->app['session.store']);
            $request->session()->put('login_return', ['path' => $path, 'at' => now()->timestamp]);

            $this->assertNull(\App\Support\LoginReturn::pathFor($user, $request), "{$path} must not be used.");
        }
    }

    /** The unfinished verification still comes first. */
    public function test_a_verification_link_still_wins_over_a_remembered_page(): void
    {
        $user = $this->makeUser(verified: false, email: 'pending@example.test');
        $url = $this->verificationUrl($user);

        $this->get('/my/bookings/5', self::PAGE_LOAD)->assertRedirect(route('login'));
        $this->get($url)->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'pending@example.test', 'password' => 'the-real-password'])
            ->assertRedirect($url);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /** What a browser sends when someone opens a page, as opposed to a fetch(). */
    private const PAGE_LOAD = ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document'];

    private function verificationUrl(User $user): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    private function seedSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => time(),
        ]);
    }

    private function makeTrustedDevice(User $user, string $token): void
    {
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => TrustedDevice::hashToken($token),
            'device_label' => 'Test device',
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function makeUser(string $role = 'customer', bool $verified = true, ?string $email = null, int $status = 1): User
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'full_name' => "User {$n}",
            'email' => $email ?? "user{$n}@example.test",
            'password' => Hash::make('the-real-password'),
            'role' => $role,
            'status' => $status,
        ]);

        // `email_verified_at` is deliberately absent from User::$fillable.
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    private function makeTables(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('customer');
            $table->tinyInteger('status')->default(1);
            $table->string('phone')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('trusted_devices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token_hash');
            $table->string('device_label')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Written by completeLogin() and logout().
        Schema::create('staff_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('target_table')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('description')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('login_activities', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_label')->nullable();
            $table->boolean('via_new_device_otp')->default(false);
            $table->timestamps();
        });

        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('data_type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }
}
