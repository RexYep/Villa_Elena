<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RefundTransfer;
use App\Models\User;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 6 — PayMongo payment security.
 *
 * Tables are built by hand: the real migrations are MySQL-only and don't run
 * on the in-memory SQLite test connection.
 */
class PaymentSecurityTest extends TestCase
{
    private const TEST_SECRET = 'whsk_test_secret';

    private const LIVE_SECRET = 'whsk_live_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->makeTables();

        config([
            'services.paymongo.webhook_secret' => '',
            'services.paymongo.webhook_secret_test' => self::TEST_SECRET,
            'services.paymongo.webhook_secret_live' => self::LIVE_SECRET,
        ]);
    }

    // ── Signature verification ─────────────────────────────────────

    public function test_a_valid_signature_is_accepted(): void
    {
        $body = $this->eventBody();

        $this->assertTrue(
            app(PayMongoService::class)->verifyWebhook($body, $this->sign($body)),
            'A freshly signed payload must verify.'
        );
    }

    /**
     * The `t` field is signed, so it cannot be forged — but it was never
     * compared to the clock, so a captured body+signature stayed valid
     * indefinitely. Measured before the fix: a five-year-old timestamp was
     * accepted.
     */
    public function test_a_stale_signature_is_rejected(): void
    {
        $body = $this->eventBody();
        $paymongo = app(PayMongoService::class);

        $tooOld = time() - PayMongoService::SIGNATURE_TOLERANCE_SECONDS - 60;
        $this->assertFalse($paymongo->verifyWebhook($body, $this->sign($body, ts: $tooOld)));

        $ancient = time() - (5 * 365 * 86400);
        $this->assertFalse($paymongo->verifyWebhook($body, $this->sign($body, ts: $ancient)));
    }

    public function test_a_signature_from_the_future_is_rejected(): void
    {
        $body = $this->eventBody();
        $future = time() + PayMongoService::SIGNATURE_TOLERANCE_SECONDS + 60;

        $this->assertFalse(app(PayMongoService::class)->verifyWebhook($body, $this->sign($body, ts: $future)));
    }

    /**
     * The window has to stay generous. A rejected event is never retried
     * (the endpoint always answers 200), so a tight window would drop real
     * payments over ordinary clock skew — while a replayed event is already
     * harmless, because recording is idempotent.
     */
    public function test_ordinary_clock_skew_is_still_accepted(): void
    {
        $body = $this->eventBody();
        $paymongo = app(PayMongoService::class);

        foreach ([-120, -30, 0, 30, 120] as $skew) {
            $this->assertTrue(
                $paymongo->verifyWebhook($body, $this->sign($body, ts: time() + $skew)),
                "A {$skew}s skew must still verify — real deliveries are not clock-synced."
            );
        }
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        $body = $this->eventBody();
        $signature = $this->sign($body);
        $tampered = str_replace('"amount":400000', '"amount":4000000', $body);

        $this->assertNotSame($body, $tampered, 'The tamper must actually change the body.');
        $this->assertFalse(app(PayMongoService::class)->verifyWebhook($tampered, $signature));
    }

    public function test_a_missing_or_malformed_signature_is_rejected(): void
    {
        $body = $this->eventBody();
        $paymongo = app(PayMongoService::class);

        foreach (['', 'nonsense', 'te='.hash_hmac('sha256', time().'.'.$body, self::TEST_SECRET)] as $bad) {
            $this->assertFalse($paymongo->verifyWebhook($body, $bad));
        }
    }

    /** Test and live webhooks are separate registrations with separate secrets. */
    public function test_a_secret_in_the_wrong_mode_slot_is_rejected(): void
    {
        $body = $this->eventBody();
        $paymongo = app(PayMongoService::class);

        $this->assertFalse($paymongo->verifyWebhook($body, $this->sign($body, secret: self::TEST_SECRET, slot: 'li')));
        $this->assertTrue($paymongo->verifyWebhook($body, $this->sign($body, secret: self::LIVE_SECRET, slot: 'li')));
    }

    // ── Never a non-2xx ────────────────────────────────────────────

    /**
     * PayMongo disables a webhook that repeatedly answers 4xx/5xx and never
     * re-enables it, after which every payment silently stops being recorded.
     */
    public function test_the_webhook_answers_200_to_everything(): void
    {
        $bodies = ['', 'not json at all', '[]', '{"data":"a string"}', '{"data":null}', $this->eventBody()];

        foreach ($bodies as $body) {
            foreach ([null, '', 'garbage', $this->sign($body)] as $signature) {
                $response = $this->call(
                    'POST', '/webhooks/paymongo', [], [], [],
                    array_filter([
                        'CONTENT_TYPE' => 'application/json',
                        'HTTP_PAYMONGO_SIGNATURE' => $signature,
                    ], fn ($v) => $v !== null),
                    $body
                );

                $this->assertSame(200, $response->getStatusCode(), 'A non-2xx here gets the webhook disabled.');
            }
        }
    }

    /** They are machines that discard the cookie; a session row per delivery is waste. */
    public function test_the_webhooks_start_no_session(): void
    {
        $response = $this->call('POST', '/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        $this->assertSame([], $response->headers->getCookies());
    }

    // ── The transfer callback ──────────────────────────────────────

    /**
     * This endpoint has no signature, no auth and — correctly — no throttle,
     * because a 429 is a failed delivery. With an empty body it used to sync
     * EVERY pending transfer, one outbound PayMongo call each, which made it
     * an amplifier against our own API quota for anyone who found the URL.
     */
    public function test_a_transfer_callback_with_no_id_touches_nothing(): void
    {
        Http::fake();
        $this->makePendingTransfer('tr_one');
        $this->makePendingTransfer('tr_two');

        $this->postJson('/webhooks/paymongo/transfer', [])
            ->assertOk()
            ->assertJson(['received' => true, 'synced' => 0, 'failed' => 0]);

        Http::assertNothingSent();
    }

    public function test_a_transfer_callback_for_an_unknown_id_touches_nothing(): void
    {
        Http::fake();
        $this->makePendingTransfer('tr_one');

        $this->postJson('/webhooks/paymongo/transfer', ['data' => ['id' => 'tr_not_ours']])
            ->assertOk()
            ->assertJson(['synced' => 0]);

        Http::assertNothingSent();
    }

    /** A callback that names one of our transfers still does its job. */
    public function test_a_transfer_callback_for_a_known_id_syncs_just_that_one(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 'tr_one', 'attributes' => ['status' => 'pending']]], 200)]);

        $this->makePendingTransfer('tr_one');
        $this->makePendingTransfer('tr_two');

        $this->postJson('/webhooks/paymongo/transfer', ['data' => ['id' => 'tr_one']])
            ->assertOk()
            ->assertJson(['synced' => 1]);
    }

    // ── Recording: atomicity and idempotency ───────────────────────

    /**
     * The one that bites. recordPaymongoPayment() ran in no transaction, so a
     * failure between the INSERT and recalculateFinancials() left the payment
     * recorded while the booking still said `amount_paid = 0` — and the stale
     * sweeper's ONLY safety net is `amount_paid <= 0`, so it then cancelled a
     * booking the guest had actually paid for.
     */
    public function test_a_failure_partway_through_records_no_payment_at_all(): void
    {
        $booking = $this->makeBooking();

        // `availability_blocks` is read by hasConflict(), which runs inside
        // confirmOnFirstPayment() — i.e. INSIDE the transaction, after the
        // payment row has already been inserted. Exactly the window that used
        // to strand a payment.
        Schema::drop('availability_blocks');

        try {
            $this->record($booking, 4000.0, 'pay_partial');
        } catch (\Throwable $e) {
            // expected
        }

        $booking->refresh();

        $this->assertSame(0, Payment::where('booking_id', $booking->id)->count(), 'The payment row must have rolled back with everything else.');
        $this->assertSame(0.0, (float) $booking->amount_paid);
        $this->assertSame('pending', $booking->status);
    }

    /** ...and because nothing was half-written, a later delivery still works. */
    public function test_a_later_delivery_can_still_record_after_a_transient_failure(): void
    {
        $booking = $this->makeBooking();

        Schema::drop('availability_blocks');

        try {
            $this->record($booking, 4000.0, 'pay_transient');
        } catch (\Throwable $e) {
            // expected
        }

        // The transient fault clears.
        Schema::create('availability_blocks', function ($t) {
            $t->id();
            $t->unsignedBigInteger('property_id')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });

        $this->assertTrue($this->record($booking, 4000.0, 'pay_transient'));

        $booking->refresh();
        $this->assertSame(4000.0, (float) $booking->amount_paid);
        $this->assertSame('confirmed', $booking->status);
    }

    /**
     * The other half of the same boundary: once the transaction has committed,
     * a failure in the side effects — a dead SMTP host, a Pusher blip, a
     * notification insert — must NOT cost the payment. Money first, telling
     * people about it second.
     */
    public function test_a_failure_after_the_commit_still_keeps_the_payment(): void
    {
        $booking = $this->makeBooking();

        // Only reached after the commit.
        Schema::drop('notifications');

        try {
            $this->record($booking, 4000.0, 'pay_after_commit');
        } catch (\Throwable $e) {
            // The webhook's own catch-all turns this into a 200 and notifies
            // the admins; what matters is what survived in the database.
        }

        $booking->refresh();

        $this->assertSame(1, Payment::where('booking_id', $booking->id)->count(), 'The payment was already committed — it must survive.');
        $this->assertSame(4000.0, (float) $booking->amount_paid);
        $this->assertSame('confirmed', $booking->status);
    }

    /**
     * Any booking already stranded by the old code repairs itself the next
     * time a delivery for the same payment arrives. Before, that path was a
     * bare `return false` and the damage was permanent.
     */
    public function test_a_stranded_booking_is_reconciled_by_a_duplicate_delivery(): void
    {
        $booking = $this->makeBooking();

        // Exactly the state the old non-transactional code produced.
        Payment::create([
            'booking_id' => $booking->id, 'amount' => 4000, 'payment_method' => 'qrph',
            'payment_type' => 'full_payment', 'status' => 'success', 'payment_date' => today(),
            'reference_number' => 'pay_stranded', 'notes' => 'PayMongo online payment',
        ]);

        $this->assertSame(0.0, (float) $booking->fresh()->amount_paid, 'Precondition: the booking has not caught up.');

        $this->assertFalse($this->record($booking, 4000.0, 'pay_stranded'), 'No NEW payment should be recorded.');

        $booking->refresh();
        $this->assertSame(4000.0, (float) $booking->amount_paid, 'The duplicate delivery should have reconciled it.');
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->count(), 'Reconciling must never double-count.');
    }

    /** Duplicate deliveries stay idempotent, and reconciling is inert once healthy. */
    public function test_replaying_the_same_payment_never_records_it_twice(): void
    {
        $booking = $this->makeBooking();

        $this->assertTrue($this->record($booking, 4000.0, 'pay_once'));

        for ($i = 0; $i < 4; $i++) {
            $this->assertFalse($this->record($booking, 4000.0, 'pay_once'));
        }

        $booking->refresh();
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->count());
        $this->assertSame(4000.0, (float) $booking->amount_paid);
    }

    /** A zero or negative amount is never a payment. */
    public function test_a_non_positive_amount_is_never_recorded(): void
    {
        $booking = $this->makeBooking();

        $this->assertFalse($this->record($booking, 0.0, 'pay_zero'));
        $this->assertFalse($this->record($booking, -500.0, 'pay_negative'));
        $this->assertSame(0, Payment::where('booking_id', $booking->id)->count());
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function sign(string $payload, ?string $secret = null, string $slot = 'te', ?int $ts = null): string
    {
        $ts ??= time();
        $mac = hash_hmac('sha256', $ts.'.'.$payload, $secret ?? self::TEST_SECRET);

        return "t={$ts},{$slot}={$mac}";
    }

    private function eventBody(): string
    {
        return json_encode([
            'data' => [
                'id' => 'evt_test',
                'attributes' => [
                    'type' => 'payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => 'pay_test_1',
                        'attributes' => [
                            'amount' => 400000,
                            'source' => ['type' => 'qrph'],
                            'metadata' => ['booking_id' => 999999],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function record(Booking $booking, float $amount, string $reference): bool
    {
        $controller = app(\App\Http\Controllers\PaymentController::class);
        $method = (new \ReflectionClass($controller))->getMethod('recordPaymongoPayment');
        $method->setAccessible(true);

        return $method->invoke($controller, $booking, $amount, 'qrph', $reference, 'full_payment');
    }

    private function makePendingTransfer(string $transferId): RefundTransfer
    {
        $payment = Payment::create([
            'booking_id' => $this->makeBooking()->id, 'amount' => 500, 'payment_method' => 'qrph',
            'payment_type' => 'refund', 'status' => 'pending', 'payment_date' => today(),
        ]);

        return RefundTransfer::create([
            'payment_id' => $payment->id,
            'transfer_id' => $transferId,
            'reference_number' => 'REF-'.$transferId,
            'status' => 'pending',
            'amount' => 500,
            'provider' => 'instapay',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // Task 10 F6 / F7 — what an exception is allowed to tell the user
    //
    // APP_DEBUG=false does nothing for any of these: the strings were
    // concatenated by our own code, not rendered by the exception handler.
    // ══════════════════════════════════════════════════════════════

    /**
     * The gateway's raw response body was going to the GUEST.
     * PayMongoService throws `new \Exception('PayMongo Error: '.$response->body())`
     * and the controller flashed getMessage() verbatim — the same mistake v7.5
     * fixed in GeminiService, where guests saw Groq's JSON in the chat bubble.
     */
    public function test_a_gateway_failure_does_not_show_the_guest_the_raw_error(): void
    {
        $booking = $this->makeBooking();

        $secret = 'PayMongo Error: {"errors":[{"detail":"merchant acct_123 is not activated","code":"x"}]}';

        // createCheckout() takes Cache::store('database')->lock(...) — pinned to
        // that store on purpose, so a Redis blip under CACHE_STORE=failover
        // cannot put two requests' locks in two different stores. It needs the
        // real lock table; without it the lock throws before the try block and
        // the request 500s, which looks exactly like "no flash message".
        $this->makeCacheLockTable();

        $fake = \Mockery::mock(PayMongoService::class);
        $fake->shouldReceive('createCheckoutSession')->once()->andThrow(new \Exception($secret));
        $fake->shouldReceive('isTestMode')->andReturn(true);
        $this->app->instance(PayMongoService::class, $fake);

        $response = $this->actingAs($booking->user)->post(
            route('payment.checkout', $booking),
            ['payment_type' => 'full_payment']
        );

        $flash = session('error');

        $this->assertNotNull($flash, 'The guest still needs to be told something went wrong.');
        $this->assertStringNotContainsString('PayMongo Error', $flash);
        $this->assertStringNotContainsString('acct_123', $flash);
        $this->assertStringNotContainsString('merchant', $flash);
        $this->assertStringContainsString('not been charged', $flash,
            'And it should say the thing the guest actually wants to know.');
    }

    /**
     * The admin refund button. Its guard messages ARE written to be shown —
     * they end by naming the manual route out — so those must survive; only
     * the unexpected ones become generic.
     */
    public function test_a_refund_guard_message_still_reaches_the_admin(): void
    {
        [$admin, $refund] = $this->makeRefundAwaitingPayout();

        $guard = 'The PayMongo wallet has ₱5.00 available but ₱1,010.00 is needed. Top up the wallet.';

        $fake = \Mockery::mock(\App\Services\RefundTransferService::class);
        $fake->shouldReceive('send')->once()
            ->andThrow(new \App\Exceptions\RefundNotSendable($guard));
        $this->app->instance(\App\Services\RefundTransferService::class, $fake);

        $this->actingAs($admin)->post(
            route('admin.payments.send', $refund),
            ['confirm_amount' => (string) $refund->amount]
        );

        $this->assertSame($guard, session('error'),
            'A RefundNotSendable is admin-facing by construction and must not be swallowed.');
    }

    /**
     * And the case the old `catch (\Throwable)` also covered. Narrowing to
     * \RuntimeException would NOT have helped:
     *     QueryException <- PDOException <- RuntimeException
     * so a SQL error carries its query and bound values straight through.
     */
    public function test_a_sql_error_during_a_refund_is_not_shown_to_the_admin(): void
    {
        [$admin, $refund] = $this->makeRefundAwaitingPayout();

        $sql = 'SQLSTATE[42S22]: Column not found: 1054 Unknown column (SQL: insert into `refund_transfers` '
            .'(`payment_id`, `reference_number`) values (7, VE-SECRET-REF))';

        $queryException = new \Illuminate\Database\QueryException('mysql', $sql, [], new \PDOException($sql));

        $this->assertInstanceOf(\RuntimeException::class, $queryException,
            'If this ever stops being true, the narrowing argument changes.');

        $fake = \Mockery::mock(\App\Services\RefundTransferService::class);
        $fake->shouldReceive('send')->once()->andThrow($queryException);
        $this->app->instance(\App\Services\RefundTransferService::class, $fake);

        $this->actingAs($admin)->post(
            route('admin.payments.send', $refund),
            ['confirm_amount' => (string) $refund->amount]
        );

        $flash = (string) session('error');

        $this->assertStringNotContainsString('SQLSTATE', $flash);
        $this->assertStringNotContainsString('refund_transfers', $flash);
        $this->assertStringNotContainsString('VE-SECRET-REF', $flash);
        $this->assertStringContainsString('Mark Paid Out', $flash,
            'The admin still needs the manual route out.');
    }

    private function makeCacheLockTable(): void
    {
        if (Schema::hasTable('cache_locks')) {
            return;
        }

        Schema::create('cache_locks', function ($t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });
    }

    /** @return array{0: User, 1: Payment} */
    private function makeRefundAwaitingPayout(): array
    {
        static $n = 0;
        $n++;

        $booking = $this->makeBooking();

        $admin = User::create([
            'full_name' => 'Admin '.$n, 'email' => "admin{$n}@example.test",
            'password' => 'x', 'role' => 'admin', 'status' => 1,
        ]);

        // `status` must be 'pending', not 'success' — isAwaitingPayout() reads
        // the payment status, and a refund that has already been paid out is
        // exactly the thing the controller refuses to send again.
        $refund = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 1000,
            'payment_method' => 'qrph',
            'payment_type' => 'refund',
            'status' => 'pending',
            'payment_date' => now()->toDateString(),
        ]);

        \App\Models\RefundDestination::create([
            'payment_id' => $refund->id,
            'institution_name' => 'Test Bank',
            // Any BIC works: NO_AUTO_TRANSFER_BICS is empty by design, so
            // canReceiveTransfer() is true unless someone repopulates it.
            'institution_bic' => 'TESTPHM1XXX',
            'account_number' => '999999990001',
            'account_name' => 'Guest Name',
            'provided_by' => $admin->id,
        ]);

        // The guards read these through the relations, which were loaded as
        // null before the destination existed.
        return [$admin, $refund->fresh()];
    }

    private function makeBooking(): Booking
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'full_name' => 'Guest '.$n, 'email' => "guest{$n}@example.test",
            'password' => 'x', 'role' => 'customer', 'status' => 1,
        ]);

        $property = Property::firstOrCreate(
            ['property_name' => 'Villa Elena (Whole Villa)'],
            ['type' => 'villa', 'status' => 'available', 'base_price' => 4000, 'weekend_price' => 6000]
        );

        return Booking::create([
            'user_id' => $user->id, 'property_id' => $property->id,
            'check_in_date' => now()->addDays(7 + $n)->toDateString(),
            'check_out_date' => now()->addDays(7 + $n)->toDateString(),
            'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00',
            'num_guests' => 2, 'base_amount' => 4000, 'total_amount' => 4000,
            'amount_paid' => 0, 'balance_due' => 4000, 'status' => 'pending', 'source' => 'online',
        ]);
    }

    private function makeNotificationsTable(): void
    {
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
            $t->string('email');
            $t->string('password');
            $t->string('role')->default('customer');
            $t->integer('status')->default(1);
            $t->string('phone')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->boolean('email_notifications_enabled')->default(true);
            $t->timestamps();
        });

        Schema::create('properties', function ($t) {
            $t->id();
            $t->string('property_name');
            $t->string('type')->default('villa');
            $t->string('status')->default('available');
            $t->decimal('base_price', 10, 2)->default(0);
            $t->decimal('weekend_price', 10, 2)->default(0);
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
            $t->integer('num_guests')->default(1);
            $t->decimal('base_amount', 10, 2)->default(0);
            $t->decimal('total_amount', 10, 2)->default(0);
            $t->decimal('amount_paid', 10, 2)->default(0);
            $t->decimal('balance_due', 10, 2)->default(0);
            $t->decimal('discount_amount', 10, 2)->default(0);
            $t->string('status')->default('pending');
            $t->string('payment_status')->default('unpaid');
            $t->string('source')->nullable();
            $t->string('paymongo_session_id')->nullable();
            $t->string('paymongo_payment_type')->nullable();
            $t->timestamp('overpayment_notified_at')->nullable();
            // Real column, and it has to be here: Booking::slotHoldColumnExists()
            // memoises its answer in a STATIC for the whole PHPUnit process, so
            // whichever test class builds `bookings` first decides it for
            // everyone. Leaving it out passes alone and fails in the full suite
            // with "table bookings has no column named slot_hold".
            $t->string('slot_hold')->nullable()->unique();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('payments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->decimal('amount', 10, 2);
            $t->string('payment_method');
            $t->string('payment_type');
            $t->string('status')->default('pending');
            $t->date('payment_date')->nullable();
            $t->string('reference_number')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('received_by')->nullable();
            $t->timestamps();
            $t->unique(['booking_id', 'reference_number']);
        });

        Schema::create('refund_destinations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('payment_id');
            $t->string('institution_name');
            $t->string('institution_bic', 16);
            $t->string('account_number', 64);
            $t->string('account_name');
            $t->unsignedBigInteger('provided_by')->nullable();
            $t->timestamp('provided_at')->nullable();
            $t->timestamps();
        });

        Schema::create('refund_transfers', function ($t) {
            $t->id();
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->string('transfer_id')->nullable();
            $t->string('batch_transfer_id')->nullable();
            $t->string('reference_number')->nullable();
            $t->string('provider_reference_number')->nullable();
            $t->string('provider')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('amount', 10, 2)->default(0);
            $t->decimal('fee', 10, 2)->default(0);
            $t->string('institution_name')->nullable();
            $t->string('institution_bic')->nullable();
            $t->string('account_number')->nullable();
            $t->string('account_name')->nullable();
            $t->string('provider_error_code')->nullable();
            $t->string('provider_error_message')->nullable();
            $t->string('sub_code')->nullable();
            $t->unsignedBigInteger('initiated_by')->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('availability_blocks', function ($t) {
            $t->id();
            $t->unsignedBigInteger('property_id')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });

        Schema::create('staff_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action')->nullable();
            $t->string('target_table')->nullable();
            $t->unsignedBigInteger('target_id')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });

        $this->makeNotificationsTable();
    }
}
