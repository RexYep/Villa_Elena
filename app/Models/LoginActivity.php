<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
