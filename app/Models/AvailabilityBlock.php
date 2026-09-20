<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $property_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string $reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AvailabilityBlock whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AvailabilityBlock extends Model
{
    use HasFactory;

    /**
     * Ang block na idinagdag o inalis ng admin (calendar, property page,
     * prescriptive action) ay nagbabago sa staff Availability grid — dito
     * sa model para sakop ang bawat isa ng mga daanang iyon.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Booking::touchAvailability());
        static::deleted(fn () => Booking::touchAvailability());
    }

    protected $fillable = [
        'property_id',
        'start_date',
        'end_date',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /**
     * Ang mga block na sumasaklaw sa isang CHECK-IN date.
     *
     * CHECK-IN date ang batayan, hindi ang buong haba ng stay — at
     * sinasadya ito: ganito na tumitingin ang staff availability grid
     * mula pa noong una (`$date->betweenIncluded($b->start_date,
     * $b->end_date)`), at kailangang EKSAKTONG pareho ang grid at ang
     * guard. Kapag nagkaiba sila, babalik mismo ang bug na inaayos
     * dito: "Blocked" ang nakikita ni staff pero tinatanggap pa rin ng
     * booking form ang petsa.
     *
     * Kaya rin hindi kasama ang check-OUT: natatapos ang Night slot ng
     * 6AM kinabukasan, at ang pag-block sa araw na iyon ay hindi
     * nangangahulugang bawal na rin ang gabing nauna rito.
     */
    public function scopeCoveringDate($query, int $propertyId, $date)
    {
        $day = $date instanceof \Carbon\Carbon
            ? $date->copy()->startOfDay()
            : \Carbon\Carbon::parse($date)->startOfDay();

        return $query->where('property_id', $propertyId)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day);
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