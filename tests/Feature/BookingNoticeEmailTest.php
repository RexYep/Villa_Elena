<?php

namespace Tests\Feature;

use App\Helpers\BookingMailHelper;
use App\Helpers\NotificationHelper;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The emails beside the three booking-change notices (v7.74): an unpaid hold
 * that ran out, a cancellation, and a move to another date.
 *
 * Each is sent through the real `array` mailer and read back, so the views
 * are rendered. Tables are built by hand: the real migrations are MySQL-only
 * and don't run on the in-memory SQLite test connection.
 */
class BookingNoticeEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // The email is sent in defer(), after the response. Outside a
        // request nothing ends, so run it at once.
        $this->withoutDefer();
        $this->makeTables();

        Setting::set('booking_hold_minutes', '15');
        Setting::set('resort_name', 'Villa Elena Private Rental Resort');
    }

    public function test_a_cancellation_is_emailed_with_the_sentence_the_bell_shows(): void
    {
        $guest = $this->makeGuest();
        $booking = $this->makeBooking($guest, ['status' => 'cancelled', 'amount_paid' => 2000]);

        NotificationHelper::bookingCancelledForGuest($booking, 2000);

        $notice = Notification::where('user_id', $guest->id)->sole();
        $mail = $this->soleEmail();

        $this->assertSame("Booking Cancelled — {$booking->booking_ref}", $notice->title);
        $this->assertSame("Booking Cancelled — {$booking->booking_ref}", $mail->getSubject());
        $this->assertSame($guest->email, $mail->getTo()[0]->getAddress());
        $this->assertSame('Villa Elena Private Rental Resort', $mail->getFrom()[0]->getName());

        foreach ([$mail->getHtmlBody(), $mail->getTextBody()] as $part) {
            $this->assertStringContainsString($notice->message, html_entity_decode($part));
            $this->assertStringContainsString('2,000.00', $part);
            $this->assertStringContainsString('Paid, not refunded', $part);
            $this->assertStringContainsString("/my/bookings/{$booking->id}", $part);
            $this->assertStringContainsString('Day', $part, 'A booking is described by its slot.');
            $this->assertStringNotContainsString('night', strtolower($part), 'Never by a number of nights.');
        }
    }

    public function test_an_unpaid_cancellation_says_nothing_about_money(): void
    {
        $guest = $this->makeGuest();
        $booking = $this->makeBooking($guest, ['status' => 'cancelled']);

        NotificationHelper::bookingCancelledForGuest($booking, 0);

        $this->assertStringNotContainsString('refund', strtolower($this->soleEmail()->getTextBody()));
    }

    public function test_a_hold_that_ran_out_states_the_real_hold_and_no_deposit_percentage(): void
    {
        $guest = $this->makeGuest();
        $booking = $this->makeBooking($guest, ['status' => 'cancelled', 'cancelled_by' => 'system', 'cancelled_at' => now()]);

        NotificationHelper::holdExpiredForGuest($booking);

        $notice = Notification::where('user_id', $guest->id)->sole();
        $text = $this->soleEmail()->getTextBody();

        $this->assertStringContainsString('within 15 minutes', $notice->message);
        $this->assertStringContainsString('You can book again if the date is still open.', $notice->message);
        $this->assertStringNotContainsString('50%', $notice->message, 'The deposit is a setting, and some guests must pay in full.');

        $this->assertStringContainsString($notice->message, $text);
        $this->assertStringContainsString('Already paid?', $text);
        $this->assertStringNotContainsString('refund', strtolower($text), 'A late payment is decided by a person; no refund is promised.');
    }

    /**
     * After the third unpaid hold in the window the guest cannot book for a
     * day (Booking::bookingCooldownEndsAt()), so "you can book again" would
     * be untrue.
     */
    public function test_a_blocked_guest_is_told_when_they_can_book_again_not_that_they_can(): void
    {
        $guest = $this->makeGuest();

        foreach (range(1, 3) as $i) {
            $booking = $this->makeBooking($guest, [
                'status' => 'cancelled', 'cancelled_by' => 'system', 'cancelled_at' => now(),
                'check_in_date' => now()->addDays(20 + $i)->format('Y-m-d'),
                'check_out_date' => now()->addDays(20 + $i)->format('Y-m-d'),
            ]);
        }

        NotificationHelper::holdExpiredForGuest($booking);

        $message = Notification::where('user_id', $guest->id)->sole()->message;

        $this->assertStringContainsString('you can book again after '.now()->addHours(24)->format('M j, Y'), $message);
        $this->assertStringNotContainsString('if the date is still open', $message);
    }

    public function test_a_reschedule_names_both_dates_and_a_changed_total(): void
    {
        $guest = $this->makeGuest();
        $booking = $this->makeBooking($guest, ['status' => 'confirmed', 'amount_paid' => 2000, 'balance_due' => 2000]);
        $was = $booking->stay_phrase;

        $booking->forceFill([
            'check_in_date' => now()->addDays(40)->format('Y-m-d'),
            'check_out_date' => now()->addDays(40)->format('Y-m-d'),
            'total_amount' => 6000,
            'balance_due' => 4000,
            'reschedule_count' => 1,
        ])->save();

        $returned = NotificationHelper::bookingRescheduledForGuest($booking->fresh(), $was, 4000);

        $notice = Notification::where('user_id', $guest->id)->sole();
        $mail = $this->soleEmail();

        $this->assertSame($notice->message, $returned, 'The flash message is the same sentence.');
        $this->assertSame("Booking Rescheduled — {$booking->booking_ref}", $mail->getSubject());
        $this->assertStringContainsString("moved from {$was} to {$booking->fresh()->stay_phrase}", $returned);
        $this->assertStringContainsString('The total is now ₱6,000.00 (it was ₱4,000.00).', $returned);
        $this->assertStringContainsString('₱4,000.00 is still due', $returned);
        $this->assertStringContainsString('You have 1 reschedule left', $returned);
        $this->assertStringNotContainsString('additional', $returned);

        $text = $mail->getTextBody();
        $this->assertStringContainsString("Previously: {$was}", $text);
        $this->assertStringContainsString('Balance due: ₱4,000.00', $text);
    }

    /** A balance that was already owed is not "now due" because the date moved. */
    public function test_a_reschedule_at_the_same_price_does_not_announce_a_new_total(): void
    {
        $guest = $this->makeGuest();
        $booking = $this->makeBooking($guest, ['status' => 'confirmed', 'amount_paid' => 2000, 'balance_due' => 2000]);

        $message = NotificationHelper::bookingRescheduledForGuest($booking, 'Mon, Jan 4, 2027 (Day, 8:00 AM)', 4000);

        $this->assertStringNotContainsString('The total is now', $message);
        $this->assertStringContainsString('₱2,000.00 is still due', $message);
    }

    public function test_a_move_by_the_resort_says_who_moved_it_and_nothing_about_reschedules_left(): void
    {
        $guest = $this->makeGuest();
        $booking = $this->makeBooking($guest, ['status' => 'confirmed', 'amount_paid' => 2000, 'balance_due' => 2000]);

        $message = NotificationHelper::bookingRescheduledForGuest($booking, 'Mon, Jan 4, 2027 (Day, 8:00 AM)', byResort: true);

        $this->assertSame("Booking Moved by the Resort — {$booking->booking_ref}", $this->soleEmail()->getSubject());
        $this->assertStringStartsWith("The resort moved your booking {$booking->booking_ref}", $message);
        $this->assertStringContainsString('If you did not ask for this change, please contact us.', $message);
        $this->assertStringNotContainsString('reschedule', strtolower($message), 'A move by the resort does not use one up.');
    }

    /**
     * An unpaid booking can be made and cancelled over and over, and every
     * email draws on the quota shared with sign-in codes.
     */
    public function test_past_the_daily_limit_the_bell_still_rings_and_the_email_stops(): void
    {
        $guest = $this->makeGuest();
        $booking = $this->makeBooking($guest, ['status' => 'cancelled']);
        $attempts = BookingMailHelper::DAILY_NOTICE_LIMIT + 3;

        foreach (range(1, $attempts) as $_) {
            NotificationHelper::bookingCancelledForGuest($booking, 0);
        }

        $this->assertCount(BookingMailHelper::DAILY_NOTICE_LIMIT, $this->emails());
        $this->assertSame($attempts, Notification::where('user_id', $guest->id)->count());

        // The limit is per guest.
        $other = $this->makeGuest();
        NotificationHelper::bookingCancelledForGuest($this->makeBooking($other, ['status' => 'cancelled']), 0);

        $this->assertCount(BookingMailHelper::DAILY_NOTICE_LIMIT + 1, $this->emails());
    }

    public function test_a_guest_who_switched_booking_emails_off_gets_the_bell_only(): void
    {
        $guest = $this->makeGuest();
        $guest->forceFill(['email_notifications_enabled' => false])->save();

        NotificationHelper::bookingCancelledForGuest($this->makeBooking($guest, ['status' => 'cancelled']), 0);

        $this->assertCount(0, $this->emails());
        $this->assertSame(1, Notification::where('user_id', $guest->id)->count());
    }

    // ── helpers ────────────────────────────────────────────────────

    /** @return array<int, \Symfony\Component\Mime\Email> */
    private function emails(): array
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())->all();
    }

    private function soleEmail(): \Symfony\Component\Mime\Email
    {
        $emails = $this->emails();
        $this->assertCount(1, $emails);

        return $emails[0];
    }

    private function makeGuest(): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'full_name' => "Maria Guest{$n}",
            'email' => "guest{$n}@example.test",
            'password' => bcrypt('secret-password'),
            'role' => 'customer',
            'status' => 1,
        ])->fresh();
    }

    private function makeBooking(User $guest, array $over = []): Booking
    {
        static $n = 0;
        $n++;

        $date = now()->addDays(30)->format('Y-m-d');

        $booking = new Booking;
        $booking->forceFill(array_merge([
            'booking_ref' => "VE-TEST{$n}",
            'user_id' => $guest->id,
            'property_id' => 1,
            'check_in_date' => $date,
            'check_in_time' => '08:00:00',
            'check_out_date' => $date,
            'check_out_time' => '17:00:00',
            'slot' => 'day',
            'num_nights' => 1,
            'num_guests' => 4,
            'base_amount' => 4000,
            'total_amount' => 4000,
            'amount_paid' => 0,
            'balance_due' => 0,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'source' => 'online',
        ], $over))->save();

        return $booking->fresh();
    }

    private function makeTables(): void
    {
        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('data_type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->tinyInteger('status')->default(1);
            $table->string('phone')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->string('booking_ref')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->date('check_in_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('slot')->nullable();
            $table->integer('num_nights')->default(1);
            $table->integer('num_guests')->default(1);
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('extras_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('discount_id')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('source')->default('online');
            $table->string('slot_hold')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->string('paymongo_session_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('notifications', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('type')->default('in_app');
            $table->string('link')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->string('status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
}
