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

    /**
     * The resort's name: Admin → Settings → "Resort Name".
     *
     * Every place that prints the name in full reads it here (emails and
     * their sender name, the landing page, the sign-in pages, checkout, the
     * legal pages, the chatbot). Three spellings were in use before, each
     * typed where it was printed.
     *
     * `APP_NAME` is the fallback, for a blank setting and for a database
     * that cannot be read. The second matters: the error pages are printed
     * in the sign-in layout, which shows this name, and an error page that
     * needs the database fails exactly when the database is the error.
     */
    public static function resortName(): string
    {
        try {
            $name = trim((string) self::get('resort_name'));
        } catch (\Throwable) {
            $name = '';
        }

        return $name !== '' ? $name : (string) config('app.name');
    }

    /**
     * The name on two lines, for the wordmarks that set it that way (the
     * landing page's heading, the sign-in pages): the first two words, then
     * the rest. The second line is empty for a name of one or two words.
     *
     * @return array{0: string, 1: string}
     */
    public static function resortNameLines(): array
    {
        $words = preg_split('/\s+/', self::resortName(), -1, PREG_SPLIT_NO_EMPTY);

        return [
            implode(' ', array_slice($words, 0, 2)),
            implode(' ', array_slice($words, 2)),
        ];
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
    }
}
