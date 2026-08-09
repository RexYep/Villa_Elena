<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'total_amount',
        'amount_paid',
        'balance_due',
        'status',
        'payment_status',
        'paymongo_session_id',
        'paymongo_payment_type',    
        'source',
        'special_requests',
        'cancelled_at',
        'cancellation_reason',
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
     * @param  int      $propertyId         Karaniwan, ito na lang yung ID ng master "Villa Elena" record.
     * @param  Carbon   $newCheckIn         Buong datetime ng bagong check-in.
     * @param  Carbon   $newCheckOut        Buong datetime ng bagong check-out.
     * @param  int|null $excludeBookingId   Booking ID na hindi isasali sa check (para sa "update" ng existing booking).
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