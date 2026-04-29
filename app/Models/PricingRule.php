<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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