<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $property_name
 * @property string $type
 * @property string|null $description
 * @property int $max_capacity
 * @property numeric $base_price
 * @property numeric|null $weekend_price
 * @property array<array-key, mixed>|null $amenities
 * @property numeric|null $floor_area_sqm
 * @property int|null $floor_level
 * @property string $status
 * @property bool $is_featured
 * @property int $sort_order
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AvailabilityBlock> $availabilityBlocks
 * @property-read int|null $availability_blocks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Booking|null $currentBooking
 * @property-read float $average_rating
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HousekeepingTask> $housekeepingTasks
 * @property-read int|null $housekeeping_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PropertyImage> $images
 * @property-read int|null $images_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PricingRule> $pricingRules
 * @property-read int|null $pricing_rules_count
 * @property-read \App\Models\PropertyImage|null $primaryImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereAmenities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereBasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereFloorAreaSqm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereFloorLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereMaxCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property wherePropertyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereWeekendPrice($value)
 * @mixin \Eloquent
 */
class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_name',
        'type',
        'description',
        'max_capacity',
        'base_price',
        'weekend_price',
        'amenities',
        'floor_area_sqm',
        'floor_level',
        'status',
        'is_featured',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'amenities'   => 'array',
        'base_price'  => 'decimal:2',
        'weekend_price' => 'decimal:2',
        'is_featured' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────
    public function currentBooking()
{
    return $this->hasOne(\App\Models\Booking::class)
        ->where('status', 'checked_in')
        ->with('user:id,full_name')
        ->latest();
}
 

public function primaryImage()
{
    return $this->hasOne(\App\Models\PropertyImage::class)
        ->where('is_primary', 1);
}
 
public function images()
{
    return $this->hasMany(\App\Models\PropertyImage::class);
}
 

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function pricingRules()
    {
        return $this->hasMany(PricingRule::class);
    }

    public function availabilityBlocks()
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function getAverageRatingAttribute(): float
    {
        return round($this->reviews()->where('status', 'approved')->avg('overall_rating') ?? 0, 1);
    }

    public function getPriceForDate(\Carbon\Carbon $date): float
    {
        // Check if a pricing rule applies for this date
        $rule = $this->pricingRules()
            ->where('is_active', 1)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($rule) {
            return $rule->type === 'percentage'
                ? $this->base_price * (1 + $rule->price / 100)
                : $rule->price;
        }

        // Check if it's a weekend (Friday=5, Saturday=6)
        if ($date->isSaturday() || $date->isSunday()) {
            return $this->weekend_price ?? $this->base_price;
        }

        return $this->base_price;
    }

    /**
     * FLAT/PACKAGE PRICE base sa CHECK-IN lang (hindi per-night).
     * Bawat booking dito ay Day (8AM–5PM) o Night (7PM–6AM) fixed-slot na
     * package, hindi per-night na stay,
     * kaya ang binabayaran ng customer ay nakadepende lang sa kung anong
     * araw/oras sila CHECK-IN, hindi sa dami ng gabi ng stay.
     *
     * Segments:
     *  - Lunes–Huwebes:                          base_price    (₱4,000)
     *  - Biyernes, Sabado, Linggo hanggang 6PM:   weekend_price (₱6,000)
     *  - Linggo 6:00 PM pataas:                   base_price    (₱4,000)
     */
    public function getPackagePrice(\Carbon\Carbon $checkin): float
    {
        // Special date-range pricing rule (hal. holiday override) — mananatili
        // itong pinaka-priority kung meron.
        $rule = $this->pricingRules()
            ->where('is_active', 1)
            ->where('start_date', '<=', $checkin)
            ->where('end_date', '>=', $checkin)
            ->first();

        if ($rule) {
            return $rule->type === 'percentage'
                ? $this->base_price * (1 + $rule->price / 100)
                : $rule->price;
        }

        $dayOfWeek = $checkin->dayOfWeek; // 0=Sunday ... 6=Saturday

        // Linggo: mahal pa hanggang 6PM, tapos mura na
        if ($dayOfWeek === 0) {
            return $checkin->format('H:i') >= '18:00'
                ? $this->base_price
                : ($this->weekend_price ?? $this->base_price);
        }

        // Biyernes (5) at Sabado (6): peak rate buong araw
        if (in_array($dayOfWeek, [5, 6])) {
            return $this->weekend_price ?? $this->base_price;
        }

        // Lunes–Huwebes: regular rate
        return $this->base_price;
    }

    /**
     * ANG IISANG PINAGMUMULAN ng presyong sinisingil sa isang booking.
     *
     * Katulad ng papel na ginagampanan ng Booking::slotDateTimes() para sa
     * oras: bawat path na gumagawa o nagpepresyo ng booking — public
     * portal, live price preview, staff walk-in, admin create, customer
     * reschedule — ay dapat dumaan DITO sa halip na kunin ang
     * getPackagePrice() at magkuwenta ng sariling promo. Kung may dalawang
     * lugar na magkuwenta, magkakaiba ang ipinakitang presyo sa aktwal na
     * sinisingil, at ang guest ang unang makakapansin.
     *
     * Ang promo ay laging laban sa BASE RATE lang — hindi kasama ang
     * extras. Sinasadya iyon: ang mga extras ay pass-through na gastos
     * (pagkain, karagdagang serbisyo) na hindi dapat bawasan ng kampanya,
     * at ganoon din ang hugis ng
     * Admin\BookingController::recalculateBookingTotals() —
     * `base_amount + extras - discount_amount`.
     *
     * @return array{base: float, discount: float, total: float, promo: ?\App\Models\Discount}
     */
    public function quoteFor(\Carbon\Carbon $checkin, ?string $slot = null): array
    {
        $base  = round((float) $this->getPackagePrice($checkin), 2);
        $promo = Discount::bestFor($base, $checkin, $slot);
        $off   = $promo ? $promo->calculateDiscount($base) : 0.0;

        return [
            'base'     => $base,
            'discount' => $off,
            'total'    => round($base - $off, 2),
            'promo'    => $promo,
        ];
    }
}