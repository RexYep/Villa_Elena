<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The page a guest lands on after PayMongo (`payment/success.blade.php`),
 * rendered in each of its four states (v7.75).
 *
 * The view is rendered for real: a Blade file can compile and still fail or
 * say the wrong thing when it runs. Tables are built by hand: the real
 * migrations are MySQL-only and don't run on the in-memory SQLite test
 * connection.
 */
class PaymentResultPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->makeTables();

        Setting::set('booking_hold_minutes', '15');
        Setting::set('resort_phone', '0917 123 4567');
        Setting::set('resort_email', 'hello@villa.test');
    }

    public function test_a_received_payment_describes_the_booking_by_slot_and_time(): void
    {
        $booking = $this->makeBooking(['status' => 'confirmed', 'payment_status' => 'partial', 'amount_paid' => 2000, 'balance_due' => 2000]);

        $html = $this->page($booking, confirmed: true, amountPaid: 2000);

        $this->assertStringContainsString('Payment Successful!', $html);
        $this->assertStringContainsString('Amount paid', $html);
        $this->assertStringContainsString('₱2,000.00', $html);
        $this->assertStringContainsString('<dt>Slot</dt>', $html);
        $this->assertStringContainsString('8:00 AM', $html);
        $this->assertStringContainsString('5:00 PM', $html);
        $this->assertStringContainsString('₱2,000.00 remaining', $html);
        $this->assertStringNotContainsString('Property', $html, 'There is one villa; a Property row says nothing.');
        // Nothing left to wait for, so nothing polls.
        $this->assertStringNotContainsString('villa:payment-state', $html);
    }

    public function test_the_waiting_page_shows_the_deadline_and_the_balance_not_an_amount_due(): void
    {
        $booking = $this->makeBooking(['status' => 'pending', 'balance_due' => 4000]);

        $html = $this->page($booking, confirmed: false);

        $this->assertStringContainsString('Waiting for Payment', $html);
        $this->assertStringContainsString('Pay by ', $html, 'The hold deadline must be on the page the guest waits on.');
        $this->assertStringContainsString('data-stay="1"', $html, 'This page shows the result itself; the deadline box must not leave it.');
        $this->assertStringContainsString('Checking for your payment', $html);
        $this->assertStringContainsString('Booking balance', $html);
        $this->assertStringContainsString('Try the payment again', $html);
        $this->assertStringNotContainsString('Amount Due', $html);
        $this->assertStringContainsString('villa:payment-watch-ended', $html, 'The page must say when it stops checking.');
    }

    /**
     * The state that used to read "Waiting for Payment … keep this page open"
     * for a booking with nothing left to wait for.
     */
    public function test_a_cancelled_unpaid_booking_is_not_shown_as_waiting(): void
    {
        $booking = $this->makeBooking(['status' => 'cancelled', 'cancelled_by' => 'system', 'cancelled_at' => now()]);

        $html = $this->page($booking, confirmed: false);

        $this->assertStringContainsString('Booking Cancelled', $html);
        $this->assertStringContainsString('nothing is due', $html);
        $this->assertStringNotContainsString('Waiting for Payment', $html);
        $this->assertStringNotContainsString('Try the payment again', $html);
        $this->assertStringNotContainsString('Paid just before', $html);
        $this->assertStringNotContainsString('villa:payment-state', $html, 'Its checkout session is closed; no payment can arrive.');
        $this->assertStringContainsString('No payment', $html);
    }

    /** PayMongo refused to close the session: a payment may still be on its way. */
    public function test_a_cancelled_booking_with_an_open_checkout_keeps_watching(): void
    {
        $booking = $this->makeBooking([
            'status' => 'cancelled', 'cancelled_by' => 'system', 'cancelled_at' => now(), 'paymongo_session_id' => 'cs_test_open',
        ]);

        $html = $this->page($booking, confirmed: false);

        $this->assertStringContainsString('Booking Cancelled', $html);
        $this->assertStringContainsString('Paid just before that happened?', $html);
        $this->assertStringContainsString('villa:payment-state', $html);
    }

    public function test_a_payment_on_a_cancelled_booking_is_not_called_fully_paid_and_says_how_to_reach_the_resort(): void
    {
        $booking = $this->makeBooking([
            'status' => 'cancelled', 'payment_status' => 'paid', 'amount_paid' => 2000, 'cancelled_by' => 'system', 'cancelled_at' => now(),
        ]);

        $html = $this->page($booking, confirmed: true, amountPaid: 2000);

        $this->assertStringContainsString('Payment Received', $html);
        $this->assertStringContainsString('₱2,000.00 received', $html);
        $this->assertStringNotContainsString('Fully Paid', $html);
        $this->assertStringContainsString('href="tel:09171234567"', $html);
        $this->assertStringContainsString('href="mailto:hello@villa.test"', $html);
    }

    public function test_a_status_is_never_printed_raw(): void
    {
        $booking = $this->makeBooking(['status' => 'checked_in']);

        $this->assertSame(['text' => 'Checked in', 'class' => 'status-confirmed'], $booking->statusDisplay());
        $this->assertSame('status-cancelled', $this->makeBooking(['status' => 'cancelled'])->statusDisplay()['class']);
        $this->assertSame('status-partial', $this->makeBooking(['status' => 'pending'])->statusDisplay()['class']);
    }

    // ── helpers ────────────────────────────────────────────────────

    private function page(Booking $booking, bool $confirmed, float $amountPaid = 0): string
    {
        return view('payment.success', [
            'booking' => $booking,
            'amountPaid' => $amountPaid,
            'paymentType' => 'partial',
            'paymentConfirmed' => $confirmed,
        ])->render();
    }

    private function makeBooking(array $over = []): Booking
    {
        static $n = 0;
        $n++;

        $guest = User::create([
            'full_name' => "Maria Guest{$n}",
            'email' => "result{$n}@example.test",
            'password' => bcrypt('secret-password'),
            'role' => 'customer',
            'status' => 1,
        ]);

        $date = now()->addDays(30 + $n)->format('Y-m-d');

        $booking = new Booking;
        $booking->forceFill(array_merge([
            'booking_ref' => "VE-PAGE{$n}",
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
    }
}
