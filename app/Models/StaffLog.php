<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'user_id'      => auth()->id(),
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