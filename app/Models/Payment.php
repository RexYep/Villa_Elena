<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'amount',
        'payment_method',
        'payment_type',
        'transaction_ref',
        'gateway_response',
        'status',
        'processed_by',
        'notes',
        'payment_date',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'payment_date'     => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function getMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'gcash'         => 'GCash',
            'paymaya'       => 'PayMaya',
            'card'          => 'Credit/Debit Card',
            'cash'          => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            default         => ucfirst($this->payment_method),
        };
    }
}