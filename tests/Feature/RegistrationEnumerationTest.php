<?php

namespace Tests\Feature;

use App\Mail\RegistrationAttemptMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The last of the three account-enumeration leaks.
 *
 * Login already gives one answer for "no such account" and "wrong password"
 * (v7.22), and sendResetLink() answers identically whether or not the address
 * exists (v7.25). Registration was still saying "This email is already
 * registered." straight out, which made the other two close to pointless.
 *
 * Tables are built by hand: the real migrations are MySQL-only and don't run
 * on the in-memory SQLite test connection.
 */
class RegistrationEnumerationTest extends TestCase
{
    private const TAKEN = 'taken@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->makeTables();

        User::create([
            'full_name' => 'Existing Guest',
            'email' => self::TAKEN,
            'password' => Hash::make('their-real-password'),
            'role' => 'customer',
            'status' => 1,
        ]);
    }

    /**
     * The assertion that is the whole point: the two responses must be
     * byte-identical apart from nothing at all.
     */
    public function test_a_taken_and_a_free_address_get_the_same_answer(): void
    {
        Mail::fake();

        $taken = $this->post('/register', $this->form(self::TAKEN));
        $free = $this->post('/register', $this->form('brand-new@example.test'));

        $this->assertSame($taken->getStatusCode(), $free->getStatusCode());
        $this->assertSame($taken->headers->get('Location'), $free->headers->get('Location'));
        $this->assertSame(session()->get('success'), session()->get('success'));

        foreach ([$taken, $free] as $response) {
            $response->assertRedirect(route('register.pending'));
            $response->assertSessionHasNoErrors();
        }
    }

    /** No validation error may name the address as taken. */
    public function test_a_taken_address_produces_no_validation_error(): void
    {
        Mail::fake();

        $this->post('/register', $this->form(self::TAKEN))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('register.pending'));
    }

    /** ...and the account is still not duplicated. */
    public function test_a_taken_address_creates_no_second_account(): void
    {
        Mail::fake();

        $this->post('/register', $this->form(self::TAKEN));

        $this->assertSame(1, User::where('email', self::TAKEN)->count());
    }

    /** The existing account's password must be untouched by the attempt. */
    public function test_a_taken_address_does_not_overwrite_the_existing_account(): void
    {
        Mail::fake();

        $before = User::where('email', self::TAKEN)->first();

        $this->post('/register', $this->form(self::TAKEN, name: 'Impostor', phone: '09990000000'));

        $after = User::where('email', self::TAKEN)->first();

        $this->assertSame($before->password, $after->password);
        $this->assertSame('Existing Guest', $after->full_name);
        $this->assertTrue(Hash::check('their-real-password', $after->password));
    }

    /**
     * Registration must not sign anybody in. It used to, and that alone gave
     * the answer away: the owner of an existing account cannot be logged in
     * just because somebody typed their address, so the presence of a session
     * would have distinguished the two branches however identical the words.
     */
    public function test_registration_no_longer_signs_anyone_in(): void
    {
        Mail::fake();

        $this->post('/register', $this->form('brand-new@example.test'));
        $this->assertGuest();

        $this->post('/register', $this->form(self::TAKEN));
        $this->assertGuest();
    }

    /** A genuinely new address still creates the account. */
    public function test_a_free_address_still_registers(): void
    {
        Mail::fake();

        $this->post('/register', $this->form('brand-new@example.test'))
            ->assertRedirect(route('register.pending'));

        $user = User::where('email', 'brand-new@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    /** The information did not vanish — it went to the person entitled to it. */
    public function test_the_real_owner_is_told_about_the_attempt(): void
    {
        Mail::fake();

        $this->post('/register', $this->form(self::TAKEN));

        Mail::assertSent(RegistrationAttemptMail::class, fn ($mail) => $mail->hasTo(self::TAKEN));
    }

    public function test_a_free_address_triggers_no_attempt_notice(): void
    {
        Mail::fake();

        $this->post('/register', $this->form('brand-new@example.test'));

        Mail::assertNotSent(RegistrationAttemptMail::class);
    }

    /**
     * The notice must not echo anything the submitter typed. Otherwise the
     * form becomes a way to post arbitrary text into a stranger's inbox.
     */
    public function test_the_attempt_notice_repeats_nothing_the_submitter_typed(): void
    {
        Mail::fake();

        $this->post('/register', $this->form(
            self::TAKEN,
            name: 'CLICK http://evil.example/now',
            phone: '0917-ATTACKER',
        ));

        Mail::assertSent(RegistrationAttemptMail::class, function ($mail) {
            $rendered = $mail->render();

            $this->assertStringNotContainsString('evil.example', $rendered);
            $this->assertStringNotContainsString('ATTACKER', $rendered);
            $this->assertStringContainsString('Existing Guest', $rendered);

            return true;
        });
    }

    /**
     * A dead mail transport must not become the tell. If the notice throws and
     * the response changes shape, the leak is back on any day Brevo is down.
     */
    public function test_a_failing_mailer_does_not_change_the_answer(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));

        $this->post('/register', $this->form(self::TAKEN))
            ->assertRedirect(route('register.pending'))
            ->assertSessionHasNoErrors();
    }

    /**
     * Timing is the other channel. bcrypt at production cost is hundreds of
     * milliseconds; if only the new-account branch paid it, the clock would
     * answer the question the words no longer do. The hash is computed before
     * the branch so both sides pay it.
     */
    public function test_both_branches_hash_the_password(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/AuthController.php'));

        $registerBody = substr($source, strpos($source, 'public function register('));
        $registerBody = substr($registerBody, 0, strpos($registerBody, 'private function announceDuplicateRegistration'));

        $hashAt = strpos($registerBody, 'Hash::make($request->password)');
        $branchAt = strpos($registerBody, 'if ($existing)');

        $this->assertNotFalse($hashAt, 'The password must still be hashed in register().');
        $this->assertNotFalse($branchAt);
        $this->assertLessThan(
            $branchAt,
            $hashAt,
            'Hash::make() must run BEFORE the exists/not-exists branch, or response time tells them apart.'
        );
    }

    // ── The landing page ───────────────────────────────────────────

    /**
     * Rendered for real, not compiled. Blade compiles a broken view into valid
     * PHP often enough that only a render catches it (see project.md v7.5).
     */
    public function test_the_pending_page_renders_for_a_guest(): void
    {
        $this->get(route('register.pending'))
            ->assertOk()
            ->assertSee('Check your email');
    }

    /** It must not name the address or otherwise hint at which branch ran. */
    public function test_the_pending_page_names_no_address(): void
    {
        Mail::fake();

        $response = $this->followingRedirects()->post('/register', $this->form(self::TAKEN));

        $response->assertOk();
        $response->assertDontSee(self::TAKEN);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function form(string $email, string $name = 'New Guest', string $phone = '09171234567'): array
    {
        return [
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ];
    }

    private function makeTables(): void
    {
        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('setting_key');
            $t->text('setting_value')->nullable();
            $t->timestamps();
        });

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('full_name');
            $t->string('email', 100)->unique();
            $t->string('password');
            $t->string('role')->default('customer');
            $t->integer('status')->default(1);
            $t->string('phone')->nullable();
            $t->string('profile_image')->nullable();
            $t->string('address')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_login')->nullable();
            $t->boolean('two_factor_enabled')->default(false);
            $t->boolean('email_notifications_enabled')->default(true);
            $t->string('remember_token')->nullable();
            $t->timestamps();
        });

        // NotificationHelper::newGuestRegistered() writes here.
        Schema::create('notifications', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('type')->nullable();
            $t->string('title')->nullable();
            $t->text('message')->nullable();
            $t->string('link')->nullable();
            $t->integer('is_read')->default(0);
            $t->string('status')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }
}
