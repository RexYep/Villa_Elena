<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'setting_key';
    public $incrementing  = false;
    protected $keyType    = 'string';
    

    protected $fillable = [
        'setting_key',
        'setting_value',
        'data_type',
        'description',
    ];

    // ── Static helper to get/set settings easily ──────────────────

   public static function get(string $key, $default = null)
{
    $cached = Cache::remember("setting_{$key}", 3600, function () use ($key) {
        return static::where('setting_key', $key)->first();
    });

    return $cached?->setting_value ?? $default;
}

public static function set(string $key, $value): void
{
    static::updateOrCreate(
        ['setting_key' => $key],
        ['setting_value' => $value]
    );

    Cache::forget("setting_{$key}");
}
}