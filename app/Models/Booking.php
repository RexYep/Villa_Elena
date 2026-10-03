<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $booking_ref
 * @property int $user_id
 * @property int $property_id
 * @property \Illuminate\Support\Carbon $check_in_date
 * @property string $check_in_time
 * @property \Illuminate\Support\Carbon|null $actual_check_in
 * @property \Illuminate\Support\Carbon $check_out_date
 * @property string $check_out_time
 * @property string|null $slot_hold
 * @property string|null $slot
 * @property \Illuminate\Support\Carbon|null $actual_check_out
 * @property int $num_nights
 * @property int $num_guests
 * @property numeric $base_amount
 * @property numeric $extras_amount
 * @property numeric $discount_amount
 * @property int|null $discount_id
 * @property numeric $total_amount
 * @property numeric $amount_paid
 * @property numeric $balance_due
 * @property string $status
 * @property string $payment_status
 * @property string $source
 * @property int $reschedule_count
 * @property string|null $paymongo_session_id
 * @property string|null $paymongo_payment_type
 * @property string|null $special_requests
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $cancelled_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Discount|null $discount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BookingExtra> $extras
 * @property-read int|null $extras_count
 * @property-read string $payment_status_badge
 * @property-read string $payment_status_class
 * @property-read string $payment_status_label
 * @property-read string $status_badge
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HousekeepingTask> $housekeepingTasks
 * @property-read int|null $housekeeping_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Property $property
 * @property-read \App\Models\Review|null $review
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereActualCheckIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereActualCheckOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBalanceDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBaseAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBookingRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCancelledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCheckInDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCheckInTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCheckOutDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCheckOutTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereDiscountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereExtrasAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereNumGuests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereNumNights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePaymongoPaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePaymongoSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereRescheduleCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereSpecialRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereSlotHold($value)
 * @property \Illuminate\Support\Carbon|null $overpayment_notified_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereOverpaymentNotifiedAt($value)
 * @mixin \Eloquent
 */
class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_ref',
        'user_id',
        'property_id',
        'check_in_date',
        'check_in_time',
        'actual_check_in',
        'check_out_date',
        'check_out_time',
        'actual_check_out',
        'num_nights',
        'num_guests',
        'base_amount',
        'extras_amount',
        'discount_amount',
        'discount_id',
        'total_amount',
        'amount_paid',
        'balance_due',
        'status',
        'payment_status',
        'paymongo_session_id',
        'paymongo_payment_type',    
        'source',
        'reschedule_count',
        'special_requests',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
    ];

    protected $casts = [
        'check_in_date'   => 'date',
        'check_out_date'  => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out'=> 'datetime',
        'cancelled_at'    => 'datetime',
        // Hindi ito fillable — kagaya ng slot_hold, ang model lang ang
        // nagtatakda nito (flagOverpayment()).
        'overpayment_notified_at' => 'datetime',
        'base_amount'    => 'decimal:2',
        'extras_amount'  => 'decimal:2',
        'discount_amount'=> 'decimal:2',
        'total_amount'   => 'decimal:2',
        'amount_paid'    => 'decimal:2',
        'balance_due'    => 'decimal:2',
    ];

    // ── Auto-generate booking reference ───────────────────────────

    protected static function booted(): void
    {
        static::creating(function ($booking) {
            if (empty($booking->booking_ref)) {
                $booking->booking_ref = 'VE-' . strtoupper(Str::random(8));
            }
        });

        // `slot_hold` ang column na binabantayan ng UNIQUE index na
        // pumipigil sa double-booking sa antas ng database (tingnan ang
        // 2026_09_08_100000_add_slot_hold_to_bookings_table). Hindi ito
        // fillable at hindi ito dapat isulat ng kahit sinong controller
        // — dito lang ito kinukuwenta, para hindi kailanman maghiwalay
        // ang halaga nito sa aktuwal na petsa/oras/status ng booking.
        static::saving(function ($booking) {
            if (! static::slotHoldColumnExists()) {
                return;
            }

            $hold = $booking->computeSlotHold();

            // Ang isang EXISTING row na wala nang `slot_hold` ay hindi
            // basta-basta puwedeng bumawi ng slot nito.
            //
            // Dalawang paraan para mapunta sa estadong ito: kinansela
            // ito (tama lang na bumalik ang hawak kung bakante pa), o
            // isa ito sa dalawang naunang double-booking na sinadyang
            // hindi isinama ng migration sa index (VE-YHLBMLUU). Kung
            // walang tsekeng ito, ang PINAKASIMPLENG pag-edit sa
            // ganoong row — pagtatala ng refund, pagpapalit ng status,
            // pag-aayos ng bilang ng bisita — ay 500 na duplicate-key
            // error, at ang mga row na iyon mismo ang kailangang
            // ayusin ng admin.
            //
            // Ang query ay tumatakbo LANG sa makitid na kasong ito.
            // Ang bagong INSERT ay laging kumukuha ng totoong halaga
            // ($booking->exists === false dito), kaya buo pa rin ang
            // proteksiyon ng index laban sa bagong double-booking.
            if ($hold !== null && $booking->exists && $booking->getOriginal('slot_hold') === null) {
                $taken = static::withTrashed()
                    ->where('slot_hold', $hold)
                    ->whereKeyNot($booking->getKey())
                    ->exists();

                if ($taken) {
                    $hold = null;
                }
            }

            $booking->slot_hold = $hold;
        });

        // `slot` — ang pangalan ng slot na binook, itinatala bilang datos.
        //
        // Gaya ng `slot_hold`, hindi ito fillable at DITO LANG ito
        // isinusulat: walang controller ang nagtatakda nito. Hindi na
        // kailangang baguhin ang pitong daanang gumagawa o naglilipat ng
        // booking — lahat sila ay dumadaan sa slotDateTimes() para sa
        // oras, kaya ang mismong oras na iyon ang nagsasabi ng slot.
        //
        // ANG MAHALAGANG PATAKARAN: kapag may nakaimbak nang halaga at
        // HINDI na tugma ang oras sa alinmang slot, PINAPANATILI ito —
        // hindi ito nini-null.
        //
        // Iyon ang buong dahilan ng column na ito. Ang `extendStay()` ay
        // nagbabago ng check_out_time ng isang naka-check-in nang bisita
        // at hindi ng check_in_time, kaya pagkatapos noon ay wala nang
        // slot na tutugma sa oras. Kung nini-null ito, mawawala ang
        // pangalan ng slot sa calendar sa eksaktong sandaling
        // pinahahaba ang stay — habang ang katotohanan ay alam pa naman:
        // Night itong binook, at Night pa rin.
        static::saving(function ($booking) {
            if (! static::hasOptionalColumn('slot')) {
                return;
            }

            // Ang exactSlotKey() ay sumasagot LAMANG kapag eksaktong
            // tugma ang oras, kaya ang isang pinahabang stay (o isang
            // legacy na 2:00 PM na row) ay walang isinasagot dito.
            if ($slot = $booking->exactSlotKey()) {
                $booking->slot = $slot;
            }
        });

        // Ang SoftDeletes::runSoftDelete() ay direktang UPDATE sa query
        // builder — hindi ito dumadaan sa save(), kaya hindi tumatakbo
        // ang saving() sa itaas. Kung hindi ito lilinisin dito,
        // haharangan ng isang soft-deleted na booking ang slot nito
        // habang-buhay.
        static::deleted(function ($booking) {
            if ($booking->isForceDeleting() || ! static::slotHoldColumnExists()) {
                return;
            }

            static::withTrashed()->whereKey($booking->getKey())->update(['slot_hold' => null]);
            $booking->slot_hold = null;
            $booking->syncOriginalAttribute('slot_hold');
        });

        // Live na KPI ng admin dashboard (Pending / Total Bookings).
        //
        // Dito sa model, hindi sa mga controller: ang Pending Bookings ay
        // dating nababawasan LANG kapag binago ng admin ang status sa
        // booking page, dahil iyon ang tanging lugar na nagpapadala.
        // Ang kumpirmasyon dahil sa bayad, check-in/out sa front desk,
        // at awtomatikong pagkansela ay tahimik na nagbabago ng status —
        // kaya nanatili ang "1" hanggang sa refresh. Sinasalo rito ang
        // bawat daanan, kasama ang mga hindi pa naisusulat.
        //
        // Ang touch() ay iisang broadcast kada request anuman ang bilang
        // ng save, kaya ligtas dito.
        static::saved(function ($booking) {
            // `source` para sa Booking Sources chart (v7.9) — ang pag-edit
            // ng pinagmulan ay nagbabago sa donut nang walang anumang status.
            if ($booking->wasRecentlyCreated || $booking->wasChanged(['status', 'source'])) {
                \App\Services\DashboardStats::touch();
            }

            // Live na staff Availability grid. Ang status ay binabantayan
            // dahil ang kinanselang booking ay nagpapalaya ng slot; ang mga
            // petsa/oras dahil ang reschedule at ang calendar drag-move ay
            // naglilipat ng slot nang walang anumang status.
            if ($booking->wasRecentlyCreated || $booking->wasChanged([
                'status', 'property_id',
                'check_in_date', 'check_in_time', 'check_out_date', 'check_out_time',
            ])) {
                static::touchAvailability();
            }
        });

        static::deleted(function () {
            \App\Services\DashboardStats::touch();
            static::touchAvailability();
        });

        static::restored(function () {
            \App\Services\DashboardStats::touch();
            static::touchAvailability();
        });
    }

    /**
     * Ipaalam sa staff Availability page na kunin muli ang grid.
     * Iisa kada request — tingnan ang BroadcastOnce.
     */
    public static function touchAvailability(): void
    {
        \App\Services\BroadcastOnce::dispatch(
            'staff-availability',
            fn () => new \App\Events\StaffAvailabilityChanged
        );
    }

    /**
     * Umiiral na ba ang `slot_hold` column?
     *
     * Hindi atomic ang isang deploy: sa Render, tumatakbo lang ang
     * migrations kapag naka-set ang `RUN_MIGRATIONS=true` para sa deploy
     * na iyon (tingnan ang §15). Kung tumapak ang code na ito bago pa
     * ang migration, ang hook sa itaas ay magsusulat ng column na wala
     * pa — at BAWAT paggawa ng booking ay 500. Kaya kailangang kayanin
     * ng code ang parehong hugis ng schema habang naglalabasan ang
     * deploy.
     *
     * Isang beses lang ito tinatanong kada proseso. Ibig sabihin, ang
     * isang matagal-nang-buhay na worker na nagsimula bago ang
     * migration ay mananatiling nakakita ng "wala" hanggang mag-restart
     * — tanggap iyon: pagkatapos ng deploy, nagre-restart naman ang
     * mga proseso, at hindi nawawala ang tunay na proteksiyon
     * (nananatili ang lock ng reserveSlot()) — ang backstop lang ang
     * pansamantalang wala.
     */
    /** @var array<string, bool> Sagot kada column, isang beses kada proseso. */
    protected static array $optionalColumnExists = [];

    /**
     * Umiiral na ba ang isang column na idinagdag ng migration na
     * posibleng hindi pa tumakbo? Iisang kopya ng dahilan sa itaas,
     * ginagamit ng `slot_hold` at ng `slot` — pareho silang isinusulat ng
     * model hook sa bawat save, kaya pareho nilang kailangang kayanin ang
     * dalawang hugis ng schema habang naglalabasan ang deploy.
     */
    protected static function hasOptionalColumn(string $column): bool
    {
        if (! array_key_exists($column, static::$optionalColumnExists)) {
            try {
                static::$optionalColumnExists[$column] = \Illuminate\Support\Facades\Schema::hasColumn(
                    (new static)->getTable(),
                    $column
                );
            } catch (\Throwable $e) {
                static::$optionalColumnExists[$column] = false;
            }
        }

        return static::$optionalColumnExists[$column];
    }

    protected static function slotHoldColumnExists(): bool
    {
        return static::hasOptionalColumn('slot_hold');
    }

    /**
     * Ang halaga ng `slot_hold` para sa booking na ito: isang string na
     * kumakatawan sa "hawak ko ang slot na ito" —
     * `"{property_id}:{check_in_date}:{check_in_time}"`.
     *
     * NULL ito kapag hindi na hawak ng booking ang slot (cancelled,
     * no_show, o soft-deleted). Mahalaga ang NULL: pinapayagan ng MySQL
     * ang maraming NULL sa isang UNIQUE index, kaya libre nang
     * ma-rebook ang slot ng isang kanseladong booking — habang
     * imposible pa ring magkaroon ng dalawang BUHAY na booking sa
     * iisang slot.
     *
     * Sinasadyang tugma ito sa exclusion list ng hasConflict()
     * (`cancelled`/`no_show` lang) — kapag may binago sa isa, tingnan
     * ang isa, kung hindi ay tatanggihan ng index ang isang booking na
     * sinasabi naman ng hasConflict() na puwede.
     */
    public function computeSlotHold(): ?string
    {
        if (in_array($this->status, ['cancelled', 'no_show'], true) || $this->trashed()) {
            return null;
        }

        if (empty($this->property_id) || empty($this->check_in_date) || empty($this->check_in_time)) {
            return null;
        }

        return $this->property_id
            . ':' . $this->check_in_date->format('Y-m-d')
            . ':' . \Carbon\Carbon::parse($this->check_in_time)->format('H:i:s');
    }

    /**
     * Ilang minuto bago ituring na "abandoned" ang isang unpaid "pending"
     * booking — kontrolado sa Admin → Settings → Booking Rules ("Booking
     * Hold"), 60 minuto ang default. Ginagamit ng hasConflict() (kung
     * kailan tumitigil mag-block ng slot ang unpaid hold), ng
     * hasActivePendingBooking() (anti-spam check), at ng
     * AutoCheckInOutBookings::cancelStalePendingBookings() (formal
     * auto-cancel) — iisang setting lang ang pinagbabatayan ng tatlo.
     */
    public static function pendingHoldMinutes(): int
    {
        return (int) Setting::get('booking_hold_minutes', 60);
    }

    // ── Alin ang puwedeng ilipat ng petsa ───────────────────────────
    /**
     * Ang mga status LAMANG na makatuwirang ilipat sa ibang petsa.
     *
     * Ang admin calendar ay `editable: true` sa kabuuan at ang feed nito ay
     * `cancelled` lang ang itinatanggi, kaya nadadala ang bawat `checked_out`
     * na booking — 41 sa 55 sa production data. Dalawa ang tunay na pinsala
     * niyon, hindi lang kalat:
     *
     *   1. Binabago nito ang kasaysayan. Isang stay na naganap noong Ago 8 ay
     *      itatala nang ibang petsa, at `check_in_date` ang binabasa ng
     *      revenue at occupancy reporting.
     *   2. Umaagaw ito ng slot sa hinaharap. `cancelled` at `no_show` lang ang
     *      hindi kasama sa hasConflict(), kaya HUMAHAWAK PA RIN ng slot ang
     *      isang `checked_out`. I-drag ang tapos nang stay sa bakanteng petsa
     *      sa hinaharap at haharangan nito ang petsang iyon laban sa totoong
     *      booking — ipinapatupad hanggang sa `slot_hold` UNIQUE index.
     *
     * Wala ang `checked_in` dito nang sinasadya: nasa villa na mismo ang
     * bisita, at ang pagpapalit ng kaniyang petsa ay trabaho ng extendStay(),
     * na siyang itinakdang eksepsiyon sa free-choice na oras.
     *
     * IISANG listahan ito, ginagamit ng feed (para hindi na ma-drag) at ng
     * moveBooking() (para tanggihan ang direktang POST). Ang pagtatago ng
     * kontrol ay hindi pagbabawal, kaya kailangan ang dalawa — at kailangang
     * iisa ang pinagmumulan nila, dahil ang dalawang kopya ng listahan ng
     * status ay tiyak na maghihiwalay.
     */
    public const MOVABLE_STATUSES = ['pending', 'confirmed'];

    public function canBeMoved(): bool
    {
        return in_array($this->status, static::MOVABLE_STATUSES, true);
    }

    // ── Fixed Booking Slots ──────────────────────────────────────────
    // Fixed na package slot ang pinipili ng guest — hindi free-choice na
    // oras. Ang gap sa pagitan ng Day at Night (5:00 PM checkout → 7:00 PM
    // check-in, at 6:00 AM checkout → 8:00 AM check-in) ang siya nang
    // nagsisilbing cleaning buffer, kaya walang hiwalay na buffer na
    // kailangan pang i-enforce sa hasConflict().
    //
    // ⚠️ HINDI NA MAGKAHIWALAY ANG MGA SLOT (v7.47).
    //
    // Dalawa lang ang slot noon, at hindi sila kailanman nagpapatong: ang
    // Day ng isang petsa at ang Night ng parehong petsa ay dalawang
    // magkaibang bagay. Ang 22-Hours ay PUMAPATONG sa dalawa:
    //
    //   Biyernes 7PM ────────────── 22-Hours ───────────── Sabado 5PM
    //   Biyernes 7PM ─ Night ─ Sabado 6AM
    //                          Sabado 8AM ─ Day ─ Sabado 5PM
    //
    // Kaya ang isang 22-oras na booking sa Biyernes ay humahawak DIN sa
    // Night ng Biyernes AT sa Day ng Sabado, at kabaligtaran: kapag may
    // booking sa Day ng Sabado, sarado ang 22-Hours ng Biyernes. Walang
    // kailangang baguhin para tumama ito — datetime-overlap ang
    // hasConflict() at ang slotAvailabilityMap() (na sumasaklaw sa
    // `check_in_date - 1` nang mismong dahilan na ito), hindi
    // paghahambing ng slot key. Huwag ipapalit iyon sa pagsusuring
    // "parehong slot key ba".
    //
    // TANDAAN: ang `slot_hold` UNIQUE index ay `{property}:{date}:{time}`,
    // at 19:00 ang check-in ng Night AT ng 22-Hours — kaya nahuhuli nito
    // ang Night-vs-22-Hours sa isang petsa (tama lang, nagkakabanggaan
    // sila), pero HINDI ang 22-Hours-vs-Day-kinabukasan: iba ang petsa,
    // iba ang oras. Ang lock ng reserveSlot() ang humahawak doon. Mas
    // manipis ang backstop ngayon kaysa noong dalawa ang slot.

    // Ang `name` at `times` ay para sa mga radio card, na ipinapakita ang
    // pangalan nang bold at ang oras nang maliit. INVARIANT:
    // `label === "{name} ({times})"`. Nakalista ang tatlo sa halip na
    // pagbuo-buuin sa runtime dahil nasa isang tanawin lang silang lahat
    // dito; kapag binago ang isa, tingnan ang katabi.
    public const SLOTS = [
        'day' => [
            'label'     => 'Day (8:00 AM – 5:00 PM)',
            'name'      => 'Day',
            'times'     => '8:00 AM – 5:00 PM',
            'check_in'  => '08:00',
            'check_out' => '17:00',
            'overnight' => false,
        ],
        'night' => [
            'label'     => 'Night (7:00 PM – 6:00 AM)',
            'name'      => 'Night',
            'times'     => '7:00 PM – 6:00 AM',
            'check_in'  => '19:00',
            'check_out' => '06:00',
            'overnight' => true,
        ],
        // Pinayagan ng may-ari kasama ng Day at Night (tingnan ang
        // booking policy sa v7.47). Kapareho ng check-in ng Night — iyon
        // ang dahilan kung bakit dalawang field ang inihahambing ng
        // exactSlotKey() at kung bakit may `bookings.slot` na column.
        'stay22' => [
            'label'     => '22 Hours (7:00 PM – 5:00 PM next day)',
            'name'      => '22 Hours',
            'times'     => '7:00 PM – 5:00 PM next day',
            'check_in'  => '19:00',
            'check_out' => '17:00',
            'overnight' => true,
            // ⚠️ Hindi ito inaalok araw-araw. Pinipili ng may-ari kung
            // aling petsa (`slot_windows`), at sa petsang may window ay
            // ITO LANG ang inaalok — nakatago ang Day at Night. Tingnan
            // ang slotsOfferedOn(). Ang `window_required` ang nagbubukod
            // sa "walang row = hindi kailanman inaalok" (dito) at sa
            // "walang row = laging inaalok" (Day/Night).
            'window_required' => true,
        ],
    ];

    /**
     * Kailangan ba ng slot na ito ng `slot_windows` na row bago maalok?
     */
    public static function slotRequiresWindow(string $slot): bool
    {
        return (bool) (static::SLOTS[$slot]['window_required'] ?? false);
    }

    /** Ang mga slot na pinipili ng may-ari kada petsa. */
    public static function windowedSlotKeys(): array
    {
        return array_values(array_filter(
            array_keys(static::SLOTS),
            fn (string $slot) => static::slotRequiresWindow($slot)
        ));
    }

    /**
     * Ang mga slot na KAILANMAN maipagbibili — mayroong presyo.
     *
     * ⚠️ HINDI ito ang tanong na "ano ang maaaring i-book sa petsang
     * ito" — `slotsOfferedOn()` ang sumasagot niyon, at IYON ang dapat
     * gamitin ng mga form, grid, dropdown at pampublikong pahina.
     *
     * Tatlong magkaibang tanong, tatlong method:
     *
     *   SLOTS                 ano ang mga DEPINISYON (laging tatlo)
     *   bookableSlotKeys()    ano ang may PRESYO
     *   slotsOfferedOn($date) ano ang inaalok SA PETSANG ITO
     *
     * Kailangang manatili sa SLOTS ang lahat para makapagbasa ng umiiral
     * nang booking ang slotDateTimes(), ang slotKey() at ang calendar.
     *
     * Ito ay nananatiling kapaki-pakinabang para sa mga tanong na walang
     * petsa — hal. "may 22-oras na slot ba na nakatakda man lang?" — at
     * ito ang unang hadlang sa loob ng slotsOfferedOn() mismo.
     *
     * @return array<int, string>
     */
    public static function bookableSlotKeys(?\App\Models\Property $property = null): array
    {
        $property ??= \App\Models\Property::where('type', 'villa')->first();

        if (! $property) {
            return array_keys(static::SLOTS);
        }

        return array_values(array_filter(
            array_keys(static::SLOTS),
            fn (string $slot) => $property->isSlotPriced($slot)
        ));
    }

    /**
     * Ano ang inaalok sa ISANG PARTIKULAR na petsa. Ito ang awtoridad para
     * sa bawat form, grid, dropdown, validator at pampublikong pahina.
     *
     * Ang 22-Hours ay hindi pang-araw-araw: pinipili ng may-ari kung
     * aling petsa (`slot_windows`), at sa isang petsang may window ay
     * ITO LANG ang inaalok — nakatago ang Day at Night doon. Kaya:
     *
     *   Okt 2 (may window)  →  ['stay22']
     *   Okt 3 (walang)      →  ['day', 'night']
     *
     * Ang Okt 3 ay ordinaryong petsa kahit hawak ng 22-oras na stay ang
     * umaga nito. Hindi ito trabaho ng window: kapag NA-BOOK ang 22 oras,
     * isasara ng hasConflict() ang Day ng Okt 3 nang mag-isa. Tingnan ang
     * tala sa ibaba tungkol sa paghihiwalay ng dalawang konsepto.
     *
     * ⚠️ DALAWANG BITAG NA NAPANSIN BAGO IPADALA:
     *
     * 1. WINDOW NA WALANG PRESYO. Kung magtatago ng Day/Night ang
     *    exclusivity habang wala pang presyo ang 22 oras, ang petsa ay
     *    WALANG inaalok — patay na petsa sa pampublikong calendar. Kaya
     *    ang window ay may bisa LAMANG kapag may presyo na ang slot;
     *    kung wala, bumabalik ang petsa sa Day/Night. Kung wala ito, ang
     *    isang admin na naglalagay ng window bago ang presyo ay tahimik
     *    na nagsasara ng mga petsang iyon.
     *
     * 2. ITO AY TUNGKOL SA PAG-AALOK, HINDI SA BANGGAAN. Ang
     *    `hasConflict()` at ang `slotAvailabilityMap()` ay dapat
     *    SUMUSURI PA RIN ng LAHAT ng slot, window man o wala — kailangan
     *    pa ring markahan ng isang 22-oras na booking ang Night at ang
     *    Day ng kinabukasan bilang sarado. Ang paghahalo ng dalawang
     *    konseptong ito ay magbubukas muli ng double-booking.
     *
     * @param  \Carbon\Carbon|string  $date  Ang CHECK-IN date.
     * @return array<int, string>
     */
    public static function slotsOfferedOn($date, ?\App\Models\Property $property = null): array
    {
        $property ??= \App\Models\Property::where('type', 'villa')->first();

        if (! $property) {
            return array_keys(static::SLOTS);
        }

        $priced = static::bookableSlotKeys($property);
        $dateStr = $date instanceof \Carbon\Carbon
            ? $date->format('Y-m-d')
            : \Carbon\Carbon::parse($date)->format('Y-m-d');

        // May window ba sa petsang ito para sa isang slot na may presyo?
        // Ang unang tumama ang nananaig — at dahil iisa lang ngayon ang
        // windowed na slot, hindi pa kailangan ng panuntunan sa pag-uuna.
        foreach (static::windowedSlotKeys() as $slot) {
            if (! in_array($slot, $priced, true)) {
                continue;   // Bitag 1: walang presyo, walang bisa ang window.
            }

            $hasWindow = \App\Models\SlotWindow::query()
                ->offeredOn($property->id, $slot, $dateStr)
                ->exists();

            if ($hasWindow) {
                return [$slot];
            }
        }

        // Walang window: ang mga hindi-windowed na slot na may presyo.
        return array_values(array_filter(
            $priced,
            fn (string $slot) => ! static::slotRequiresWindow($slot)
        ));
    }

    /**
     * Ang mga petsang may window, kada windowed na slot — para sa mga
     * front end na kailangang malaman ito nang hindi tumatawag sa server
     * kada pagpalit ng petsa.
     *
     * Hugis: `['stay22' => ['2026-10-02', '2026-10-09']]`
     *
     * Ang mga slot na WALANG presyo ay hindi kasama, kaya ang JS na
     * gumagamit nito ay eksaktong katumbas ng slotsOfferedOn(): window
     * muna (at eksklusibo), at kung wala, ang mga hindi-windowed na slot.
     * Kapag ang isa ay nagsasama ng hindi-presyadong slot at ang isa ay
     * hindi, may mapipiling slot sa form na tatanggihan ng server.
     *
     * Naka-bound sa isang saklaw dahil ito ay ipinapadala sa bawat page
     * load; ang isang buong taon ay iilang dosenang string lang.
     *
     * @return array<string, array<int, string>>
     */
    public static function slotWindowDates(
        ?\App\Models\Property $property = null,
        ?\Carbon\Carbon $from = null,
        ?\Carbon\Carbon $to = null,
    ): array {
        $property ??= \App\Models\Property::where('type', 'villa')->first();

        if (! $property) {
            return [];
        }

        $from ??= today();
        $to ??= today()->copy()->addMonths(12);

        $priced = static::bookableSlotKeys($property);
        $out = [];

        foreach (static::windowedSlotKeys() as $slot) {
            if (! in_array($slot, $priced, true)) {
                continue;
            }

            $out[$slot] = \App\Models\SlotWindow::where('property_id', $property->id)
                ->where('slot', $slot)
                ->where('is_active', 1)
                ->whereBetween('check_in_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                ->orderBy('check_in_date')
                ->pluck('check_in_date')
                ->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d)
                ->all();
        }

        return $out;
    }

    /**
     * Mapa ng mga SARADONG slot kada petsa para sa isang property.
     *
     * Dating `PortalController::buildSlotAvailability()` — dalawa na ngayon
     * ang gumagamit nito (ang availability calendar ng property page at ang
     * reschedule form ng customer), kaya dito na ito nakatira: dalawang
     * kopya ng lohikang ito ang siguradong maglalayo sa isa't isa.
     *
     * Sinasadyang ginagaya nito ang eksaktong lohika ng hasConflict() —
     * parehong status filter, parehong "abandoned pending hold" exemption,
     * parehong datetime-overlap test sa pamamagitan ng slotDateTimes(), at
     * parehong $excludeBookingId. Kailangang tugma ang dalawa: kung mas
     * mahigpit ang mapa, magmumukhang sarado ang slot na tatanggapin naman
     * pala ng server; kung mas maluwag, mare-reject ang guest matapos na
     * siyang pumili. Kapag may binago sa isa, tingnan ang isa.
     *
     * @param  int|null  $excludeBookingId  Hindi hinahayaang harangan ng
     *         isang booking ang sarili nitong slot — ito ang kaso ng
     *         reschedule, kaparehong-kapareho ng hasConflict().
     * @return array<string, array<string, int>> hal. ['2026-09-10' => ['night' => 42]]
     */
    public static function slotAvailabilityMap(int $propertyId, ?int $excludeBookingId = null): array
    {
        $query = static::where('property_id', $propertyId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) {
                $q->where('status', '!=', 'pending')
                    ->orWhere('created_at', '>=', now()->subMinutes(static::pendingHoldMinutes()));
            })
            ->where('check_out_date', '>=', today());

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        $bookings = $query->get(['id', 'check_in_date', 'check_in_time', 'check_out_date', 'check_out_time']);

        if ($bookings->isEmpty()) {
            return [];
        }

        // Tunay na check-in/check-out datetime ng bawat booking. Hindi
        // puwedeng ang slot key lang ang basehan: puwedeng na-extend ng
        // Admin\BookingController::extendStay() ang checkout, kaya umaabot
        // ito sa slot na hindi tugma sa check-in time nito.
        $windows = $bookings->map(fn ($b) => [
            'id' => $b->id,
            'start' => \Carbon\Carbon::parse($b->check_in_date->format('Y-m-d').' '.$b->check_in_time),
            'end' => \Carbon\Carbon::parse($b->check_out_date->format('Y-m-d').' '.$b->check_out_time),
        ])->all();

        // Ang mga petsang posibleng maapektuhan lang ang sinusuri — bakante
        // ang lahat ng iba pa. Kasama ang araw bago ang check-in at ang
        // araw matapos ang check-out, dahil ang overnight na booking ay
        // tumatawid sa hangganan ng araw.
        $candidates = [];
        foreach ($bookings as $b) {
            $cursor = $b->check_in_date->copy()->subDay();
            $last = $b->check_out_date->copy()->addDay();
            while ($cursor->lte($last)) {
                $candidates[$cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        $todayStr = today()->format('Y-m-d');
        $map = [];

        foreach (array_keys($candidates) as $date) {
            if ($date < $todayStr) {
                continue;
            }

            // Kasama ang booking id kada saradong slot para magamit ito ng
            // live (Pusher) na "freed" na update sa calendar: iyon lang ang
            // paraan para malaman kung aling pill ang aalisin kapag
            // kinansela ang isang booking habang bukas ang page.
            $taken = [];
            foreach (array_keys(static::SLOTS) as $slotKey) {
                [$slotStart, $slotEnd] = static::slotDateTimes($slotKey, $date);

                foreach ($windows as $w) {
                    if ($slotStart->lt($w['end']) && $slotEnd->gt($w['start'])) {
                        $taken[$slotKey] = $w['id'];
                        break;
                    }
                }
            }

            if ($taken) {
                $map[$date] = $taken;
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * Aling mga slot ang lumipas na PARA SA ARAW NA ITO, base sa oras ng
     * server nang i-render ang page. Ito ang kaparehong pagsusuri ng
     * `$checkin->isPast()` sa mga controller — kung wala ito, ang isang
     * bukas na slot kaninang umaga ay mukhang mapipili pa rin ngayong gabi
     * at tatanggihan lang pagkatapos i-submit.
     *
     * @return array<int, string>
     */
    public static function pastSlotsToday(): array
    {
        $past = [];

        foreach (array_keys(static::SLOTS) as $slotKey) {
            [$checkIn] = static::slotDateTimes($slotKey, today()->format('Y-m-d'));
            if ($checkIn->isPast()) {
                $past[] = $slotKey;
            }
        }

        return $past;
    }

    /**
     * Kinukuha ang buong check-in/check-out Carbon datetime pair para sa
     * isang slot ('day' o 'night'), base sa petsa ng check-in.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public static function slotDateTimes(string $slot, string $checkInDate): array
    {
        abort_unless(isset(static::SLOTS[$slot]), 422, 'Invalid booking slot.');
        $def = static::SLOTS[$slot];

        $checkIn  = \Carbon\Carbon::parse($checkInDate . ' ' . $def['check_in']);
        $checkOut = \Carbon\Carbon::parse($checkInDate . ' ' . $def['check_out']);
        if ($def['overnight']) {
            $checkOut->addDay();
        }

        return [$checkIn, $checkOut];
    }

    /**
     * Kabaligtaran ng slotDateTimes() — tinitignan kung aling slot ang
     * tugma sa NAKAIMBAK na oras ng booking na ito. Null kung walang
     * eksaktong tugmang slot (hal. lumang booking bago ipatupad ang fixed
     * slots, o isang stay na pinahaba ng extendStay()).
     *
     * ⚠️ EKSAKTO ang hinahanap na tugma: check-in time, check-out time, AT
     * kung tumatawid ba ng araw — hindi ang check-in time lamang.
     *
     * Dati, check-in time lang ang sinusuri. Tama lang iyon HABANG dalawa
     * ang slot: magkaiba ang 08:00 at 19:00, kaya isang field ay sapat na
     * pambukod. Sa sandaling magkapareho ng check-in time ang dalawang
     * slot — gaya ng Night (19:00–06:00) at ng 22-Hours (19:00–17:00, na
     * pinayagan ng may-ari) — ang unang tumama sa loop ang nananalo, at
     * TAHIMIK nang nagsisinungaling ang sagot.
     *
     * Hindi lang label ang nasisira doon. Ang haba ng booking ay
     * kinukuha ng `Admin\CalendarController::move()` mula sa sinasagot
     * nitong slot (tingnan ang slotDateTimes() na tawag doon), kaya ang
     * isang 22-oras na booking na ikinaladkad sa ibang petsa ay muling
     * maisusulat bilang 11-oras na Night: labing-isang oras na binayaran
     * ng bisita, nawawala nang walang anumang tala. Kaya dalawa ang oras
     * na sinusuri dito, hindi isa.
     *
     * Kasama rin ang day-span sa pagsusuri para hindi tumama ang isang
     * sirang row (hal. 19:00–17:00 sa IISANG petsa — negatibo ang haba)
     * sa isang tunay na overnight na slot.
     *
     * ⚠️ HINDI ITO ANG TANONG NA "anong slot ba ang booking na ito" —
     * `slotKey()` ang sumasagot niyon, at binabasa nito ang nakaimbak na
     * `slot` column. Ang tanong DITO ay: "ang oras na nakaimbak ngayon,
     * tumutugma pa ba nang eksakto sa isang slot?"
     *
     * Dalawang magkaibang tanong iyon, at iisang method lang ang
     * sumasagot sa kanila dati — ligtas lang iyon habang natatangi ang
     * check-in time ng bawat slot. Ito ang bersiyong dapat gamitin kapag
     * ang sagot ay magtatakda ng HABA ng booking (hal. ang drag-move sa
     * calendar): kapag pinahaba na ng `extendStay()` ang isang stay,
     * WALA itong isinasagot, kaya hindi maaaring maibalik ng isang
     * drag-move ang checkout sa de-latang 6:00 AM at mabura ang
     * extension — ang tunay nitong haba ang mapapanatili.
     */
    public function exactSlotKey(): ?string
    {
        if (empty($this->check_in_time) || empty($this->check_out_time)
            || empty($this->check_in_date) || empty($this->check_out_date)) {
            return null;
        }

        $in  = \Carbon\Carbon::parse($this->check_in_time)->format('H:i');
        $out = \Carbon\Carbon::parse($this->check_out_time)->format('H:i');

        // Tumatawid ba ng araw ang booking? Ihahambing ito sa `overnight`
        // ng bawat slot definition.
        $crossesDay = $this->check_in_date->format('Y-m-d')
            !== $this->check_out_date->format('Y-m-d');

        foreach (static::SLOTS as $key => $def) {
            if ($def['check_in'] === $in
                && $def['check_out'] === $out
                && (bool) $def['overnight'] === $crossesDay) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Anong slot ang booking na ito? Para sa mga label at sa pre-fill ng
     * mga form. Null kung wala talaga (hal. legacy row bago ang v5.1).
     *
     * Ang NAKAIMBAK na `slot` ang unang sinasagot — iyon ang tanging
     * mapagkakatiwalaang sagot kapag hindi na sinasabi ng oras. Ang
     * paghahambing ng oras ay fallback lang para sa mga row na hindi pa
     * nasusulatan ng column (mga naunang booking sa pagitan ng deploy at
     * ng migration, o kung hindi pa tumakbo ang migration).
     *
     * Huwag itong gamitin para kuwentahin ang HABA ng booking — tingnan
     * ang exactSlotKey() at ang dahilan sa docblock nito.
     */
    public function slotKey(): ?string
    {
        if (! empty($this->slot) && isset(static::SLOTS[$this->slot])) {
            return $this->slot;
        }

        return $this->exactSlotKey();
    }

    public function checkInDateTime(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->check_in_date->format('Y-m-d') . ' ' . $this->check_in_time);
    }

    public function checkOutDateTime(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->check_out_date->format('Y-m-d') . ' ' . $this->check_out_time);
    }

    /**
     * Tinitignan kung may existing booking na mag-co-conflict sa
     * bagong check-in/check-out window. Walang hiwalay na buffer na
     * idinadagdag dito — sapat na ang gap sa pagitan ng dalawang fixed
     * slot (see SLOTS above) bilang cleaning buffer.
     *
     * @param  int            $propertyId         Karaniwan, ito na lang yung ID ng master "Villa Elena" record.
     * @param  \Carbon\Carbon $newCheckIn         Buong datetime ng bagong check-in.
     * @param  \Carbon\Carbon $newCheckOut        Buong datetime ng bagong check-out.
     * @param  int|null       $excludeBookingId   Booking ID na hindi isasali sa check (para sa "update" ng existing booking).
     */
    public static function hasConflict(
        int $propertyId,
        \Carbon\Carbon $newCheckIn,
        \Carbon\Carbon $newCheckOut,
        ?int $excludeBookingId = null
    ): bool {
        // Ang admin block (Admin → Calendar → Block Dates) ay
        // hinaharangan DITO, hindi sa bawat controller. Lahat ng
        // gumagawa o lumilipat ng booking ay dumadaan sa reserveSlot(),
        // at ang reserveSlot() ay tumatawag nito sa loob ng lock nito —
        // kaya iisang dagdag ang sumasakop sa lahat ng pitong daanan.
        //
        // Bago ito (v7.17), ang `availability_blocks` ay binabasa LAMANG
        // ng staff availability grid, at pang-DISPLAY lang. Ibig sabihin
        // nakikita ni staff ang "Blocked" habang tinatanggap pa rin ng
        // LAHAT ng booking path ang petsa — pati ang public portal, kaya
        // nakakapag-book ang guest sa isang petsang ipinasara ng admin.
        // Walang nakakasalo nito sa ilalim: ang `slot_hold` UNIQUE index
        // ay gawa sa datos ng booking, hindi ng block.
        if (static::blockOn($propertyId, $newCheckIn) !== null) {
            return true;
        }

        $query = static::where('property_id', $propertyId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            // Anti-abuse: mga "pending" (unpaid) booking na lumagpas na sa
            // "Booking Hold" window (Settings → Booking Rules) ay
            // itinuturing nang abandoned hold (guest na umalis lang sa
            // PayMongo checkout at hindi na bumalik) — hindi na sila
            // dapat mag-block ng slot kahit hindi pa sila explicit
            // na na-cancel. Awtomatiko itong nagbubukas ulit ng slot
            // nang walang kailangang cron job.
            ->where(function ($q) {
                $q->where('status', '!=', 'pending')
                  ->orWhere('created_at', '>=', now()->subMinutes(static::pendingHoldMinutes()));
            })
            // rough pre-filter para hindi kailangang i-loop lahat ng records
            ->where('check_out_date', '>=', $newCheckIn->copy()->subDay())
            ->where('check_in_date', '<=', $newCheckOut->copy()->addDay());

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        foreach ($query->get() as $existing) {
            // Overlap check — walang buffer padding, dahil ang gap sa
            // pagitan ng dalawang fixed slot na mismo ang buffer.
            if ($newCheckIn->lt($existing->checkOutDateTime()) && $newCheckOut->gt($existing->checkInDateTime())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ang admin block na sumasaklaw sa check-in date na ito, kung meron.
     *
     * Ibinabalik ang mismong ROW, hindi boolean, dahil dalawa ang
     * kailangan sa kanya: ang desisyon (hasConflict) at ang DAHILAN na
     * ipinapakita sa tao. Pareho ang NULL na sagot ng reserveSlot() sa
     * "nakuha na ng iba" at sa "ipinasara ng admin", pero hindi
     * puwedeng pareho ang sasabihin — ang "naka-book na" sa isang
     * sadyang ipinasarang petsa ay nagpapahanap kay staff ng isang
     * booking na wala naman.
     */
    public static function blockOn(int $propertyId, \Carbon\Carbon $checkIn): ?AvailabilityBlock
    {
        return AvailabilityBlock::coveringDate($propertyId, $checkIn)->first();
    }

    /**
     * Mensaheng nakikita ng tao kapag tinanggihan ang slot.
     *
     * Iisang lugar para hindi mag-drift ang limang call site ng
     * reserveSlot(); ipinapasa ng caller ang sarili nitong "nakuha na"
     * na pananalita, dahil magkaiba ang tono ng guest at ng staff.
     */
    public static function unavailableMessage(
        int $propertyId,
        \Carbon\Carbon $checkIn,
        string $takenMessage,
        bool $forStaff = false
    ): string {
        $block = static::blockOn($propertyId, $checkIn);

        if (! $block) {
            return $takenMessage;
        }

        $reason = ucfirst(str_replace('_', ' ', $block->reason));

        return $forStaff
            ? "That date is blocked ({$reason}) and cannot be booked. An admin can remove the block in Calendar → Blocked Dates."
            : 'That date is not open for booking. Please choose another date.';
    }

    // ── Atomic Slot Reservation ─────────────────────────────────────

    /**
     * Ang IISANG paraan ng paggawa o paglipat ng booking.
     *
     * Ang hasConflict() ay SELECT lang. Kapag tinawag ito nang mag-isa,
     * may puwang sa pagitan ng "wala palang kasalungat" at ng INSERT na
     * walang humahawak ng kahit ano — kaya dalawang request na sabay
     * dumating ay PAREHONG nakakakita ng bakante, at parehong pumapasok.
     * Hindi ito teorya: VE-4C7INQOG at VE-YHLBMLUU, parehong 2026-09-15
     * Day slot, parehong `created_at` na 16:33:05, parehong nabayaran.
     *
     * Kaya lahat ng gumagawa/lumilipat ng booking (public portal, admin
     * create, staff walk-in, customer reschedule, extend stay) ay dapat
     * dumaan dito — kagaya ng slotDateTimes() sa oras at ng quoteFor()
     * sa presyo. Ang lock ay hindi nakakabit sa `bookings` (walang
     * mailo-lock kung wala pang row), kundi sa mismong `properties` row:
     * isang tunay na row na tiyak na umiiral, kaya deterministiko ang
     * pagse-serialize — walang inaasahang gap-lock na gawi ng InnoDB.
     *
     * Ang callback ay tumatakbo LAMANG kapag napatunayang bakante pa
     * ang slot, at nasa loob pa rin ng lock. Panatilihing DB lang ang
     * laman nito — ang mail, broadcast at notification ay pagkatapos ng
     * commit, hindi sa loob (isang nag-timeout na Pusher call ay hindi
     * dapat mag-rollback ng nabayarang booking).
     *
     * @param  \Closure  $callback  Gumagawa/nag-uupdate ng booking. Dapat may ibinabalik.
     * @return mixed  Ang ibinalik ng callback, o NULL kung hindi na bakante ang slot.
     */
    public static function reserveSlot(
        int $propertyId,
        \Carbon\Carbon $checkIn,
        \Carbon\Carbon $checkOut,
        \Closure $callback,
        ?int $excludeBookingId = null
    ) {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($propertyId, $checkIn, $checkOut, $callback, $excludeBookingId) {
                // Ang serialization point. Lahat ng sabay-sabay na
                // booking sa iisang property ay pipila rito.
                Property::whereKey($propertyId)->lockForUpdate()->first();

                static::releaseExpiredHolds($propertyId, $checkIn, $checkOut, $excludeBookingId);

                if (static::hasConflict($propertyId, $checkIn, $checkOut, $excludeBookingId)) {
                    return null;
                }

                return $callback();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Huling sala ng UNIQUE index sa `slot_hold`. Hindi ito
            // dapat maabot habang buo ang lock sa itaas — pero kung
            // maabot man (hal. bagong code path na hindi dumadaan dito),
            // isang "hindi na bakante" ang tamang sagot sa guest, hindi
            // isang 500.
            if (static::isSlotHoldViolation($e)) {
                \Illuminate\Support\Facades\Log::warning(
                    'slot_hold unique index rejected a booking for property '.$propertyId.' at '
                    .$checkIn->format('Y-m-d H:i').' — a concurrent write got past reserveSlot().'
                );

                return null;
            }

            throw $e;
        }
    }

    /**
     * Pormal nang kinakansela ang mga "abandoned" na unpaid hold na
     * dumadaan sa hinihinging slot, habang hawak ang lock ng
     * reserveSlot().
     *
     * Kailangan ito dahil MAGKAIBA ang pananaw ng dalawang mekanismo sa
     * isang lumagpas-nang-oras na `pending` booking: pinapalampas na ito
     * ng hasConflict() (kaya "bakante" ang sabi ng availability grid),
     * pero row pa rin ito na may `slot_hold` kaya haharangin ito ng
     * UNIQUE index. Kung hindi lilinisin dito, magkakaroon ng slot na
     * bukas sa mata ng guest pero tinatanggihan ng INSERT.
     *
     * Dating ang AutoCheckInOutBookings::cancelStalePendingBookings()
     * lang ang gumagawa nito — pero cron-driven iyon, at sa Render ay
     * external pinger ang nagpapatakbo ng scheduler. Hindi puwedeng
     * nakasalalay ang tama ng booking sa kung tumatakbo ba ang cron.
     */
    private static function releaseExpiredHolds(
        int $propertyId,
        \Carbon\Carbon $checkIn,
        \Carbon\Carbon $checkOut,
        ?int $excludeBookingId
    ): void {
        $query = static::where('property_id', $propertyId)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(static::pendingHoldMinutes()))
            // Kaparehong dobleng proteksiyon ng sweeper: kailanman ay
            // hindi kinakansela ang isang booking na may hawak nang pera.
            ->where(function ($q) {
                $q->whereNull('amount_paid')->orWhere('amount_paid', '<=', 0);
            })
            ->where('check_out_date', '>=', $checkIn->copy()->subDay())
            ->where('check_in_date', '<=', $checkOut->copy()->addDay());

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        foreach ($query->get() as $hold) {
            // Yung mga tunay na dumadaan lang sa hinihinging window —
            // hindi lahat ng expired hold ng property na ito.
            if (! ($checkIn->lt($hold->checkOutDateTime()) && $checkOut->gt($hold->checkInDateTime()))) {
                continue;
            }

            $hold->releaseAsExpiredHold();
        }
    }

    /**
     * Kinakansela ang booking na ito bilang isang abandoned na unpaid
     * hold, at ipinapaalam sa guest at admin.
     *
     * Pinagsasaluhan ng reserveSlot() at ng
     * AutoCheckInOutBookings::cancelStalePendingBookings() — dalawang
     * kopya ng wording at ng cancellation fields ay siguradong
     * maglalayo sa isa't isa.
     *
     * Ipinapadala ang mga notification PAGKATAPOS ng commit: kapag
     * tinawag ito mula sa loob ng reserveSlot(), mali (at
     * mapanganib) na magpaalam ng "na-cancel na" para sa isang
     * transaction na puwede pang mag-rollback. Sa labas ng
     * transaction, agad na tumatakbo ang afterCommit().
     */
    public function releaseAsExpiredHold(): void
    {
        $holdMinutes = static::pendingHoldMinutes();
        $holdLabel = $holdMinutes % 60 === 0
            ? ($holdMinutes / 60) . '-hour'
            : $holdMinutes . '-minute';

        $this->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => "Auto-cancelled by the system — payment was not completed within the {$holdLabel} grace period.",
            'cancelled_by'        => 'system',
            'balance_due'         => 0,
        ]);

        StaffLog::record('auto_cancelled_stale_booking', 'bookings', $this->id,
            "System auto-cancelled unpaid pending booking {$this->booking_ref} ({$holdMinutes}+ minutes since created, no payment received).");

        $booking = $this;

        \Illuminate\Support\Facades\DB::afterCommit(function () use ($booking, $holdLabel) {
            $booking->loadMissing(['user', 'property']);

            \App\Helpers\NotificationHelper::notifyGuest(
                $booking->user_id,
                'Booking Auto-Cancelled — Grace Period Expired',
                "Your booking {$booking->booking_ref} for {$booking->property->property_name} was automatically cancelled because the required 50% downpayment wasn't completed within the {$holdLabel} grace period. Feel free to book again if the dates are still available.",
                route('customer.bookings.show', $booking, false)
            );

            \App\Helpers\NotificationHelper::bookingAutoCancelled($booking, $holdLabel);
        });
    }

    /**
     * `true` kung ang QueryException na ito ay galing sa UNIQUE index
     * ng `slot_hold` — ibig sabihin, isang double-booking ang sinagasa
     * ng database.
     */
    private static function isSlotHoldViolation(\Illuminate\Database\QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'bookings_slot_hold_unique');
    }

    // ── Anti-Abuse / Anti-Spam Helpers ──────────────────────────────

    /**
     * Tinitignan kung may isa nang "pending" (unpaid, loob pa ng
     * "Booking Hold" window) na booking ang user na ito. Ginagamit para
     * pigilan ang isang account na sabay-sabay humawak ng maraming
     * unpaid slot ("booking spam") — kailangan munang bayaran o
     * kanselahin ang dati bago makagawa ng panibago.
     */
    public static function hasActivePendingBooking(int $userId): bool
    {
        return static::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(static::pendingHoldMinutes()))
            ->exists();
    }

    /**
     * Bilang ng beses na nag-cancel ang user na ito sa loob ng
     * nakaraang $days araw — ginagamit para markahan ang mga account
     * na paulit-ulit nag-bo-book tapos magca-cancel (posibleng abuse).
     */
    public static function recentCancellationCount(int $userId, int $days = 30): int
    {
        return static::where('user_id', $userId)
            ->where('status', 'cancelled')
            ->where('cancelled_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * True kapag 3+ na ang cancellation ng guest sa loob ng 30 araw —
     * kapag naka-flag, dapat i-require ng full payment (walang deposit
     * option) ang susunod nilang booking. Tingnan: PaymentController
     * (showPaymentPage/createCheckout) kung saan ito ina-apply.
     */
    public static function hasExcessiveCancellations(int $userId, int $days = 30, int $threshold = 3): bool
    {
        return static::recentCancellationCount($userId, $days) >= $threshold;
    }

    /**
     * Bilang ng beses na AUTO-CANCELLED (`cancelled_by = 'system'`) ang
     * user na ito sa loob ng nakaraang $days araw — ibang bagay ito sa
     * recentCancellationCount(), na binibilang ang LAHAT ng cancellation
     * kahit kusa ng guest. Dito, non-payment holds lang ang binibilang:
     * paulit-ulit na pag-book nang hindi talaga binabayaran.
     */
    public static function recentAutoCancelCount(int $userId, int $days = 30): int
    {
        return static::where('user_id', $userId)
            ->where('cancelled_by', 'system')
            ->where('cancelled_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * Kung kailan lilipas ang cooldown na pumipigil sa isang user na
     * gumawa ng BAGONG booking, o null kung wala/lumipas na. Naiiba ito
     * sa hasExcessiveCancellations() (na nage-force ng full payment sa
     * susunod na booking) — dito, hindi muna sila makakagawa ng bagong
     * booking, dahil ang inaalala rito ay yung account na paulit-ulit
     * humahawak ng slot (via unpaid pending booking) nang hindi talaga
     * nagbabayad, hindi yung mga tapat na nag-cancel.
     *
     * Ginagamit sa Portal\PortalController::submitBooking().
     */
    public static function bookingCooldownEndsAt(int $userId): ?\Illuminate\Support\Carbon
    {
        $threshold   = (int) Setting::get('booking_cooldown_threshold', 3);
        $windowDays  = (int) Setting::get('booking_cooldown_window_days', 30);
        $cooldownHrs = (int) Setting::get('booking_cooldown_hours', 24);

        if (static::recentAutoCancelCount($userId, $windowDays) < $threshold) {
            return null;
        }

        $lastAutoCancelledAt = static::where('user_id', $userId)
            ->where('cancelled_by', 'system')
            ->max('cancelled_at');

        if (! $lastAutoCancelledAt) {
            return null;
        }

        $endsAt = \Illuminate\Support\Carbon::parse($lastAutoCancelledAt)->addHours($cooldownHrs);

        return $endsAt->isFuture() ? $endsAt : null;
    }

    // ── Relationships ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Ang seasonal promo na na-apply nang gawin ang booking. Maaaring
     * null — alinman sa walang promo noon, o binura na ang promo
     * (nullOnDelete). Ang `discount_amount` ang nananatiling awtoridad
     * sa kung magkano talaga ang nabawas; ito ay para sa atribusyon.
     */
    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function extras()
    {
        return $this->hasMany(BookingExtra::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function issueReports()
    {
        return $this->hasMany(IssueReport::class);
    }

    /**
     * Pwede bang mag-ulat ng problema ang guest? Lang habang naka-check-in:
     * ang ulat ay para sa problemang kasalukuyang kinakaharap sa villa, at
     * ang staff na tumatanggap ay nasa villa sa oras na iyon.
     */
    public function canReportIssues(): bool
    {
        return $this->status === 'checked_in';
    }

    // ── Yugto ng refund ────────────────────────────────────────────
    /**
     * Nasaan na ang pera ng guest?
     *
     * SINASADYANG hiwalay ito sa `payment_status`. Ang `payment_status`
     * ay sumasagot sa "tapos na ba ang pera ng booking na ito?" at
     * pinagbabatayan ng mga filter, report at calendar. Ang tanong dito
     * ay iba: "naipadala na ba talaga ang refund?"
     *
     * Dating `refunded` agad ang booking sa oras na ma-approve ang
     * refund — bago pa man may perang gumalaw — kaya nagmumukhang
     * tapos na ang isang bagay na hindi pa nga nagsisimula.
     *
     * DERIVED ito, hindi naka-imbak. Walang column na puwedeng
     * mag-drift palayo sa katotohanan: ang `payments` at ang
     * `refund_transfers` na mismo ang sumasagot.
     *
     * @return string  none | owed | processing | failed | refunded
     */
    public function refundStage(): string
    {
        $refunds = $this->payments->where('payment_type', 'refund');

        if ($refunds->isEmpty()) {
            return 'none';
        }

        $outstanding = $refunds->where('status', 'pending');

        // Wala nang hinihintay — lahat ay naipadala na.
        if ($outstanding->isEmpty()) {
            return 'refunded';
        }

        // Ang nasa daan ang nangunguna: may pera nang gumagalaw, at
        // iyon ang pinaka-kapaki-pakinabang na malaman.
        foreach ($outstanding as $refund) {
            if ($refund->hasTransferInFlight()) {
                return 'processing';
            }
        }

        // May sinubukan pero hindi natuloy. Iba ito sa "hindi pa
        // sinusubukan" — may kailangang ayusin.
        foreach ($outstanding as $refund) {
            if ($refund->refundTransfers->isNotEmpty()) {
                return 'failed';
            }
        }

        return 'owed';
    }

    /**
     * Ang label na nakikita sa booking details, sa halip na ang
     * hilaw na `payment_status`.
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->refundStage()) {
            'owed'       => 'Refund',
            'processing' => 'Refund Processing',
            'failed'     => 'Refund Failed',
            'refunded'   => 'Refunded',
            default      => ucfirst((string) $this->payment_status),
        };
    }

    /**
     * Ang CSS class na katugma ng label sa itaas.
     *
     * Ang mga umiiral nang `p-*` na class ay ginagamit pa rin kung
     * saan sila tumutugma, kaya isa lang ang bagong kailangan.
     */
    public function getPaymentStatusClassAttribute(): string
    {
        return match ($this->refundStage()) {
            'owed', 'processing' => 'p-refund-progress',
            'failed'             => 'p-refund-failed',
            'refunded'           => 'p-refunded',
            default              => 'p-' . $this->payment_status,
        };
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isConfirmed(): bool  { return $this->status === 'confirmed'; }
    public function isCheckedIn(): bool  { return $this->status === 'checked_in'; }
    public function isCheckedOut(): bool { return $this->status === 'checked_out'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }
    public function isPaid(): bool       { return $this->payment_status === 'paid'; }

    // ── Cancellation / Refund Policy (v7.52) ────────────────────────
    //
    //   Sino ang nag-cancel                         Ibinabalik
    //   ─────────────────────────────────────────────────────────────
    //   Ang guest (kusa, o hiniling sa admin)       ₱0 — deposit man o
    //                                               buong bayad
    //   Ang resort (maintenance, panahon, atbp.)    lahat ng naibayad
    //
    // Patakaran ito ng may-ari: "Deposit is NON-REFUNDABLE", at ganoon
    // din ang buong bayad. Dating may tiers dito (100% sa loob ng 24 oras
    // o 7+ araw, 50% sa 3–6 araw, 0% kung wala pang 3) na kinukuwenta sa
    // `amount_paid` — kaya ang 50% deposit mismo ay naibabalik nang buo,
    // salungat sa patakaran.
    //
    // Ang tanong ay hindi na "kailan" kundi "SINO" — at hindi iyon
    // mahuhulaan ng code mula sa booking. Ang guest path
    // (Customer\HomeController::cancelBooking) ay hindi na gumagawa ng
    // refund; ang admin ay tinatanong sa cancel modal kung sino ang
    // nagpasya (Admin\BookingController::updateStatus).
    //
    // HINDI nito inaalis ang refund machinery (Send Money, refund
    // destinations, Payments page). Tatlong uri ng refund ang walang
    // kinalaman sa pag-cancel ng guest: ang resort ang nag-cancel,
    // sobra/dobleng singil, at bayad na dumating matapos makuha ng iba
    // ang slot.

    /**
     * Ang iisang pangungusap ng patakaran na ipinapakita sa guest.
     *
     * Limang lugar ang nagsasabi nito (Terms, booking form, checkout,
     * booking detail, chatbot). Noong may kanya-kanya silang kopya,
     * tatlo ang magkakaibang sinasabi — at ang chatbot ay nangangako pa
     * ng "free cancellation 48 hours" na hindi kailanman ipinatupad.
     */
    public const CANCELLATION_POLICY = 'All payments are non-refundable — the deposit and a full payment alike. Cancelling a booking does not return what you have paid.';

    /**
     * Ibabalik sa guest kapag ANG RESORT ang nag-cancel: lahat ng
     * naibayad niya. Ang `amount_paid` ay net na ng mga naunang refund
     * (tingnan ang recalculateFinancials()), kaya hindi ito madodoble.
     */
    public function resortCancellationRefund(): float
    {
        return max(0, round((float) $this->amount_paid, 2));
    }

    public function isCancellable(): bool
    {
        // Pwede lang i-cancel ang mga booking na hindi pa nagsisimula
        // ang stay — kapag naka-check-in/checked-out/cancelled na,
        // hindi na ito dapat pwedeng i-cancel dito.
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Ilang beses lang pwedeng ilipat ng guest ang iisang booking.
     */
    public const MAX_RESCHEDULES = 2;

    /**
     * Ilang araw bago ang check-in huling pwedeng mag-reschedule.
     *
     * Ang dahilan nito ngayon (v7.52): ang slot na pinalaya nang huli
     * ay slot na hindi na maibebenta ulit ng resort. Kailangan nito ng
     * panahon para may ibang makapag-book.
     *
     * Iba ang orihinal na dahilan, at wala na iyon: noong may refund
     * tiers pa, tinutugma ito sa 100% tier para hindi maiwasan ang
     * cancellation penalty sa pamamagitan ng paglipat ng booking sa
     * malayong petsa at doon mag-cancel. Wala nang refund ang guest
     * cancellation, kaya wala nang butas na isasara. Dahil reschedule
     * na lang ang natitirang lunas ng guest, desisyon ng may-ari kung
     * luluwagan ang bilang na ito — hindi ng code.
     */
    public const RESCHEDULE_CUTOFF_DAYS = 7;

    public function isReschedulable(): bool
    {
        return $this->rescheduleBlockReason() === null;
    }

    /**
     * Ibinabalik ang dahilan kung bakit HINDI pwedeng i-reschedule, o
     * null kung pwede. Iisang pinagmumulan ito ng katotohanan para sa
     * controller guard at sa ipinapakitang mensahe sa guest — para hindi
     * magkaiba ang sinasabi ng UI sa aktwal na ipinapatupad.
     */
    public function rescheduleBlockReason(): ?string
    {
        if (! $this->isCancellable()) {
            return 'This booking can no longer be rescheduled.';
        }

        if ($this->reschedule_count >= self::MAX_RESCHEDULES) {
            return 'You have already rescheduled this booking '
                . self::MAX_RESCHEDULES . ' times, which is the maximum allowed. '
                . 'Please contact us directly if you need to make further changes.';
        }

        $daysUntilCheckIn = now()->diffInDays($this->checkInDateTime(), false);

        if ($daysUntilCheckIn < self::RESCHEDULE_CUTOFF_DAYS) {
            return 'Bookings can only be rescheduled at least '
                . self::RESCHEDULE_CUTOFF_DAYS . ' days before check-in. '
                . 'Please contact us directly if you need to make changes.';
        }

        return null;
    }

    public function reschedulesRemaining(): int
    {
        return max(0, self::MAX_RESCHEDULES - (int) $this->reschedule_count);
    }

    /**
     * Muling kinukuwenta ang amount_paid / balance_due / payment_status
     * mula mismo sa Payment records ng booking na ito.
     *
     * Dati, kinopya-kopya ang lohikang ito sa pitong magkakaibang lugar
     * (dalawang cancel path, tatlong record-payment path, reschedule, at
     * ang PayMongo success callback) — at hindi na sila magkakapareho.
     * Isang kopya ang nagtatakda ng payment_status na 'partial' kahit
     * bayad na nang buo ang guest, at isa naman ang hindi binibilang ang
     * mga refund na hindi pa naibibigay. Iisang kopya na lang ngayon —
     * tumawag nito sa halip na gumawa ng panibagong kopya.
     *
     * Dalawang panuntunan ang ipinapatupad dito:
     *
     *   1. Ang mga TUNAY na bayad ay binibilang lang kapag `status`
     *      ay 'success' — ang isang naiwang 'pending' na gateway payment
     *      ay hindi pa perang nasa kamay.
     *
     *   2. Ang mga REFUND ay binibilang agad-agad, anuman ang `status`
     *      nila. Sa sandaling maaprubahan ang refund, may utang na ang
     *      resort sa guest — dapat agad itong makita sa booking kahit
     *      hindi pa nailalabas ang pera. Ang `status` ng refund row ang
     *      sumusubaybay sa aktwal na paglabas ng pera (tingnan ang
     *      Payment::isPaidOut()), hindi kung utang ba ito o hindi.
     */
    public function recalculateFinancials(): void
    {
        $totalPaid = $this->payments()
            ->where('payment_type', '!=', 'refund')
            ->where('status', 'success')
            ->sum('amount');

        $totalRefunded = $this->payments()
            ->where('payment_type', 'refund')
            ->sum('amount');

        $netPaid = max(0, round($totalPaid - $totalRefunded, 2));

        if (in_array($this->status, ['cancelled', 'no_show'])) {
            // Tapos na ang booking — wala nang babayaran ang guest
            // anuman ang tier na na-apply.
            $balanceDue    = 0;
            $paymentStatus = $netPaid > 0
                ? ($totalRefunded > 0 ? 'partial' : 'paid')
                : ($totalRefunded > 0 ? 'refunded' : 'unpaid');
        } else {
            $balanceDue = max(0, round((float) $this->total_amount - $netPaid, 2));

            if ($netPaid <= 0) {
                $paymentStatus = $totalRefunded > 0 ? 'refunded' : 'unpaid';
            } elseif ($balanceDue <= 0) {
                $paymentStatus = 'paid';
            } else {
                $paymentStatus = 'partial';
            }
        }

        $this->update([
            'amount_paid'    => $netPaid,
            'balance_due'    => $balanceDue,
            'payment_status' => $paymentStatus,
        ]);

        $this->flagOverpayment();
    }

    /**
     * Sobra ba sa presyo ng booking ang naibayad na?
     *
     * Ang `balance_due` ay may `max(0, ...)` — sinasadya iyon (walang
     * negatibong utang), pero ibig sabihin din, ang isang sobrang bayad
     * ay MUKHANG kapareho lang ng isang tamang-tamang bayad: `paid`,
     * balanse ₱0. Iyon ang dahilan kung bakit kayang maganap ang isang
     * dobleng singil nang walang kahit anong bakas sa app. Dito
     * nakukuha ang pagkakaibang iyon.
     */
    public function overpaidAmount(): float
    {
        return max(0, round((float) $this->amount_paid - (float) $this->total_amount, 2));
    }

    public function isOverpaid(): bool
    {
        return $this->overpaidAmount() > 0;
    }

    /**
     * Inaabisuhan ang admin kapag may sobrang bayad — MINSAN lang kada
     * pangyayari, hindi kada recalculation.
     *
     * Hindi awtomatikong gumagawa ng refund: ang pagpapasya kung
     * ibabalik ba ito, o ilalagay sa extra charges, o may ibang
     * kaayusan, ay sa tao. Ang tanging kasalanan ng sistema dati ay ang
     * hindi man lang pagsabi.
     *
     * Nililinis ang timestamp kapag hindi na sobra ang bayad (nairefund
     * na, o tumaas ang total dahil sa extras), kaya ang susunod na
     * sobrang bayad ay maaabisuhan ulit.
     */
    protected function flagOverpayment(): void
    {
        $excess = $this->overpaidAmount();

        if ($excess <= 0) {
            if ($this->overpayment_notified_at !== null) {
                static::whereKey($this->getKey())->update(['overpayment_notified_at' => null]);
                $this->overpayment_notified_at = null;
            }

            return;
        }

        if ($this->overpayment_notified_at !== null) {
            return;
        }

        // Direktang query update: hindi dapat pumutok ang saving() hook
        // (at ang slot_hold recompute nito) dahil lang sa isang flag.
        static::whereKey($this->getKey())->update(['overpayment_notified_at' => now()]);
        $this->overpayment_notified_at = now();

        \Illuminate\Support\Facades\Log::warning(
            "Overpayment on {$this->booking_ref}: paid ₱{$this->amount_paid} against a total of ₱{$this->total_amount} (₱{$excess} excess)."
        );

        $booking = $this;

        \Illuminate\Support\Facades\DB::afterCommit(function () use ($booking, $excess) {
            \App\Helpers\NotificationHelper::notifyAdmin(
                "Overpayment — {$booking->booking_ref}",
                'Booking '.$booking->booking_ref.' has been paid ₱'.number_format($excess, 2)
                .' more than its total of ₱'.number_format((float) $booking->total_amount, 2)
                .' (₱'.number_format((float) $booking->amount_paid, 2).' received). '
                .'This usually means the guest was charged twice. Check the payments list for the booking '
                .'and return the excess through the Payments page.',
                route('admin.bookings.show', $booking, false)
            );
        });
    }

    /**
     * Ino-confirm ang booking sa sandaling may aktwal na natanggap na
     * bayad. Tawagin ito PAGKATAPOS ng recalculateFinancials(), para
     * bago na ang amount_paid.
     *
     * WALANG admin approval step ang sistema — ang unang matagumpay na
     * bayad ang nagko-confirm ng booking. Ipinapatupad na ito ng
     * PayMongo path mula pa noon, pero ang TATLONG manwal na
     * record-payment path (admin payments page, admin booking detail,
     * staff frontdesk) ay tumatawag lang ng recalculateFinancials() —
     * na humahawak sa amount_paid/balance_due/payment_status at HINDI
     * sa `status`.
     *
     * Delikado ang puwang na iyon dahil pumapasok doon ang
     * AutoCheckInOutBookings::cancelStalePendingBookings(): kinakansela
     * nito ang mga booking na 'pending' pa lampas sa grace period.
     * Resulta: tumatanggap ang staff ng downpayment sa front desk,
     * nananatiling 'pending' ang booking, tapos kinakansela ito ng
     * sistema na sinasabi sa guest na "hindi nakumpleto ang
     * downpayment" — habang hawak na pala ang pera nila. Nangyari ito
     * kay VE-KX24HC95, na may hawak na ₱2,000.
     *
     * @return bool  true kung ito mismo ang nag-flip mula 'pending'
     */
    public function confirmOnFirstPayment(): bool
    {
        if ($this->status !== 'pending' || $this->amount_paid <= 0) {
            return false;
        }

        // Pangalawang ruta patungo sa double-booking, walang
        // kinalaman sa race condition: ang isang `pending` na hold na
        // lumagpas na sa grace period ay tumitigil nang mag-block ng
        // slot, kaya puwede nang mabook ng ibang guest — pero ang
        // PayMongo checkout link ng unang guest ay BUKAS PA RIN. Kapag
        // bumalik siya at nagbayad, dating basta na lang siyang
        // ginagawang 'confirmed' dito, kahit may ibang may hawak na ng
        // slot na iyon.
        //
        // Hindi puwedeng basta ibasura ang bayad (may hawak na tayong
        // pera), at hindi rin puwedeng kanselahin ang booking dito
        // (desisyon iyon ng admin, may refund na kasama). Kaya
        // naiiwan itong 'pending' — na siya ring ligtas na estado:
        // hindi na ito kinakansela ng stale sweeper dahil
        // `amount_paid > 0`, kaya nananatili itong nakikita at may
        // pera, hanggang may magdesisyon.
        if (static::hasConflict($this->property_id, $this->checkInDateTime(), $this->checkOutDateTime(), $this->id)) {
            \Illuminate\Support\Facades\Log::warning(
                "Payment received for {$this->booking_ref} but its slot is already held by another booking — left pending for admin review."
            );

            \App\Helpers\NotificationHelper::notifyAdmin(
                "Double-booked payment — {$this->booking_ref}",
                "A payment was received for {$this->booking_ref} ("
                . $this->checkInDateTime()->format('M d, Y g:i A')
                . "), but that slot is already held by another booking. The payment was recorded and the booking left pending — decide which guest keeps the slot and refund the other.",
                route('admin.bookings.show', $this, false)
            );

            return false;
        }

        $this->update(['status' => 'confirmed']);

        return true;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'pending'     => '<span class="badge bg-warning">Pending</span>',
            'confirmed'   => '<span class="badge bg-success">Confirmed</span>',
            'checked_in'  => '<span class="badge bg-primary">Checked In</span>',
            'checked_out' => '<span class="badge bg-secondary">Checked Out</span>',
            'cancelled'   => '<span class="badge bg-danger">Cancelled</span>',
            'no_show'     => '<span class="badge bg-dark">No Show</span>',
            default       => '<span class="badge bg-light">Unknown</span>',
        };
    }

    public function getPaymentStatusBadgeAttribute(): string
    {
        return match($this->payment_status) {
            'unpaid'   => '<span class="badge bg-danger">Unpaid</span>',
            'partial'  => '<span class="badge bg-warning">Partial</span>',
            'paid'     => '<span class="badge bg-success">Paid</span>',
            'refunded' => '<span class="badge bg-info">Refunded</span>',
            default    => '<span class="badge bg-light">Unknown</span>',
        };
    }

    /**
     * Ang teksto at CSS class ng "Payment Status" sa mga page na
     * nakaharap sa guest (checkout at "Waiting for Payment").
     *
     * NASA MODEL ITO SA HALIP NA SA BLADE nang may dahilan. Dalawa ang
     * gumagamit nito: ang unang render ng page, at ang JSON ng
     * payment.status na binabasa ng watcher tuwing ilang segundo. Kung
     * dalawang kopya ito, ang page na nag-update nang kusa ay unti-unting
     * mag-iiba ang sinasabi sa page na bagong na-load — sa BAYAD pa
     * mismo, kung saan ang pinakamaliit na di-pagkakatugma ay tinatawag
     * agad ng guest.
     *
     * Hiwalay ito sa `payment_status_label`, na tungkol sa yugto ng
     * REFUND. Dito, ang tanong ay kung magkano pa ang utang.
     */
    public function paymentProgressDisplay(): array
    {
        return match($this->payment_status) {
            'paid'     => ['text' => 'Fully Paid', 'class' => 'status-confirmed'],
            'partial'  => [
                'text'  => 'Partial — ₱' . number_format((float) $this->balance_due, 2) . ' remaining',
                'class' => 'status-partial',
            ],
            // Dating nahuhulog ang 'refunded' sa default at nagsasabing
            // "Not yet received" — totoo man na wala nang hawak na pera,
            // ganap na mali ang ipinahihiwatig nito sa guest na NAGBAYAD
            // at BINALIKAN. Ginagamit ang klase ng 'unpaid' dahil pareho
            // silang ibig sabihin ay "walang nakabinbing bayad dito".
            'refunded' => ['text' => 'Refunded', 'class' => 'status-unpaid'],
            // Dating blangko ang cell na ito kapag 'unpaid' — mas
            // nakakalito iyon kaysa sa pagsasabi mismo.
            default    => ['text' => 'Not yet received', 'class' => 'status-unpaid'],
        };
    }
}