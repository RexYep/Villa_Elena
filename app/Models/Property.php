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
        // Presyo ng 22-Hours na slot. NULL = hindi pa ipinepresyo, kaya
        // hindi pa inaalok ang slot (tingnan ang isSlotPriced()).
        'base_price_22h',
        'weekend_price_22h',
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
        'base_price_22h' => 'decimal:2',
        'weekend_price_22h' => 'decimal:2',
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
 
/**
 * ORDER IS EXPLICIT HERE ON PURPOSE.
 *
 * This relation had no ordering at all, so the gallery's order was whatever
 * MySQL happened to return — undefined by definition, and in practice
 * primary-key order. `property_images.sort_order` was written on every upload
 * (`$existingCount + $index`) and then read by NOTHING: not this relation, not
 * the portal gallery, not the admin edit page.
 *
 * That was surfaced by the new `deleted_property_image` audit entry, which
 * reported image #37 being promoted to primary when the image next in display
 * order was a different one. The audit row was correct; the promotion was
 * picking the lowest id via an unordered `first()`.
 *
 * So the column is given effect in the one place every caller goes through.
 * `id` is the tiebreak because `sort_order` is not unique and has gaps — a
 * deleted image leaves its index behind, so property #14's only image carries
 * sort_order 1, not 0.
 */
public function images()
{
    return $this->hasMany(\App\Models\PropertyImage::class)
        ->orderBy('sort_order')
        ->orderBy('id');
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
        // Check if a pricing rule applies for this date.
        // `whereDate()` para sa parehong dahilan na nakasulat sa
        // getPackagePrice() sa ibaba — ang isang tumatawag na may datetime
        // (hindi hatinggabi) ay hindi makakatama sa huling araw ng rule.
        $rule = $this->pricingRules()
            ->where('is_active', 1)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();

        if ($rule && $rule->type !== 'percentage') {
            return $rule->price;
        }

        // Check if it's a weekend (Friday=5, Saturday=6)
        $normal = ($date->isSaturday() || $date->isSunday())
            ? ($this->weekend_price ?? $this->base_price)
            : $this->base_price;

        // Percentage: sa ibabaw ng karaniwang presyo ng petsa — kapareho ng
        // getPackagePrice() sa ibaba (v7.58).
        return $rule
            ? round($normal * (1 + $rule->price / 100), 2)
            : $normal;
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
    public function getPackagePrice(\Carbon\Carbon $checkin, ?string $slot = null): float
    {
        // Aling pares ng column ang babasahin. Ang Day at Night ay
        // pareho pa rin ang presyo — ang petsa lang ng check-in ang
        // nagpapasya — kaya walang nagbabago sa kanila. Ang 22-Hours ay
        // may sariling pares dahil sumasakop ito sa dalawang slot;
        // tingnan ang 2026_09_28_110000_add_22h_pricing_to_properties_table.
        [$baseCol, $weekendCol] = static::priceColumnsFor($slot);

        $base    = $this->{$baseCol};
        $weekend = $this->{$weekendCol};

        // Hindi pa ipinepresyo. Ang mga tumatawag ay dapat nasala na ito
        // sa pamamagitan ng isSlotPriced() / quoteFor(); NULL-safe lang
        // ito para hindi maging 0.00 ang isang hindi-inaalok na slot —
        // ang 0.00 ay LIBRENG booking, at nangyari na iyon (tingnan ang
        // PortalController::assertBookableListing()).
        if ($base === null) {
            return 0.0;
        }
        // Special date-range pricing rule (hal. holiday override) — mananatili
        // itong pinaka-priority kung meron.
        //
        // ⚠️ `whereDate()`, HINDI tuwirang paghahambing sa `$checkin`.
        //
        // Ang `$checkin` dito ay laging buong DATETIME (8:00 AM o 7:00 PM —
        // tingnan ang Booking::SLOTS), samantalang ang `end_date` ay DATE.
        // Sa `end_date >= '2026-10-17 19:00:00'`, ginagawang hatinggabi ng
        // MySQL ang petsa, kaya 2026-10-17 00:00 >= 2026-10-17 19:00 ay
        // MALI — hindi kailanman tumatama ang rule sa HULING araw nito.
        // Dahil 8AM o 7PM ang lahat ng check-in, ang isang ISANG-ARAW na
        // rule (hal. "Christmas Day rate") ay walang bisa kailanman, at ang
        // huling araw ng anumang saklaw ay tahimik na bumabalik sa
        // karaniwang presyo. Napansin ito sa v6.4 nang gumawa ng pricing
        // rule ang prescriptive engine at hindi nagbago ang quote.
        $rule = $this->pricingRules()
            ->where('is_active', 1)
            ->whereDate('start_date', '<=', $checkin->toDateString())
            ->whereDate('end_date', '>=', $checkin->toDateString())
            ->first();

        // Fixed rule: absolute — iyon ang sinabi ng admin na presyo para sa
        // petsang iyon, anuman ang araw o slot.
        if ($rule && $rule->type !== 'percentage') {
            return $rule->price;
        }

        $dayOfWeek = $checkin->dayOfWeek; // 0=Sunday ... 6=Saturday

        // Ang KARANIWANG presyo ng petsa at slot na ito:
        //  - Linggo: mahal pa hanggang 6PM, tapos mura na
        //  - Biyernes (5) at Sabado (6): peak rate buong araw
        //  - Lunes–Huwebes: regular rate
        $normal = match (true) {
            $dayOfWeek === 0 => $checkin->format('H:i') >= '18:00' ? $base : ($weekend ?? $base),
            in_array($dayOfWeek, [5, 6]) => $weekend ?? $base,
            default => $base,
        };

        // ⚠️ Percentage rule: porsyento sa ibabaw ng KARANIWANG presyo sa
        // itaas — HINDI ng `base_price`, at hindi rin ng base ng slot lang
        // (v7.58).
        //
        // Hanggang v7.57 ay `$base × (1 + %)` ito, kaya ang "+10%" sa isang
        // Sabado ay 4,000 × 1.10 = ₱4,400 — mas MABABA pa sa ₱6,000 na
        // karaniwang sinisingil. Ang rule na ang pangalan ay pagtataas ay
        // tahimik na nagpapamura tuwing weekend. Ngayon, ang "+10%" ay
        // ₱4,400 sa Martes, ₱6,600 sa Sabado, at 10% rin sa 22-oras —
        // iyon mismo ang sinasabi ng label, sa bawat petsa at slot.
        //
        // Ito ang inaasahan ng PeakRateAdvisor: iisang `pricing_rules` row
        // para sa saklaw na may halong weekday at weekend. Huwag itong
        // ibalik sa `$base`.
        if ($rule) {
            return round($normal * (1 + $rule->price / 100), 2);
        }

        return $normal;
    }

    /**
     * Aling `properties` column ang may presyo ng slot na ito.
     *
     * @return array{0: string, 1: string} [base column, weekend column]
     */
    public static function priceColumnsFor(?string $slot = null): array
    {
        // Mula sa depinisyon ng slot, hindi `$slot === 'stay22'`: sa
        // paghahambing na iyon, ang ikalawang 22-oras na slot (`day22`) ay
        // tahimik na babagsak sa presyo ng Day/Night — kalahati ng dapat.
        return (\App\Models\Booking::SLOTS[$slot]['rate'] ?? null) === '22h'
            ? ['base_price_22h', 'weekend_price_22h']
            : ['base_price', 'weekend_price'];
    }

    /**
     * May presyo na ba ang slot na ito — ibig sabihin, maaari na bang
     * ipagbili?
     *
     * Ang `base` lang ang hinihingi. Ang `weekend` ay opsyonal at
     * bumabagsak sa `base` (ganoon na ito dati para sa Day/Night), kaya
     * ang isang admin na isang presyo lang ang itinakda ay may gumaganang
     * slot pa rin — hindi isang bahagyang naka-configure na slot na
     * tahimik na nagbebenta ng mali sa katapusan ng linggo.
     */
    public function isSlotPriced(?string $slot = null): bool
    {
        [$baseCol] = static::priceColumnsFor($slot);

        return $this->{$baseCol} !== null && (float) $this->{$baseCol} > 0;
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
     * Ang `$guest` ay ang taong magbabayad, kung kilala. Kailangan ito
     * ng mga promong `guest_scope = 'returning'` — walang ibang paraan
     * para malaman kung regular na customer ang nasa harap natin.
     * Opsyonal ito dahil may mga tunay na kontekstong walang guest:
     * anonymous na price preview, ang date×slot na list-price grid ng
     * staff, ang prescriptive forecasting, at ang walk-in na BAGO pa
     * lang ang guest (na likas namang hindi returning). Sa lahat ng
     * iyon, LIST PRICE ang ibinibigay — tingnan ang
     * Discount::isEligibleGuest() para sa dahilan kung bakit iyon ang
     * tamang direksyon ng pagpalya.
     *
     * @return array{base: float, discount: float, total: float, promo: ?\App\Models\Discount}
     */
    public function quoteFor(\Carbon\Carbon $checkin, ?string $slot = null, ?User $guest = null): array
    {
        // FAIL-CLOSED sa isang slot na wala pang presyo.
        //
        // Ang 22-Hours ay nasa Booking::SLOTS na — kailangan iyon para
        // mabasa ng slotDateTimes() at ng calendar ang mga umiiral nang
        // booking — pero NULL pa ang presyo nito hangga't hindi sumasagot
        // ang may-ari. Walang form na nag-aalok nito (tingnan ang
        // Booking::bookableSlotKeys()), kaya ito ang huling hadlang laban
        // sa isang ginawa-gawang POST.
        //
        // 422 at hindi pagbalik ng 0.00, dahil ang 0.00 ay isang tunay at
        // slot-holding na booking na LIBRE. Nangyari na iyon: ang mga
        // `type = room` na row ay may `base_price = 0.00`, at
        // `POST /book/{room}` ay gumawa ng ₱0 na booking hanggang sa
        // isinara ito ng assertBookableListing() ng PortalController.
        // Kapareho ang hugis; kapareho ang tugon.
        abort_unless($this->isSlotPriced($slot), 422, 'That booking slot is not available.');

        // At TUMATANGGI rin sa isang slot na hindi inaalok sa PETSANG ITO.
        //
        // Ang 22-Hours ay inaalok lang sa mga petsang pinili ng may-ari
        // (`slot_windows`), at sa mga petsang iyon ay ITO LANG ang
        // inaalok — kaya ang Day/Night ay tinatanggihan doon, at ang
        // 22-Hours ay tinatanggihan sa lahat ng iba. Dito ito tinitingnan
        // dahil ito lang ang lugar na may PAREHONG petsa at slot at
        // dinaraanan ng lahat ng pitong booking path.
        //
        // Ligtas na maging mahigpit dito: bawat tumatawag ng quoteFor()
        // ay nagpepresyo ng booking na gagawin o ililipat pa lang
        // (`quote()` preview, `store()`, portal submit, walk-in,
        // reschedule) — walang tumatawag nito para muling kuwentahin ang
        // isang umiiral nang booking, kaya hindi puwedeng masira ng
        // isang bagong window ang pag-edit ng naunang booking.
        if ($slot !== null) {
            abort_unless(
                in_array($slot, \App\Models\Booking::slotsOfferedOn($checkin, $this), true),
                422,
                'That booking slot is not offered on that date.'
            );
        }

        $base  = round((float) $this->getPackagePrice($checkin, $slot), 2);
        $promo = Discount::bestFor($base, $checkin, $slot, $guest);
        $off   = $promo ? $promo->calculateDiscount($base) : 0.0;

        return [
            'base'     => $base,
            'discount' => $off,
            'total'    => round($base - $off, 2),
            'promo'    => $promo,
        ];
    }
}