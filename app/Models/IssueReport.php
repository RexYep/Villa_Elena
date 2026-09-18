<?php

namespace App\Models;

use App\Models\Concerns\HasWorkStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Ulat ng problema sa villa — mula sa guest na naka-check-in o mula sa staff.
 *
 * Direktang dumarating sa staff frontdesk AT sa admin nang sabay: hindi
 * laging nakabukas ang admin portal, kaya ang pagdaan sa admin muna ay
 * magpapaantala sa pagkumpuni habang ang guest ay nasa villa.
 *
 * @property int $id
 * @property int $property_id
 * @property int|null $booking_id
 * @property int|null $reported_by
 * @property string $reporter_role
 * @property string $category
 * @property string|null $description
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking|null $booking
 * @property-read string $category_icon
 * @property-read string $category_label
 * @property-read string $guest_status_label
 * @property-read string $source_label
 * @property-read string $status_class
 * @property-read string $status_label
 * @property-read \App\Models\Property $property
 * @property-read \App\Models\User|null $reporter
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport closed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport open()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereReportedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereReporterRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IssueReport whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class IssueReport extends Model
{
    use HasWorkStatus;

    public const CATEGORIES = [
        'aircon'      => ['label' => 'Aircon',      'icon' => 'snow'],
        'plumbing'    => ['label' => 'Plumbing',    'icon' => 'droplet'],
        'electrical'  => ['label' => 'Electrical',  'icon' => 'lightning-charge'],
        'cleanliness' => ['label' => 'Cleanliness', 'icon' => 'stars'],
        'wifi'        => ['label' => 'Wi-Fi',       'icon' => 'wifi'],
        'pool'        => ['label' => 'Pool',        'icon' => 'water'],
        'other'       => ['label' => 'Other',       'icon' => 'three-dots'],
    ];

    /** Ang mga salitang nakikita ng guest — hindi "Pending"/"Cancelled". */
    public const GUEST_STATUS_LABELS = [
        'pending'     => 'Received',
        'in_progress' => 'Being fixed',
        'completed'   => 'Fixed',
        'cancelled'   => 'Closed',
    ];

    public const DESCRIPTION_MAX = 500;

    protected $fillable = [
        'property_id',
        'booking_id',
        'reported_by',
        'reporter_role',
        'category',
        'description',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Ang toast/notification ng guest kapag nagbago ang status ng ulat niya. */
    public const GUEST_STATUS_MESSAGES = [
        'in_progress' => 'Your :category issue is being fixed.',
        'completed'   => 'Your :category issue has been fixed.',
        'cancelled'   => 'Your :category report was closed.',
    ];

    protected static function booted(): void
    {
        // Ang bagong ulat ay lumalabas agad sa staff frontdesk at admin
        // Housekeeping — mula sa guest man o staff, anumang controller.
        static::created(fn () => static::touchLive());
    }

    /**
     * Sabihan ang mga bukas na page na kunin muli ang listahan, at ang
     * admin KPI refetch (sidebar badge ng Housekeeping). Minsan kada request.
     */
    public static function touchLive(): void
    {
        \App\Services\BroadcastOnce::dispatch('issue-reports', fn () => new \App\Events\IssueReportsChanged);
        \App\Services\DashboardStats::touch();
    }

    /**
     * Tumatakbo pagkatapos ng bawat matagumpay na markStarted/Completed/
     * Cancelled — mula sa staff, admin, o anumang daanan sa hinaharap.
     */
    protected function workStatusChanged(): void
    {
        static::touchLive();

        if (! $this->isFromGuest() || ! $this->reported_by || ! isset(self::GUEST_STATUS_MESSAGES[$this->status])) {
            return;
        }

        $message = str_replace(':category', $this->category_label, self::GUEST_STATUS_MESSAGES[$this->status]);
        [$userId, $reportId, $bookingId, $status] = [$this->reported_by, $this->id, (int) $this->booking_id, $this->status];

        // Pagkatapos ng response, at hindi kailanman naghahagis — ang
        // status ay nakaimbak na; ang abiso ay pangalawa lang.
        \App\Services\BroadcastOnce::dispatch("guest-issue-{$reportId}-{$status}", function () use ($userId, $reportId, $bookingId, $status, $message) {
            \App\Helpers\NotificationHelper::notifyGuest(
                $userId,
                'Issue report update',
                $message,
                $bookingId ? route('customer.bookings.show', $bookingId, false) . '#report-issue' : null,
            );

            return new \App\Events\GuestIssueReportUpdated($userId, $reportId, $bookingId, $status, $message);
        });
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? ucfirst((string) $this->category);
    }

    public function getCategoryIconAttribute(): string
    {
        return self::CATEGORIES[$this->category]['icon'] ?? 'exclamation-circle';
    }

    public function getGuestStatusLabelAttribute(): string
    {
        return self::GUEST_STATUS_LABELS[$this->status] ?? $this->status_label;
    }

    public function isFromGuest(): bool
    {
        return $this->reporter_role === 'customer';
    }

    /** "Guest Maria Santos (VE-ABC123)" o "Staff". */
    public function getSourceLabelAttribute(): string
    {
        if (! $this->isFromGuest()) {
            return 'Staff';
        }

        $name = $this->reporter->full_name ?? 'Guest';

        return $this->booking ? "Guest {$name} ({$this->booking->booking_ref})" : "Guest {$name}";
    }

    /**
     * Validation rules — pinagsasaluhan ng guest form at ng staff form para
     * hindi magkaiba ang dalawa. "Other" ay walang kahulugan kung walang
     * paglalarawan, kaya kailangan lang doon.
     */
    public static function rules(): array
    {
        return [
            'category'    => 'required|in:' . implode(',', array_keys(self::CATEGORIES)),
            'description' => 'nullable|required_if:category,other|string|max:' . self::DESCRIPTION_MAX,
        ];
    }

    public static function messages(): array
    {
        return [
            'category.required'       => 'Please choose what kind of problem it is.',
            'description.required_if' => 'Please describe the problem when you choose "Other".',
        ];
    }
}
