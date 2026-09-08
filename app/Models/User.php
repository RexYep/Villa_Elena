<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $full_name
 * @property string|null $email
 * @property string $password
 * @property string|null $phone
 * @property string $role
 * @property string|null $profile_image
 * @property string|null $address
 * @property string|null $id_type
 * @property string|null $id_number
 * @property int $status 1=Active, 0=Deactivated
 * @property bool $two_factor_enabled
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $last_login
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read string|null $profile_image_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HousekeepingTask> $housekeepingTasks
 * @property-read int|null $housekeeping_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoginActivity> $loginActivities
 * @property-read int|null $login_activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StaffLog> $staffLogs
 * @property-read int|null $staff_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TrustedDevice> $trustedDevices
 * @property-read int|null $trusted_devices_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfileImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @property bool $email_notifications_enabled
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailNotificationsEnabled($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'role',
        'profile_image',
        'address',
        'id_type',
        'id_number',
        'status',
        'last_login',
        'two_factor_enabled',
        'email_notifications_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'password' => 'hashed',
        'status' => 'integer',
        'two_factor_enabled' => 'boolean',
        'email_notifications_enabled' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function staffLogs()
    {
        return $this->hasMany(StaffLog::class);
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class, 'assigned_to');
    }

    public function trustedDevices()
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class);
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }

    public function getFullNameAttribute($value): string
    {
        return ucwords($value);
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        // Dating `Storage::disk('public')->url()` na nakabalot sa
        // try/catch. Ang problema ay hindi ang catch kundi ang tawag:
        // sa Cloudinary, ang url() ay isang LIVE na Admin API call kada
        // larawan kada render, at 500/oras lang ang libreng quota — kaya
        // "Rate Limit Exceeded" at nawawala ang lahat ng larawan sa
        // buong site hanggang sa susunod na oras. Tingnan ang
        // MediaUrlHelper.
        return \App\Helpers\MediaUrlHelper::resolve($this->profile_image);
    }
}
