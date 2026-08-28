<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $property_id
 * @property int|null $assigned_to
 * @property int|null $booking_id
 * @property string $task_type
 * @property string|null $scheduled_date
 * @property \Illuminate\Support\Carbon $due_date
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $assignedTo
 * @property-read \App\Models\Booking|null $booking
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereScheduledDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereTaskType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HousekeepingTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'assigned_to',
        'booking_id',
        'task_type',
        'due_date',
        'status',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }
}