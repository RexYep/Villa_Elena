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

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}