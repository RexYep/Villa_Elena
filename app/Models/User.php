<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, MustVerifyEmailTrait;

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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'last_login'         => 'datetime',
        'password'           => 'hashed',
        'status'             => 'integer',
        'two_factor_enabled' => 'boolean',
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
        return $this->profile_image
            ? Storage::disk('public')->url($this->profile_image)
            : null;
    }
}