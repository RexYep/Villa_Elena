<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    
}