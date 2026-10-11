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

    /**
     * Photo size, in pixels. The upload rule, its error message, the hint on
     * the admin forms and the warning on a preview all read these, so the
     * form never states a limit the server does not enforce.
     *
     * MIN_*: refused below this. Low on purpose: it only stops thumbnails and
     * icons, and a portrait phone photo (720×960) still passes.
     * SOFT_BELOW_WIDTH: accepted, but warned about. The villa page shows its
     * main photo up to about 890px wide, so anything narrower is enlarged.
     * RECOMMENDED_WIDTH: what the hint asks for; sharp in the full-size viewer
     * and on a high-density screen.
     */
    public const MIN_WIDTH = 600;
    public const MIN_HEIGHT = 400;
    public const SOFT_BELOW_WIDTH = 1000;
    public const RECOMMENDED_WIDTH = 1600;

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