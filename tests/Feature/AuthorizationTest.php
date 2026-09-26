<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Review;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 3 — authorization and IDOR.
 *
 * Every ownership check in the customer portal is an inline `abort_if` in a
 * controller. They were all present and correct when audited, but nothing
 * proved it, so a refactor could drop one silently. These tests log in as one
 * guest and reach for another guest's records by id.
 *
 * Tables are built by hand: the real migrations are MySQL-only and don't run
 * on the in-memory SQLite test connection.
 */
class AuthorizationTest extends TestCase
{
    private User $owner;

    private User $intruder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // CheckMaintenanceMode reads settings on every request it guards, so
        // the table has to exist before any route is hit. Empty is fine —
        // every caller of Setting::get() passes a default.
        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('data_type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    // ── IDOR: one guest reaching for another's records ─────────────

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function customerRecordProvider(): array
    {
        return [
            'booking detail' => ['GET', 'my/bookings/{booking}'],
            'booking cancel' => ['PATCH', 'my/bookings/{booking}/cancel'],
            'reschedule form' => ['GET', 'my/bookings/{booking}/reschedule'],
            'reschedule submit' => ['PATCH', 'my/bookings/{booking}/reschedule'],
            'issue report list' => ['GET', 'my/bookings/{booking}/issues'],
            'issue report submit' => ['POST', 'my/bookings/{booking}/issues'],
            'review form' => ['GET', 'my/bookings/{booking}/review'],
            'review submit' => ['POST', 'my/bookings/{booking}/review'],
            'notification open' => ['GET', 'my/notifications/{notification}/open'],
            'review edit' => ['GET', 'my/reviews/{review}/edit'],
            'review update' => ['PUT', 'my/reviews/{review}'],
            'review delete' => ['DELETE', 'my/reviews/{review}'],
            'trusted device delete' => ['DELETE', 'my/profile/devices/{device}'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('customerRecordProvider')]
    public function test_a_guest_cannot_reach_another_guests_record(string $method, string $template): void
    {
        $ids = $this->seedTwoGuests();

        $uri = str_replace(
            ['{booking}', '{notification}', '{review}', '{device}'],
            [$ids['booking'], $ids['notification'], $ids['review'], $ids['device']],
            $template
        );

        $response = $this->actingAs($this->intruder)->call($method, '/'.$uri);

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            "{$method} /{$uri} answered {$response->getStatusCode()} — it must refuse another guest's record."
        );
    }

    /** The same records must still be reachable by the person who owns them. */
    public function test_the_owner_is_not_locked_out_of_their_own_records(): void
    {
        $ids = $this->seedTwoGuests();

        foreach ([
            "my/bookings/{$ids['booking']}",
            "my/notifications/{$ids['notification']}/open",
            "my/reviews/{$ids['review']}/edit",
        ] as $uri) {
            $status = $this->actingAs($this->owner)->get('/'.$uri)->getStatusCode();

            $this->assertNotContains(
                $status,
                [403, 404],
                "The owner was refused their own record at /{$uri} ({$status})."
            );
        }
    }

    /** Payment pages are `auth` only — no role middleware — so the controller check is the whole guard. */
    public function test_a_guest_cannot_reach_another_guests_payment_pages(): void
    {
        $ids = $this->seedTwoGuests();

        foreach ([
            ['GET', "pay/{$ids['booking']}"],
            ['GET', "pay/{$ids['booking']}/status"],
            ['GET', "pay/{$ids['booking']}/success"],
            ['GET', "pay/{$ids['booking']}/cancel"],
            ['POST', "pay/{$ids['booking']}/checkout"],
        ] as [$method, $uri]) {
            $status = $this->actingAs($this->intruder)->call($method, '/'.$uri)->getStatusCode();

            $this->assertContains($status, [403, 404], "{$method} /{$uri} answered {$status}.");
        }
    }

    /** `booking/confirmed/{booking}` is a PUBLIC route — no auth middleware at all. */
    public function test_the_public_confirmation_page_refuses_a_stranger(): void
    {
        $ids = $this->seedTwoGuests();

        // Not logged in at all: Auth::id() is null, so the comparison must fail.
        $this->get("/booking/confirmed/{$ids['booking']}")->assertForbidden();

        // Logged in as somebody else.
        $this->actingAs($this->intruder)->get("/booking/confirmed/{$ids['booking']}")->assertForbidden();
    }

    // ── Role separation ────────────────────────────────────────────

    public function test_a_customer_cannot_reach_the_admin_or_staff_portals(): void
    {
        $this->makeUsersTable();
        $customer = $this->makeUser('customer');

        foreach (['/admin/dashboard', '/admin/users', '/admin/payments', '/staff/frontdesk', '/staff/walkin'] as $uri) {
            $this->actingAs($customer)->get($uri)->assertForbidden();
        }
    }

    public function test_staff_cannot_reach_the_admin_portal(): void
    {
        $this->makeUsersTable();
        $staff = $this->makeUser('staff');

        foreach (['/admin/dashboard', '/admin/users', '/admin/settings', '/admin/promotions', '/admin/payments'] as $uri) {
            $this->actingAs($staff)->get($uri)->assertForbidden();
        }
    }

    /** A session that outlives the account being disabled must not keep working. */
    public function test_a_deactivated_account_is_logged_out_at_the_next_request(): void
    {
        $this->makeUsersTable();
        $staff = $this->makeUser('staff', status: 0);

        $this->actingAs($staff)->get('/staff/frontdesk')->assertRedirect('/login');
        $this->assertGuest();
    }

    // ── Broadcast channel authorization ────────────────────────────

    /**
     * The dashboards were public Pusher channels until v7.24: no callback ran,
     * and none could. They carry guest names, booking references and payment
     * amounts, and the app key that unlocks a public channel is rendered into
     * pages — including the unauthenticated property page.
     */
    public function test_the_dashboard_channels_are_private(): void
    {
        $this->assertSame('private-admin-dashboard', (string) (new \App\Events\DashboardStatsChanged)->broadcastOn()[0]);
        $this->assertSame('private-staff-frontdesk', (string) (new \App\Events\StaffAvailabilityChanged)->broadcastOn()[0]);

        $issues = array_map(fn ($c) => (string) $c, (new \App\Events\IssueReportsChanged)->broadcastOn());
        $this->assertSame(['private-staff-frontdesk', 'private-admin-dashboard'], $issues);

        // Deliberately still public: a blocked date range, no names or amounts,
        // and every visitor's page is already server-rendered with it.
        $this->assertSame(
            'property-availability.14',
            (string) (new \App\Events\PropertyAvailabilityChanged(14, 'blocked', '2026-01-01', '2026-01-02'))->broadcastOn()[0]
        );
    }

    public function test_only_the_right_role_may_join_each_dashboard_channel(): void
    {
        $this->makeUsersTable();

        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $customer = $this->makeUser('customer');
        $disabled = $this->makeUser('admin', status: 0);

        // Resolve the REAL callback from routes/channels.php. (An earlier draft
        // called Broadcast::channel() here, which registers a channel rather
        // than reading one — it silently replaced the callback under test.)
        $join = fn (string $channel, User $user): bool => (bool) $this->resolveChannelCallback($channel)($user);

        $this->assertTrue($join('admin-dashboard', $admin));
        $this->assertFalse($join('admin-dashboard', $staff), 'Staff must not read the admin dashboard.');
        $this->assertFalse($join('admin-dashboard', $customer), 'A guest must not read the admin dashboard.');
        $this->assertFalse($join('admin-dashboard', $disabled), 'A deactivated admin must not read it either.');

        $this->assertTrue($join('staff-frontdesk', $staff));
        $this->assertTrue($join('staff-frontdesk', $admin), 'An admin covers the front desk too.');
        $this->assertFalse($join('staff-frontdesk', $customer), 'A guest must not read the front desk.');
        $this->assertFalse($join('staff-frontdesk', $disabled));
    }

    /** A guest may only listen on their OWN notification channel. */
    public function test_the_notification_channel_is_per_user(): void
    {
        $this->makeUsersTable();

        $a = $this->makeUser('customer');
        $b = $this->makeUser('customer');

        $callback = $this->resolveChannelCallback('notifications.{userId}');

        $this->assertTrue($callback($a, $a->id));
        $this->assertFalse($callback($a, $b->id), 'A guest must not listen on another guest\'s channel.');
    }

    // ── The key that unlocks all of it ─────────────────────────────

    /**
     * `env()` returns NULL once `php artisan config:cache` has run, and
     * docker/start.sh runs it for every non-local APP_ENV. Every realtime view
     * read the Pusher key that way, so in production the key was empty, the
     * `if (!PUSHER_KEY) return;` guard fired, and every live dashboard was
     * silently dead. Reading it from config is what survives caching.
     */
    public function test_no_view_reads_the_pusher_key_through_env(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            if (preg_match('/env\(\s*[\'"]PUSHER_/', file_get_contents($file))) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $offenders, 'env() is NULL under config:cache — read the key from config() instead.');
    }

    /** Private-channel auth is a POST into the `web` group, so it needs the token. */
    public function test_layouts_that_join_a_private_channel_expose_the_csrf_token(): void
    {
        foreach (['admin', 'staff', 'customer', 'payment'] as $layout) {
            $this->assertStringContainsString(
                'name="csrf-token"',
                file_get_contents(resource_path("views/layouts/{$layout}.blade.php")),
                "layouts/{$layout} joins a private channel but has no CSRF meta tag — /broadcasting/auth would 419."
            );
        }
    }

    // ── Helpers ────────────────────────────────────────────────────

    /** Pull the registered closure for a channel out of the broadcaster. */
    private function resolveChannelCallback(string $channel): \Closure
    {
        $broadcaster = Broadcast::getFacadeRoot()->connection();
        $channels = (new \ReflectionProperty($broadcaster, 'channels'))->getValue($broadcaster);

        $this->assertArrayHasKey($channel, $channels, "No authorization callback is registered for '{$channel}'.");

        return \Closure::fromCallable($channels[$channel]);
    }

    /** @return array{booking: int, notification: int, review: int, device: int} */
    private function seedTwoGuests(): array
    {
        $this->makeUsersTable();
        $this->makeRecordTables();

        $this->owner = $this->makeUser('customer');
        $this->intruder = $this->makeUser('customer');

        $booking = Booking::create([
            'booking_ref' => 'VE-OWNER01',
            'user_id' => $this->owner->id,
            'property_id' => 1,
            'check_in_date' => now()->addWeek()->format('Y-m-d'),
            'check_in_time' => '08:00:00',
            'check_out_date' => now()->addWeek()->format('Y-m-d'),
            'check_out_time' => '17:00:00',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'total_amount' => 4000,
            'balance_due' => 4000,
        ]);

        $notification = Notification::create([
            'user_id' => $this->owner->id,
            'title' => 'Owner only',
            'message' => 'Private',
            'type' => 'in_app',
            'is_read' => 0,
        ]);

        $review = Review::create([
            'booking_id' => $booking->id,
            'user_id' => $this->owner->id,
            'property_id' => 1,
            'rating' => 5,
            'title' => 'Lovely',
            'content' => str_repeat('a', 30),
            'status' => 'approved',
        ]);

        $device = TrustedDevice::create([
            'user_id' => $this->owner->id,
            'token_hash' => TrustedDevice::hashToken('owner-device-token'),
            'device_label' => 'Owner laptop',
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'booking' => $booking->id,
            'notification' => $notification->id,
            'review' => $review->id,
            'device' => $device->id,
        ];
    }

    private function makeUser(string $role, int $status = 1): User
    {
        static $n = 0;
        $n++;

        // Verified on purpose. Every `my/` route carries `verified`, so an
        // UNverified intruder is bounced at the middleware (302) and never
        // reaches the controller — which would make these tests pass without
        // proving anything about the ownership checks. The threat model is a
        // fully-onboarded guest editing an id in the URL.
        $user = User::create([
            'full_name' => ucfirst($role)." {$n}",
            'email' => "{$role}{$n}@example.test",
            'password' => bcrypt('secret-password'),
            'role' => $role,
            'status' => $status,
        ]);

        // Not passed to create(): `email_verified_at` is deliberately absent
        // from User::$fillable, so mass assignment drops it silently.
        $user->markEmailAsVerified();

        return $user;
    }

    /** @return array<int, string> */
    private function bladeFiles(): array
    {
        $files = [];
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($dir as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function makeUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('customer');
            $table->tinyInteger('status')->default(1);
            $table->string('phone')->nullable();
            $table->string('profile_image')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    private function makeRecordTables(): void
    {
        Schema::create('bookings', function ($table) {
            $table->id();
            $table->string('booking_ref')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->date('check_in_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('check_out_time')->nullable();
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
            $table->text('special_requests')->nullable();
            $table->timestamp('cancelled_at')->nullable();
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
            $table->timestamps();
        });

        Schema::create('reviews', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->integer('rating')->default(5);
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_reply')->nullable();
            $table->timestamps();
        });

        Schema::create('trusted_devices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token_hash', 64);
            $table->string('device_label')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
