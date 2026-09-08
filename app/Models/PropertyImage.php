<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $property_id
 * @property string $image_path
 * @property string|null $alt_text
 * @property bool $is_primary
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $url
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereAltText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyImage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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

    public function getUrlAttribute(): ?string
    {
        // Ito ang pinakamabigat sa tatlong accessor: isang gallery ng
        // villa ay anim hanggang walong larawan, at dating isang
        // Cloudinary Admin API call ang bawat isa — kada page load.
        // Tingnan ang MediaUrlHelper kung bakit lokal na ang pagbuo.
        return \App\Helpers\MediaUrlHelper::resolve($this->image_path);
    }
}