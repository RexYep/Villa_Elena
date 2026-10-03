<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Isang petsa kung kailan inaalok ang isang slot na hindi pang-araw-araw.
 *
 * @property int $id
 * @property int $property_id
 * @property string $slot
 * @property \Illuminate\Support\Carbon $check_in_date
 * @property int $is_active
 * @property string|null $notes
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Property $property
 *
 * @mixin \Eloquent
 */
class SlotWindow extends Model
{
    use HasFactory;

    /**
     * Ang pagdagdag o pag-alis ng window ay nagbabago sa kung ano ang
     * inaalok sa isang petsa, kaya kailangang mag-refetch ang staff
     * Availability grid at ang mga bukas na pahina — kapareho ng
     * ginagawa ng AvailabilityBlock.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Booking::touchAvailability());
        static::deleted(fn () => Booking::touchAvailability());
    }

    protected $fillable = [
        'property_id',
        'slot',
        'check_in_date',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'check_in_date' => 'date',
    ];

    /**
     * Ang mga aktibong window para sa isang CHECK-IN date.
     *
     * Check-in date ang batayan, hindi ang buong haba ng stay — kapareho
     * ng `AvailabilityBlock::scopeCoveringDate()`, at para sa parehong
     * dahilan: ang grid, ang public calendar at ang guard ay dapat
     * EKSAKTONG isang tanong lang ang itinatanong. Kapag nagkaiba sila,
     * may petsang mukhang bukas na tatanggihan pala sa pag-submit (o mas
     * masahol: kabaligtaran).
     *
     * Ang isang 22-oras na window sa Okt 2 ay sumasaklaw sa gabi ng Okt 2
     * hanggang hapon ng Okt 3, pero ang PETSA ng window ay Okt 2 lang.
     * Ang Okt 3 ay isang ordinaryong petsa na may Day/Night — at kapag
     * na-book ang 22 oras, ang Day ng Okt 3 ay isasara ng hasConflict()
     * nang mag-isa, hindi ng window.
     */
    public function scopeOfferedOn($query, int $propertyId, string $slot, $date)
    {
        $day = $date instanceof \Carbon\Carbon
            ? $date->copy()->startOfDay()
            : \Carbon\Carbon::parse($date)->startOfDay();

        return $query->where('property_id', $propertyId)
            ->where('slot', $slot)
            ->where('is_active', 1)
            ->whereDate('check_in_date', $day);
    }

    /**
     * Ang check-in/check-out datetime ng stay na inaalok ng window na ito.
     *
     * Hango sa Booking::slotDateTimes(), hindi iniimbak — iisa lang ang
     * dapat nagsasabi ng haba ng isang slot.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function stayDateTimes(): array
    {
        return Booking::slotDateTimes($this->slot, $this->check_in_date->format('Y-m-d'));
    }

    /** Hal. "7:00 PM Oct 2 – 5:00 PM Oct 3" — para sa kumpirmasyon ng admin. */
    public function getSpanLabelAttribute(): string
    {
        [$in, $out] = $this->stayDateTimes();

        return $in->format('g:i A M j').' – '.$out->format('g:i A M j');
    }

    /** Lumipas na ba ang check-in ng window na ito? */
    public function isPast(): bool
    {
        return $this->stayDateTimes()[0]->isPast();
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
