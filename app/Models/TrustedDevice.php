<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string|null $device_label
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereDeviceLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereUserId($value)
 * @mixin \Eloquent
 */
class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'device_label',
        'ip_address',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
