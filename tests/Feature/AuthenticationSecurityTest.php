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

    public function test_the_verification_link_verifies_but_does_not_sign_anyone_in(): void
    {
        $user = $this->makeUser(verified: false);

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasVerifiedEmail(), 'The address must still get verified.');
        // A 60-minute emailed URL must not be a complete login.
        $this->assertGuest();
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
            ->assertRedirect(route('login'));
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

    // ── Helpers ────────────────────────────────────────────────────

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
