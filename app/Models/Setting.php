<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $setting_key
 * @property string|null $setting_value
 * @property string $data_type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereSettingKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereSettingValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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