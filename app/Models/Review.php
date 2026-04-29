<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
     'booking_id',
     'user_id',
     'property_id',
     'rating',
     'title',
     'content',
     'status',           // pending, approved, rejected
     'admin_reply',
     'admin_reply_at',
     'admin_note',       // rejection reason
 ];

    protected $casts = [
        'overall_rating' => 'integer',
        'cleanliness'    => 'integer',
        'service'        => 'integer',
        'value'          => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getStarsHtmlAttribute(): string
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $stars .= $i <= $this->overall_rating
                ? '<i class="bi bi-star-fill text-warning"></i>'
                : '<i class="bi bi-star text-warning"></i>';
        }
        return $stars;
    }
}