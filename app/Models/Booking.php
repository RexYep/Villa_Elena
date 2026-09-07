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

    // ── Fixed Booking Slots ──────────────────────────────────────────
    // Dalawang fixed na package slot na lang ang pinipili ng guest —
    // hindi na free-choice na oras. Ang gap sa pagitan ng dalawang slot
    // (5:00 PM checkout → 7:00 PM check-in, at 6:00 AM checkout → 8:00 AM
    // check-in) ang siya nang nagsisilbing cleaning buffer, kaya walang
    // hiwalay na buffer na kailangan pang i-enforce sa hasConflict().

    public const SLOTS = [
        'day' => [
            'label'     => 'Day (8:00 AM – 5:00 PM)',
            'check_in'  => '08:00',
            'check_out' => '17:00',
            'overnight' => false,
        ],
        'night' => [
            'label'     => 'Night (7:00 PM – 6:00 AM)',
            'check_in'  => '19:00',
            'check_out' => '06:00',
            'overnight' => true,
        ],
    ];

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
     * Kabaligtaran ng slotDateTimes() — tinitignan kung aling slot
     * ('day'/'night') ang tugma sa check_in_time ng existing booking na
     * ito, para sa pre-fill ng reschedule/edit forms. Null kung walang
     * tugmang slot (hal. lumang booking bago ipatupad ang fixed slots).
     */
    public function slotKey(): ?string
    {
        $time = \Carbon\Carbon::parse($this->check_in_time)->format('H:i');

        foreach (static::SLOTS as $key => $def) {
            if ($def['check_in'] === $time) {
                return $key;
            }
        }

        return null;
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

    // ── Cancellation / Refund Policy ────────────────────────────────
    //
    //   Kailan nag-cancel                          Refund ng eligible amount
    //   ─────────────────────────────────────────────────────────────
    //   Within 24 hours matapos i-book (booking     100%
    //     grace period — laging nangingibabaw ito
    //     kahit malapit na ang check-in)
    //   7+ araw bago ang check-in                   100%
    //   3–6 araw bago ang check-in                    50%
    //   Wala pang 3 araw bago ang check-in             0%
    //
    // Iisang method na ito ang ginagamit KAPWA ng customer self-cancel
    // (Customer\HomeController::cancelBooking) at admin manual-cancel
    // (Admin\BookingController::updateStatus) — para hindi sila
    // mag-out-of-sync sa computation.

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
     * Sinasadyang KAPAREHO ito ng 100%-refund tier sa
     * calculateRefundPercentage() (>= 7 araw). Dati, walang cutoff ang
     * reschedule habang may tiers ang cancellation — kaya kayang iwasan
     * nang buo ang cancellation penalty: sa halip na mag-cancel 2 oras
     * bago ang check-in (0% refund), ililipat na lang ang booking sa
     * susunod na buwan, tapos saka mag-cancel doon habang malayo pa ang
     * bagong petsa (100% refund). Ang pagpapareho ng dalawang cutoff ang
     * nagsasara sa butas na iyon — sa sandaling mawala ang 100% tier,
     * wala na ring reschedule.
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

    public function calculateRefundPercentage(): int
    {
        $now = now();

        // Grace period: 100% laging refund kung loob pa ng 24 oras mula
        // nang gawin ang booking, anuman ang lapit ng check-in date.
        if ($this->created_at && $this->created_at->diffInHours($now) <= 24) {
            return 100;
        }

        $checkIn = $this->checkInDateTime();

        // Positive na bilang ng oras kung nasa hinaharap pa ang check-in,
        // negative kung nakalipas na (hal. late cancellation o no-show).
        $hoursUntilCheckIn = $checkIn->gt($now)
            ? $now->diffInHours($checkIn)
            : -$now->diffInHours($checkIn);

        $daysUntilCheckIn = $hoursUntilCheckIn / 24;

        if ($daysUntilCheckIn >= 7) {
            return 100;
        }
        if ($daysUntilCheckIn >= 3) {
            return 50;
        }
        return 0;
    }

    public function calculateRefundAmount(): float
    {
        $percentage = $this->calculateRefundPercentage();
        return round(((float) $this->amount_paid) * $percentage / 100, 2);
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
}