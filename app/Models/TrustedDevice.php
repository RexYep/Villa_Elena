<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustedDevice whereUserId($value)
 * @mixin \Eloquent
 */
class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'device_label',
        'ip_address',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    /**
     * Turn a raw cookie token into what this table stores.
     *
     * SHA-256 rather than bcrypt on purpose: the raw value is 64 characters of
     * `Str::random()`, so there is nothing to guess and a slow hash would only
     * tax every login. See the 2026_09_24_100000 migration.
     *
     * Nothing outside this model should ever hash a token by hand — a second
     * copy of `hash('sha256', ...)` somewhere else is how the write side and
     * the read side drift apart, and the symptom would be every trusted device
     * silently asking for a code again.
     */
    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** Find the row for a raw cookie token, if it exists and is still live. */
    public function scopeActiveForToken($query, ?string $rawToken)
    {
        // No cookie means no device. Guard here rather than at every call site,
        // because a NULL falling through would compare against hash('') and
        // match whatever row happened to hold that value.
        if ($rawToken === null || $rawToken === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('token_hash', static::hashToken($rawToken))
            ->where('expires_at', '>', now());
    }

    /** Is this row the device presenting `$rawToken`? Used for the "This device" tag. */
    public function matchesToken(?string $rawToken): bool
    {
        if ($rawToken === null || $rawToken === '') {
            return false;
        }

        return hash_equals((string) $this->token_hash, static::hashToken($rawToken));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
