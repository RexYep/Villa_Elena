<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $device_label
 * @property bool $via_new_device_otp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity whereDeviceLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginActivity whereViaNewDeviceOtp($value)
 * @mixin \Eloquent
 */
class LoginActivity extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'device_label',
        'via_new_device_otp',
    ];

    protected $casts = [
        'via_new_device_otp' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
