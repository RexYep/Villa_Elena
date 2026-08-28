<?php
// ─────────────────────────────────────────────
// FILE: app/Models/BookingExtra.php
// ─────────────────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $item_name
 * @property string|null $description
 * @property int $quantity
 * @property numeric $unit_price
 * @property numeric $total
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking|null $booking
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingExtra whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BookingExtra extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'item_name',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}