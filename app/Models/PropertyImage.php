<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'image_path',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }
}