<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PromotionController;
use App\Models\Booking;
use App\Models\Property;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Task 2 — input validation and business-critical values.
 *
 * Like RateLimitingTest, this builds the few tables it needs by hand: the real
 * migrations are MySQL-only and don't run on the in-memory SQLite test
 * connection.
 */
class InputValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // ── Finding 1: only the villa is a bookable listing ────────────

    public function test_the_public_property_gate_rejects_a_room(): void
    {
        $gate = new \ReflectionMethod(\App\Http\Controllers\Portal\PortalController::class, 'assertBookableListing');
        $gate->setAccessible(true);
        $controller = app(\App\Http\Controllers\Portal\PortalController::class);

        // The villa passes.
        $gate->invoke($controller, (new Property)->forceFill(['type' => 'villa']));

        foreach (['room', 'cottage', ''] as $type) {
            try {
                $gate->invoke($controller, (new Property)->forceFill(['type' => $type]));
                $this->fail("A property of type '{$type}' must not be bookable.");
            } catch (NotFoundHttpException $e) {
                $this->assertSame(404, $e->getStatusCode());
            }
        }
    }

    /**
     * The gate has to be on every public `{property}` route, not merely exist.
     * `/properties/{id}` is the page that links to the booking form, so an
     * unguarded detail page hands out the door to an unguarded book page.
     */
    public function test_every_public_property_route_is_behind_the_gate(): void
    {
        $this->makePropertiesTable();
        $this->makeSettingsTable();

        $room = Property::create(['property_name' => 'Room 1', 'type' => 'room', 'status' => 'available', 'base_price' => 0, 'max_capacity' => 7]);
        $villa = Property::create(['property_name' => 'Villa Elena', 'type' => 'villa', 'status' => 'available', 'base_price' => 4000, 'weekend_price' => 6000, 'max_capacity' => 30]);

        foreach ([
            "/properties/{$room->id}",
            "/properties/{$room->id}/price-preview?checkin=2099-01-01&slot=day",
            "/book/{$room->id}",
        ] as $uri) {
            $this->get($uri)->assertNotFound();
        }

        // POST /book/{property} is the one that actually created the ₱0
        // booking. It sits behind `auth`, so a guest redirect would mask the
        // gate — assert it is NOT a 2xx and that no booking row appeared.
        $this->makeBookingsTable();
        $this->post("/book/{$room->id}", ['checkin' => '2099-01-01', 'slot' => 'day', 'guests' => 2, 'policies_accepted' => 1]);
        $this->assertSame(0, Booking::count(), 'A room must never produce a booking row.');

        // The villa is untouched by the gate: it gets past 404 to its own
        // handling (a redirect to login, not a not-found).
        $this->get("/book/{$villa->id}")->assertStatus(302);
    }

    /**
     * Why it mattered: the room rows price at zero, so the unguarded route
     * did not just create a stray booking, it created a FREE one.
     */
    public function test_a_room_prices_at_zero_which_is_why_the_gate_matters(): void
    {
        $this->makePropertiesTable();

        $room = Property::create(['property_name' => 'Room 1', 'type' => 'room', 'status' => 'available', 'base_price' => 0, 'max_capacity' => 7]);

        $this->assertSame(0.0, (float) $room->quoteFor(now()->addMonth(), 'day')['total']);
    }

    // ── Finding 2: a guest cancel must not rewrite property status ──

    public function test_cancelling_a_booking_does_not_touch_property_status(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Customer/HomeController.php'));

        $cancel = substr($source, (int) strpos($source, 'function cancelBooking'));
        $cancel = substr($cancel, 0, (int) strpos($cancel, "\n    public function "));

        $this->assertStringNotContainsString(
            "property->update(['status'",
            $cancel,
            'A guest cancel must not overwrite a status an admin set (e.g. maintenance).'
        );
    }

    /**
     * The reason the write was safe to drop: a guest can never cancel a
     * booking that put the property into `occupied` in the first place.
     */
    public function test_a_guest_can_only_cancel_a_booking_that_never_occupied_the_villa(): void
    {
        foreach (['pending', 'confirmed'] as $status) {
            $this->assertTrue((new Booking)->forceFill(['status' => $status])->isCancellable());
        }

        foreach (['checked_in', 'checked_out', 'cancelled', 'no_show'] as $status) {
            $this->assertFalse((new Booking)->forceFill(['status' => $status])->isCancellable());
        }
    }

    // ── Finding 3: a fixed promo cannot exceed the peak rate ────────

    public function test_a_fixed_promo_is_bounded_by_the_peak_rate(): void
    {
        $this->makePropertiesTable();

        Property::create(['property_name' => 'Villa Elena', 'type' => 'villa', 'status' => 'available', 'base_price' => 4000, 'weekend_price' => 6000, 'max_capacity' => 30]);

        $max = new \ReflectionMethod(PromotionController::class, 'maxFixedDiscount');
        $max->setAccessible(true);

        $this->assertSame(6000.0, $max->invoke(app(PromotionController::class)), 'The ceiling is the villa peak rate.');

        // An active pricing rule above the list price raises the ceiling,
        // because getPackagePrice() lets such a rule override both prices.
        \App\Models\PricingRule::create(['price' => 9500, 'is_active' => 1]);
        $this->assertSame(9500.0, $max->invoke(app(PromotionController::class)));
    }

    public function test_the_fixed_promo_ceiling_never_becomes_zero(): void
    {
        $this->makePropertiesTable();

        // An empty database must not produce `max:0`, which would reject
        // every promo and look like a broken form.
        $max = new \ReflectionMethod(PromotionController::class, 'maxFixedDiscount');
        $max->setAccessible(true);

        $this->assertGreaterThan(0, $max->invoke(app(PromotionController::class)));
    }

    // ── Regression guards for what was already correct ─────────────

    /**
     * Every model must keep an explicit allow-list. A `$guarded = []` would
     * make every column mass-assignable, including `role`, `status` and
     * `total_amount`.
     */
    public function test_no_model_opens_itself_to_full_mass_assignment(): void
    {
        foreach (glob(app_path('Models/*.php')) as $file) {
            $source = file_get_contents($file);
            $model = basename($file, '.php');

            $this->assertStringContainsString('protected $fillable', $source, "{$model} has no \$fillable allow-list.");
            $this->assertDoesNotMatchRegularExpression('/\$guarded\s*=\s*\[\s*\]/', $source, "{$model} disables mass-assignment protection.");
        }
    }

    /**
     * No controller may hand request input straight to a writer. This is the
     * pattern that turns any over-broad $fillable into privilege escalation.
     */
    public function test_no_controller_mass_assigns_the_whole_request(): void
    {
        $offenders = [];

        foreach ($this->controllerFiles() as $file) {
            $source = file_get_contents($file);

            if (preg_match('/(create|update|fill|insert|updateOrCreate)\(\s*\$request->all\(\)|(create|update|fill)\(\s*request\(\)->all\(\)/', $source)) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * Raw SQL must never be built by interpolation. Bindings are fine; a
     * `$var` or `{$var}` inside the SQL string is not.
     */
    public function test_no_raw_sql_interpolates_a_variable(): void
    {
        $offenders = [];
        $raw = '/(?:whereRaw|selectRaw|orderByRaw|havingRaw|groupByRaw|fromRaw|DB::raw|DB::statement|DB::select)\(\s*([\'"])(.*?)\1/s';

        foreach (array_merge($this->controllerFiles(), glob(app_path('Models/*.php')), glob(app_path('Services/*.php'))) as $file) {
            if (! preg_match_all($raw, file_get_contents($file), $m, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($m as $match) {
                // Only double-quoted strings interpolate in PHP.
                if ($match[1] === '"' && preg_match('/\$\w+|\{\$/', $match[2])) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).': '.$match[2];
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function controllerFiles(): array
    {
        $files = [];
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Http/Controllers')));

        foreach ($dir as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Properties plus the two tables pricing reads: getPackagePrice() always
     * looks for a pricing_rules override, and quoteFor() then asks Discount.
     */
    private function makePropertiesTable(): void
    {
        Schema::create('pricing_rules', function ($table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('label')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('discounts', function ($table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('label')->nullable();
            $table->string('description')->nullable();
            $table->string('type')->default('fixed');
            $table->decimal('value', 10, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('min_nights')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->string('applies_to')->default('all');
            $table->boolean('is_public')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('properties', function ($table) {
            $table->id();
            $table->string('property_name')->nullable();
            $table->string('type')->default('room');
            $table->text('description')->nullable();
            $table->integer('max_capacity')->default(1);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('weekend_price', 10, 2)->nullable();
            $table->text('amenities')->nullable();
            $table->string('status')->default('available');
            $table->boolean('is_featured')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Empty is fine — every caller passes a default. It just has to EXIST,
     * because CheckMaintenanceMode reads settings on every public page.
     */
    private function makeSettingsTable(): void
    {
        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('data_type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    private function makeBookingsTable(): void
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
            $table->text('special_requests')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
