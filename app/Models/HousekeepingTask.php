<?php

namespace App\Models;

use App\Models\Concerns\HasWorkStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
 * @property int|null $created_by
 * @property string|null $title
 * @property string|null $location
 * @property string $priority
 * @property string|null $due_time
 * @property Carbon|null $started_at
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Support\Carbon|null $due_at
 * @property-read string $due_label
 * @property-read string $due_tone
 * @property-read string $headline
 * @property-read string $status_class
 * @property-read string $status_label
 * @property-read string $type_icon
 * @property-read string $type_label
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask closed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask open()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereDueTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HousekeepingTask whereTitle($value)
 * @mixin \Eloquent
 */
class HousekeepingTask extends Model
{
    use HasFactory, HasWorkStatus;

    /**
     * Mga uri ng task na pinipili ng admin. Ang `checkout_clean` ay dating
     * awtomatiko; nananatili ito dahil paglilinis pagkatapos ng checkout ay
     * nananatiling karaniwang utos — ang admin na lang ang nagpapadala.
     */
    public const TYPES = [
        'checkout_clean' => ['label' => 'Post-checkout cleaning', 'icon' => 'brush'],
        'daily_clean'    => ['label' => 'Cleaning',               'icon' => 'stars'],
        'maintenance'    => ['label' => 'Repair / maintenance',   'icon' => 'tools'],
        'inspection'     => ['label' => 'Inspection',             'icon' => 'clipboard-check'],
    ];

    public const PRIORITIES = ['normal' => 'Normal', 'urgent' => 'Urgent'];

    protected $fillable = [
        'property_id',
        'assigned_to',
        'created_by',
        'booking_id',
        'task_type',
        'title',
        'location',
        'priority',
        'due_date',
        'due_time',
        'status',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'started_at'   => 'datetime',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->task_type]['label'] ?? ucfirst(str_replace('_', ' ', $this->task_type));
    }

    public function getTypeIconAttribute(): string
    {
        return self::TYPES[$this->task_type]['icon'] ?? 'list-task';
    }

    /** Ang pamagat, o ang uri kung walang pamagat (mga lumang awtomatikong task). */
    public function getHeadlineAttribute(): string
    {
        return $this->title ?: $this->type_label;
    }

    /**
     * Ang totoong deadline. Walang oras → dulo ng araw, para ang task na
     * "due today" ay hindi overdue simula alas-dose ng madaling-araw.
     */
    public function getDueAtAttribute(): ?Carbon
    {
        if (! $this->due_date) {
            return null;
        }

        return $this->due_time
            ? Carbon::parse($this->due_date->format('Y-m-d') . ' ' . $this->due_time)
            : $this->due_date->copy()->endOfDay();
    }

    public function getDueLabelAttribute(): string
    {
        if (! $this->due_date) {
            return '—';
        }

        return $this->due_time
            ? $this->due_at->format('M j, g:i A')
            : $this->due_date->format('M j') . ' (any time)';
    }

    /** `late` · `soon` (≤ 4 oras) · `ok` — para sa kulay ng deadline. */
    public function getDueToneAttribute(): string
    {
        if (! $this->isOpen() || ! $this->due_at) {
            return 'ok';
        }

        $minutes = now()->diffInMinutes($this->due_at, false);

        return $minutes < 0 ? 'late' : ($minutes <= 240 ? 'soon' : 'ok');
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at?->isPast();
    }
}
