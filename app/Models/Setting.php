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
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereSettingKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereSettingValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Setting extends Model
{
    protected $primaryKey = 'setting_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'setting_key',
        'setting_value',
        'data_type',
        'description',
    ];

    /**
     * Every setting lives in ONE cache entry, fetched at most once per
     * request (Cache::memo()). The landing page alone asks for ~18
     * settings: that was 18 cache round-trips, and while Redis is down
     * each of them would wait out REDIS_TIMEOUT before falling back to
     * MySQL. See project.md v7.4.
     */
    public const CACHE_KEY = 'settings_all';

    // On the model rather than only in set(): AdminSeeder writes through
    // updateOrCreate() directly, and that must not leave the cache stale.
    protected static function booted(): void
    {
        static::saved(fn () => Cache::memo()->forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::memo()->forget(self::CACHE_KEY));
    }

    // ── Static helper to get/set settings easily ──────────────────

    public static function get(string $key, $default = null)
    {
        $settings = Cache::memo()->remember(self::CACHE_KEY, 3600, function () {
            return static::pluck('setting_value', 'setting_key')->all();
        });

        return $settings[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
    }
}
