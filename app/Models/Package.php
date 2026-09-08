<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property numeric $price
 * @property array<array-key, mixed>|null $inclusions JSON array of what is included
 * @property int $validity_days
 * @property int $max_guests
 * @property string|null $image_path
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $image_url
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereInclusions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereMaxGuests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereValidityDays($value)
 * @mixin \Eloquent
 */
class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'inclusions',
        'validity_days',
        'max_guests',
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'inclusions' => 'array',
        'is_active'  => 'boolean',
    ];

    public function getImageUrlAttribute(): string
    {
        // Tingnan ang MediaUrlHelper — dating isang live na Cloudinary
        // Admin API call ito kada larawan kada render.
        return \App\Helpers\MediaUrlHelper::resolve($this->image_path)
            ?? asset('images/default-package.jpg');
    }
}