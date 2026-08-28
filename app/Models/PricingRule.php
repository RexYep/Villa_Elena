<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $property_id
 * @property string $label e.g. Christmas Rate, Summer Rate
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property numeric $price
 * @property string $type
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Property $property
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PricingRule whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'label',
        'start_date',
        'end_date',
        'price',
        'type',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'price'      => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}