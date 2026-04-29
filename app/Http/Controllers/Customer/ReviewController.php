<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // ── Submit Review Form ─────────────────────────────────────────
    public function create(Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);
        abort_if($booking->status !== 'checked_out', 403, 'You can only review after checkout.');

        // Check if already reviewed
        $existing = Review::where('booking_id', $booking->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            return redirect()->route('customer.bookings.show', $booking)
                ->with('info', 'You have already submitted a review for this booking.');
        }

        $booking->load('property');
        return view('customer.review_form', compact('booking'));
    }

    // ── Store Review ───────────────────────────────────────────────
    public function store(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);
        abort_if($booking->status !== 'checked_out', 403);

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'title'   => 'required|string|max:100',
            'content' => 'required|string|min:20|max:1000',
        ]);

        // Prevent duplicate
        $existing = Review::where('booking_id', $booking->id)
            ->where('user_id', auth()->id())
            ->first();
        if ($existing) {
            return redirect()->route('customer.bookings.show', $booking)
                ->with('info', 'You have already submitted a review.');
        }

        Review::create([
            'booking_id'  => $booking->id,
            'user_id'     => auth()->id(),
            'property_id' => $booking->property_id,
            'rating'      => $request->rating,
            'title'       => $request->title,
            'content'     => $request->content,
            'status'      => 'pending',
        ]);

        // Notify admin
        NotificationHelper::notifyAdmin(
            'New Review Submitted',
            auth()->user()->full_name . " submitted a {$request->rating}-star review for {$booking->property->property_name}. Pending approval."
        );

        return redirect()->route('customer.bookings.show', $booking)
            ->with('success', '⭐ Thank you for your review! It will be published after approval.');
    }
}