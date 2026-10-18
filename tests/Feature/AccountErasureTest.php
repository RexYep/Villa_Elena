<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\StaffLog;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use App\Notifications\AccountDeletionCodeNotification;
use App\Services\AccountErasure;
use App\Services\PayMongoService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Deleting a guest account (v7.79): the personal details go, the booking and
 * payment records stay, and deletion is refused while anything is still in
 * play. See App\Services\AccountErasure.
 *
 * Tables are built by hand: the real migrations are MySQL-only.
 */
class AccountErasureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutDefer();
        $this->makeTables();

        $this->forgetColumnChecks();
    }

    protected function tearDown(): void
    {
        $this->forgetColumnChecks();

        parent::tearDown();
    }

    /**
     * The models memoise "does this column exist" per process. This class's
     * tables have columns (slot, refund_kind, anonymized_at) that later test
     * classes' hand-built tables do not, so leaving the answers behind broke
     * 30 tests that pass on their own.
     */
    private function forgetColumnChecks(): void
    {
        \Closure::bind(fn () => User::$anonymizedColumnExists = null, null, User::class)();
        \Closure::bind(fn () => Booking::$optionalColumnExists = [], null, Booking::class)();
        \Closure::bind(fn () => \App\Models\Payment::$refundKindColumnExists = null, null, \App\Models\Payment::class)();
    }

    // ── The guest deletes their own account ───────────────────────

    public function test_deleting_clears_the_person_and_keeps_the_records(): void
    {
        Notification::fake();
        $user = $this->makeGuest(email: 'juan@example.test');
        $booking = $this->makeBooking($user, 'checked_out', paid: 4000, specialRequests: 'My wife is allergic to shellfish');
        $paymentId = DB::table('payments')->insertGetId([
            'booking_id' => $booking->id, 'amount' => 4000, 'payment_method' => 'qrph',
            'payment_type' => 'full_payment', 'status' => 'success', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('notifications')->insert(['user_id' => $user->id, 'title' => 'Booking Confirmed', 'message' => 'x']);
        DB::table('login_activities')->insert(['user_id' => $user->id, 'ip_address' => '1.2.3.4']);
        DB::table('trusted_devices')->insert(['user_id' => $user->id, 'token_hash' => 'h', 'expires_at' => now()->addDay()]);
        DB::table('password_reset_tokens')->insert(['email' => 'juan@example.test', 'token' => 't']);

        $this->actingAs($user)
            ->delete('/my/profile', ['password' => 'the-real-password', 'confirm_text' => 'DELETE'])
            ->assertRedirect(route('home'));

        $this->assertGuest();

        $row = User::find($user->id);
        $this->assertNotNull($row, 'The row is kept: bookings.user_id cascades on delete.');
        $this->assertNotNull($row->anonymized_at);
        $this->assertNull($row->email);
        $this->assertNull($row->phone);
        $this->assertNull($row->address);
        $this->assertSame(User::ANONYMIZED_NAME, $row->full_name);
        $this->assertSame(0, $row->status);
        $this->assertFalse(Hash::check('the-real-password', $row->password));

        $this->assertNotNull(Booking::find($booking->id), 'The booking is a financial record.');
        $this->assertNull(Booking::find($booking->id)->special_requests);
        $this->assertTrue(DB::table('payments')->where('id', $paymentId)->exists());

        $this->assertSame(0, DB::table('notifications')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('login_activities')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('trusted_devices')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());

        $entry = StaffLog::where('action', 'account_erased')->first();
        $this->assertNotNull($entry);
        $this->assertSame($user->id, $entry->user_id, 'Recorded while the guest is still the actor.');
        $this->assertStringNotContainsString('juan@example.test', $entry->description);

        Notification::assertSentOnDemand(AccountDeletedNotification::class,
            fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'juan@example.test');
    }

    public function test_the_password_and_the_typed_word_are_both_required(): void
    {
        $user = $this->makeGuest();

        $this->actingAs($user)
            ->delete('/my/profile', ['password' => 'wrong', 'confirm_text' => 'DELETE'])
            ->assertSessionHasErrors('password', null, 'deleteAccount');

        $this->actingAs($user)
            ->delete('/my/profile', ['password' => 'the-real-password', 'confirm_text' => 'yes'])
            ->assertSessionHasErrors('confirm_text', null, 'deleteAccount');

        $this->assertNull(User::find($user->id)->anonymized_at);
    }

    public function test_a_guest_with_two_factor_needs_the_emailed_code(): void
    {
        Notification::fake();
        $user = $this->makeGuest(twoFactor: true);

        $this->actingAs($user)
            ->delete('/my/profile', ['password' => 'the-real-password', 'confirm_text' => 'DELETE'])
            ->assertSessionHasErrors('code', null, 'deleteAccount');

        $this->actingAs($user)->post('/my/profile/delete-code')->assertSessionHas('delete_code_sent');

        $code = null;
        Notification::assertSentTo($user, AccountDeletionCodeNotification::class, function ($n) use (&$code) {
            $code = (fn () => $this->code)->call($n);

            return true;
        });

        $wrong = $code === '111111' ? '222222' : '111111';
        $this->actingAs($user)
            ->delete('/my/profile', ['password' => 'the-real-password', 'confirm_text' => 'DELETE', 'code' => $wrong])
            ->assertSessionHasErrors('code', null, 'deleteAccount');
        $this->assertNull(User::find($user->id)->anonymized_at);

        $this->actingAs($user)
            ->delete('/my/profile', ['password' => 'the-real-password', 'confirm_text' => 'DELETE', 'code' => $code])
            ->assertRedirect(route('home'));
        $this->assertNotNull(User::find($user->id)->anonymized_at);
    }

    // ── When it is refused ─────────────────────────────────────────

    public function test_an_upcoming_paid_booking_blocks_deletion(): void
    {
        $user = $this->makeGuest();
        $booking = $this->makeBooking($user, 'confirmed', paid: 2000, daysAhead: 10);

        $this->actingAs($user)
            ->delete('/my/profile', ['password' => 'the-real-password', 'confirm_text' => 'DELETE'])
            ->assertSessionHasErrors('account', null, 'deleteAccount');

        $this->assertNull(User::find($user->id)->anonymized_at);
        $this->assertSame('confirmed', Booking::find($booking->id)->status);
    }

    public function test_a_checked_in_stay_blocks_deletion(): void
    {
        $user = $this->makeGuest();
        $this->makeBooking($user, 'checked_in', paid: 4000, daysAhead: 0);

        $this->assertNotEmpty(AccountErasure::problems($user));
    }

    public function test_a_refund_not_yet_paid_out_blocks_deletion(): void
    {
        $user = $this->makeGuest();
        $booking = $this->makeBooking($user, 'cancelled', paid: 0, daysAhead: 5);
        DB::table('payments')->insert([
            'booking_id' => $booking->id, 'amount' => 2000, 'payment_method' => 'qrph',
            'payment_type' => 'refund', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $problems = AccountErasure::problems($user);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('refund', $problems[0]['message']);
    }

    public function test_an_unpaid_balance_after_the_stay_blocks_deletion(): void
    {
        $user = $this->makeGuest();
        $this->makeBooking($user, 'checked_out', paid: 2000, daysAhead: -3, balance: 2000);

        $this->assertNotEmpty(AccountErasure::problems($user));
    }

    public function test_a_payment_in_progress_on_a_hold_blocks_deletion(): void
    {
        $user = $this->makeGuest();
        $hold = $this->makeBooking($user, 'pending', paid: 0, daysAhead: 4);
        $hold->forceFill(['paymongo_session_id' => 'cs_live_one'])->saveQuietly();

        // PayMongo refuses to expire a session that is being paid.
        $this->mock(PayMongoService::class, function ($m) {
            $m->shouldReceive('expireCheckoutSession')->andReturn('refused');
            $m->shouldReceive('getCheckoutSession')->andReturn(['attributes' => ['status' => 'active']]);
        });

        $problems = AccountErasure::erase($user, AccountErasure::BY_GUEST);

        $this->assertNotEmpty($problems);
        $this->assertNull(User::find($user->id)->anonymized_at);
        $this->assertSame('pending', Booking::find($hold->id)->status);
    }

    // ── What deleting does to bookings that are not finished ───────

    public function test_an_unpaid_hold_is_cancelled_rather_than_blocking(): void
    {
        $user = $this->makeGuest();
        $hold = $this->makeBooking($user, 'pending', paid: 0, daysAhead: 4);

        $this->assertSame([], AccountErasure::erase($user, AccountErasure::BY_GUEST));

        $hold = Booking::find($hold->id);
        $this->assertSame('cancelled', $hold->status);
        $this->assertSame('guest', $hold->cancelled_by);
        $this->assertNull($hold->slot_hold, 'A cancelled booking must free its slot.');
    }

    // ── Signing up again ───────────────────────────────────────────

    public function test_the_same_email_can_sign_up_again_as_a_new_account(): void
    {
        Notification::fake();
        $user = $this->makeGuest(email: 'again@example.test');
        $this->makeBooking($user, 'checked_out', paid: 4000, daysAhead: -20);

        AccountErasure::erase($user, AccountErasure::BY_GUEST);

        $this->post('/register', [
            'full_name' => 'Juan Again',
            'email' => 'again@example.test',
            'phone' => '09171234567',
            'password' => 'a-good-password-1!',
            'password_confirmation' => 'a-good-password-1!',
            'form_token' => encrypt(time() - 10),
        ])->assertRedirect(route('register.pending'));

        $fresh = User::where('email', 'again@example.test')->first();
        $this->assertNotNull($fresh, 'The freed address must create a new account, not a "someone tried to sign up" notice.');
        $this->assertNotSame($user->id, $fresh->id);
        $this->assertSame(0, $fresh->completedStayCount(), 'Past stays stay with the deleted account.');
        $this->assertSame(1, Booking::where('user_id', $user->id)->count());
    }

    // ── Admin ──────────────────────────────────────────────────────

    public function test_admin_delete_of_a_guest_erases_instead_of_deleting(): void
    {
        Notification::fake();
        $admin = $this->makeGuest(role: 'admin');
        $user = $this->makeGuest();
        $booking = $this->makeBooking($user, 'checked_out', paid: 4000, daysAhead: -5);

        $this->actingAs($admin)->delete("/admin/users/{$user->id}")
            ->assertRedirect(route('admin.users.index'));

        $this->assertNotNull(User::find($user->id)?->anonymized_at);
        $this->assertNotNull(Booking::find($booking->id), 'Admin delete used to cascade the bookings away.');
        $this->assertSame($admin->id, StaffLog::where('action', 'account_erased')->value('user_id'));
    }

    public function test_admin_cannot_reactivate_or_edit_a_deleted_account(): void
    {
        $admin = $this->makeGuest(role: 'admin');
        $user = $this->makeGuest();
        AccountErasure::erase($user, AccountErasure::BY_GUEST);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/toggle-status")
            ->assertSessionHas('error');
        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'full_name' => 'Juan Restored', 'email' => 'back@example.test', 'phone' => '0917', 'role' => 'customer',
        ])->assertSessionHas('error');

        $row = User::find($user->id);
        $this->assertSame(0, $row->status);
        $this->assertSame(User::ANONYMIZED_NAME, $row->full_name);
    }

    public function test_a_deleted_account_cannot_be_chosen_for_a_new_booking(): void
    {
        $user = $this->makeGuest();
        $staff = $this->makeGuest(role: 'staff');
        AccountErasure::erase($user, AccountErasure::BY_GUEST);

        $rule = ['user_id' => ['required', User::bookableGuestRule()]];

        $this->assertTrue(Validator::make(['user_id' => $user->id], $rule)->fails());
        $this->assertTrue(Validator::make(['user_id' => $staff->id], $rule)->fails());
        $this->assertFalse(Validator::make(['user_id' => $this->makeGuest()->id], $rule)->fails());
        $this->assertFalse(User::notAnonymized()->whereKey($user->id)->exists());
    }

    // ── Fixtures ───────────────────────────────────────────────────

    private function makeGuest(string $role = 'customer', ?string $email = null, bool $twoFactor = false): User
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'full_name' => "Guest {$n}",
            'email' => $email ?? "erase{$n}@example.test",
            'password' => Hash::make('the-real-password'),
            'phone' => '09170000000',
            'address' => 'Somewhere, Batangas',
            'role' => $role,
            'status' => 1,
            'two_factor_enabled' => $twoFactor,
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function makeBooking(User $user, string $status, float $paid, int $daysAhead = -10,
        float $balance = 0, ?string $specialRequests = null): Booking
    {
        $date = now()->addDays($daysAhead)->toDateString();

        return Booking::forceCreate([
            'user_id' => $user->id,
            'property_id' => 1,
            'check_in_date' => $date,
            'check_out_date' => $date,
            'check_in_time' => '08:00:00',
            'check_out_time' => '17:00:00',
            'status' => $status,
            'payment_status' => $paid > 0 ? 'paid' : 'unpaid',
            'source' => 'online',
            'num_guests' => 4,
            'base_amount' => 4000,
            'total_amount' => 4000,
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'special_requests' => $specialRequests,
        ]);
    }

    private function makeTables(): void
    {
        Schema::create('users', function ($t) {
            $t->id();
            $t->string('full_name');
            $t->string('email', 100)->nullable()->unique();
            $t->string('password');
            $t->string('role')->default('customer');
            $t->tinyInteger('status')->default(1);
            $t->timestamp('anonymized_at')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->string('profile_image')->nullable();
            $t->boolean('two_factor_enabled')->default(false);
            $t->boolean('email_notifications_enabled')->default(true);
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_login')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('sessions', function ($t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function ($t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('trusted_devices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('token_hash');
            $t->string('device_label')->nullable();
            $t->string('ip_address')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('login_activities', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('device_label')->nullable();
            $t->boolean('via_new_device_otp')->default(false);
            $t->timestamps();
        });

        Schema::create('staff_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action')->nullable();
            $t->string('target_table')->nullable();
            $t->unsignedBigInteger('target_id')->nullable();
            $t->text('description')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('setting_key')->unique();
            $t->text('setting_value')->nullable();
            $t->string('data_type')->nullable();
            $t->string('description')->nullable();
            $t->timestamps();
        });

        Schema::create('notifications', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('type')->nullable();
            $t->string('title')->nullable();
            $t->text('message')->nullable();
            $t->string('link')->nullable();
            $t->tinyInteger('is_read')->default(0);
            $t->string('status')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('bookings', function ($t) {
            $t->id();
            $t->string('booking_ref')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('property_id')->nullable();
            $t->date('check_in_date')->nullable();
            $t->date('check_out_date')->nullable();
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->string('status')->nullable();
            $t->string('payment_status')->nullable();
            $t->string('source')->nullable();
            $t->string('slot')->nullable();
            $t->string('slot_hold')->nullable()->unique();
            $t->integer('num_guests')->nullable();
            $t->text('special_requests')->nullable();
            $t->decimal('base_amount', 10, 2)->default(0);
            $t->decimal('discount_amount', 10, 2)->default(0);
            $t->decimal('total_amount', 10, 2)->default(0);
            $t->decimal('amount_paid', 10, 2)->default(0);
            $t->decimal('balance_due', 10, 2)->default(0);
            $t->string('paymongo_session_id')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->string('cancelled_by')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('payments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->decimal('amount', 10, 2);
            $t->string('payment_method');
            $t->string('payment_type');
            $t->string('refund_kind')->nullable();
            $t->string('status')->default('pending');
            $t->date('payment_date')->nullable();
            $t->string('reference_number')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('refund_transfers', function ($t) {
            $t->id();
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('amount', 10, 2)->default(0);
            $t->timestamps();
        });
    }
}
