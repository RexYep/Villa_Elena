<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action e.g. created_booking, updated_property, checked_in_guest
 * @property string|null $target_table
 * @property int|null $target_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $old_values Data before the change
 * @property array<array-key, mixed>|null $new_values Data after the change
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereTargetTable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUserId($value)
 * @mixin \Eloquent
 */
class StaffLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'target_table',
        'target_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Static helper to quickly log any action ────────────────────

    public static function record(
        string $action,
        string $targetTable = null,
        int $targetId = null,
        string $description = null,
        array $oldValues = null,
        array $newValues = null
    ): void {
        static::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'target_table' => $targetTable,
            'target_id'    => $targetId,
            'description'  => $description,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);
    }
}