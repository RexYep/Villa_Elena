<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Seasonal/automatic promo.
 *
 * WALANG itinatype na code ang guest. Ang isang promo ay tumatama kapag
 * pasok ang CHECK-IN date ng booking sa window nito (`start_date` →
 * `expiry_date`, pareho kasama) at tugma ang slot (`applies_to`). Iyon
 * ang ibig sabihin ng "seasonal" dito: petsa ng STAY ang batayan, hindi
 * petsa ng pag-book — para tugma sa `pricing_rules`, na ganoon din ang
 * hugis (tingnan ang Property::getPackagePrice()).
 *
 * Ang discount ay palaging kinukuwenta laban sa BASE RATE lang (package
 * price), hindi sa extras — tingnan ang Property::quoteFor().
 *
 * @property int $id
 * @property string|null $code
 * @property string|null $label
 * @property string|null $description
 * @property string $type
 * @property numeric $value
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property int $min_nights
 * @property int|null $usage_limit null = unlimited
 * @property int $used_count
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string $applies_to
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read string $slot_label
 * @property-read string $state
 * @property-read string $state_badge
 * @property-read string $value_label
 * @property-read string $window_label
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereAppliesTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereMinNights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereNotifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUsageLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUsedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereValue($value)
 * @mixin \Eloquent
 */
class Discount extends Model
{
    use HasFactory;

    /** Default na porsyento sa admin create form. */
    public const DEFAULT_PERCENTAGE = 20;

    protected $fillable = [
        'code',
        'label',
        'description',
        'type',
        'value',
        'start_date',
        'min_nights',
        'usage_limit',
        'used_count',
        'expiry_date',
        'applies_to',
        'is_public',
        'is_active',
        'notified_at',
    ];

    protected $casts = [
        'value'       => 'decimal:2',
        'start_date'  => 'date',
        'expiry_date' => 'date',
        'is_active'   => 'boolean',
        'is_public'   => 'boolean',
        'notified_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // ── Validity ───────────────────────────────────────────────────

    /**
     * Buhay pa ba ang promo sa kabuuan — active, hindi pa ubos ang
     * usage limit, at hindi pa lampas ang expiry. HINDI nito sinusuri
     * ang partikular na check-in date; para doon, isValidOn().
     */
    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        // Inclusive ang huling araw — ang isang promo na "expires Aug 31"
        // ay dapat pa ring tumama sa isang Aug 31 na check-in.
        if ($this->expiry_date && $this->expiry_date->lt(Carbon::today())) return false;
        return true;
    }

    /**
     * Tumatama ba ang promo sa PARTIKULAR na check-in datetime at slot?
     */
    public function isValidOn(Carbon $checkIn, ?string $slot = null): bool
    {
        if (! $this->isValid()) return false;

        $date = $checkIn->copy()->startOfDay();

        if ($this->start_date && $date->lt($this->start_date->copy()->startOfDay())) return false;
        if ($this->expiry_date && $date->gt($this->expiry_date->copy()->startOfDay())) return false;

        if ($slot !== null && $this->applies_to !== 'all' && $this->applies_to !== $slot) return false;

        return true;
    }

    // ── Computation ────────────────────────────────────────────────

    /**
     * Ilang piso ang babawas sa ibinigay na halaga. Hindi kailanman
     * hihigit sa halaga mismo — walang booking na magiging negatibo.
     */
    public function calculateDiscount(float $amount): float
    {
        if ($amount <= 0) return 0.0;

        $off = $this->type === 'percentage'
            ? $amount * (min(100, (float) $this->value) / 100)
            : (float) $this->value;

        return round(min($off, $amount), 2);
    }

    /**
     * Ang PINAKAMAGANDANG promo para sa isang check-in/slot, o null.
     *
     * Kapag may dalawang magkapatong na promo, ang MAS MALAKING bawas sa
     * piso ang nananalo (hindi ang mas mataas na porsyento — magkaiba
     * iyon kapag may halong `fixed`). Deterministiko ito: pareho ang
     * mananalo tuwing tatawagin, kaya hindi puwedeng magkaiba ang
     * presyong nakita sa preview at ang aktwal na sinisingil.
     */
    public static function bestFor(float $baseAmount, Carbon $checkIn, ?string $slot = null): ?self
    {
        if ($baseAmount <= 0) return null;

        $candidates = static::query()
            ->where('is_active', 1)
            ->get()
            ->filter(fn (self $d) => $d->isValidOn($checkIn, $slot));

        if ($candidates->isEmpty()) return null;

        return $candidates
            ->sortByDesc(fn (self $d) => [$d->calculateDiscount($baseAmount), $d->id])
            ->first();
    }

    /**
     * Mga promong dapat i-advertise sa landing page — public, active,
     * hindi pa ubos, at hindi pa tapos ang window.
     *
     * KASAMA rito ang mga `scheduled` pa lang (hindi pa dumarating ang
     * `start_date`). Sinasadya iyon: ang isang promong sasaklaw sa mga
     * stay sa Setyembre ay dapat makita ngayong Agosto — iyon mismo ang
     * panahong makakapag-impluwensya pa ito ng pag-book. Kung sa
     * unang araw pa lang ng window ito lalabas, na-advertise lang ito
     * habang tumatakbo na, kung kailan huli na para sa sinumang
     * nagpaplano nang maaga.
     *
     * Ang `expiry_date` lang ang tunay na hangganan dito: kapag lampas
     * na, wala nang stay na maaaring maabot pa nito.
     *
     * Tandaan: ito ay tungkol sa PAG-ANUNSYO. Ang pagpepresyo ay dumadaan
     * pa rin sa isValidOn(), na sinusuri ang aktwal na check-in date —
     * kaya ang isang paparating na promo ay ipinapakita rito nang maaga
     * pero hindi pa rin nagbabawas sa isang stay bago ang start_date.
     */
    public static function publicActive()
    {
        return static::query()
            ->where('is_active', 1)
            ->where('is_public', 1)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', Carbon::today()))
            // Tumatakbo na muna bago ang paparating pa lang; sa loob ng
            // bawat pangkat, ang pinakamalapit nang magsimula ang una.
            ->orderByRaw('CASE WHEN start_date IS NULL OR start_date <= ? THEN 0 ELSE 1 END', [Carbon::today()->toDateString()])
            ->orderByRaw('start_date IS NULL DESC')
            ->orderBy('start_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (self $d) => ! $d->usage_limit || $d->used_count < $d->usage_limit)
            ->values();
    }

    /** Nakatakda pa lang ba — hindi pa dumarating ang start_date? */
    public function isUpcoming(): bool
    {
        return $this->start_date && $this->start_date->gt(Carbon::today());
    }

    // ── Display helpers ────────────────────────────────────────────

    /** Hal. "20% OFF" o "₱500 OFF" */
    public function getValueLabelAttribute(): string
    {
        return $this->type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '% OFF'
            : '₱' . number_format((float) $this->value, 0) . ' OFF';
    }

    public function getSlotLabelAttribute(): string
    {
        return match ($this->applies_to) {
            'day'   => Booking::SLOTS['day']['label'] ?? 'Day slot',
            'night' => Booking::SLOTS['night']['label'] ?? 'Night slot',
            default => 'Any slot',
        };
    }

    /**
     * Estado para sa admin list: scheduled / running / expired /
     * used_up / inactive.
     */
    public function getStateAttribute(): string
    {
        if (! $this->is_active) return 'inactive';
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return 'used_up';
        if ($this->expiry_date && $this->expiry_date->lt(Carbon::today())) return 'expired';
        if ($this->start_date && $this->start_date->gt(Carbon::today())) return 'scheduled';
        return 'running';
    }

    public function getStateBadgeAttribute(): string
    {
        return match ($this->state) {
            'running'   => '<span class="badge bg-success">Running</span>',
            'scheduled' => '<span class="badge bg-info">Scheduled</span>',
            'expired'   => '<span class="badge bg-secondary">Expired</span>',
            'used_up'   => '<span class="badge bg-warning text-dark">Fully used</span>',
            default     => '<span class="badge bg-secondary">Inactive</span>',
        };
    }

    public function getWindowLabelAttribute(): string
    {
        $from = $this->start_date ? $this->start_date->format('M d, Y') : 'Anytime';
        $to   = $this->expiry_date ? $this->expiry_date->format('M d, Y') : 'no end date';

        return $from . ' — ' . $to;
    }
}
