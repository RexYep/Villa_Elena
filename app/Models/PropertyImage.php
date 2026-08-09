<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        return Storage::disk('public')->url($this->image_path);
    }
}