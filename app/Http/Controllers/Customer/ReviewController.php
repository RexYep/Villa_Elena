<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Helpers\NotificationHelper;
use App\Services\ReviewModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // ── My Reviews List ─────────────────────────────────────────────
    public function index()
    {
        $reviews = Review::where('user_id', Auth::id())
            ->with('booking.property')
            ->latest()
            ->get();

        return view('customer.reviews', compact('reviews'));
    }

    // ── Edit Review Form ────────────────────────────────────────────
    public function edit(Review $review)
    {
        abort_if($review->user_id !== Auth::id(), 403);

        $booking = $review->booking->load('property');

        return view('customer.review_form', compact('booking', 'review'));
    }

    // ── Update Review ───────────────────────────────────────────────
    public function update(Request $request, Review $review, ReviewModerationService $moderation)
    {
        abort_if($review->user_id !== Auth::id(), 403);

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'title'   => 'required|string|max:100',
            'content' => 'required|string|min:20|max:1000',
        ]);

        $data = [
            'rating'  => $request->rating,
            'title'   => $request->title,
            'content' => $request->content,
        ];

        // Content changed — re-run automated moderation rather than blindly
        // demoting a previously-approved review back to pending.
        $result = $moderation->evaluate($request->title, $request->content);

        $data['status']      = $result['approved'] ? 'approved' : 'pending';
        $data['flag_reason'] = $result['reason'];

        if (!$result['approved']) {
            $data['admin_reply']    = null;
            $data['admin_reply_at'] = null;
            $data['admin_note']     = null;

            if ($result['reason']) {
                NotificationHelper::notifyAdmin(
                    'Edited Review Auto-Flagged',
                    Auth::user()->full_name . " edited their review for {$review->booking->property->property_name} — held for manual review ({$result['reason']}).",
                    route('admin.reviews.index', [], false)
                );
            }
        }

        $review->update($data);

        $message = $result['approved']
            ? 'Review updated and is live.'
            : 'Review updated — it will be reviewed again before it goes live.';

        return redirect()->route('customer.reviews.index')->with('success', $message);
    }

    // ── Delete Review ───────────────────────────────────────────────
    public function destroy(Review $review)
    {
        abort_if($review->user_id !== Auth::id(), 403);

        $review->delete();

        return redirect()->route('customer.reviews.index')
            ->with('success', 'Review deleted.');
    }

    // ── Submit Review Form ─────────────────────────────────────────
    public function create(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        abort_if($booking->status !== 'checked_out', 403, 'You can only review after checkout.');

        // Check if already reviewed
        $existing = Review::where('booking_id', $booking->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            return redirect()->route('customer.bookings.show', $booking)
                ->with('info', 'You have already submitted a review for this booking.');
        }

        $booking->load('property');
        return view('customer.review_form', compact('booking'));
    }

    // ── Store Review ───────────────────────────────────────────────
    public function store(Request $request, Booking $booking, ReviewModerationService $moderation)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        abort_if($booking->status !== 'checked_out', 403);

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'title'   => 'required|string|max:100',
            'content' => 'required|string|min:20|max:1000',
        ]);

        // Prevent duplicate
        $existing = Review::where('booking_id', $booking->id)
            ->where('user_id', Auth::id())
            ->first();
        if ($existing) {
            return redirect()->route('customer.bookings.show', $booking)
                ->with('info', 'You have already submitted a review.');
        }

        $result = $moderation->evaluate($request->title, $request->content);

        Review::create([
            'booking_id'  => $booking->id,
            'user_id'     => Auth::id(),
            'property_id' => $booking->property_id,
            'rating'      => $request->rating,
            'title'       => $request->title,
            'content'     => $request->content,
            'status'      => $result['approved'] ? 'approved' : 'pending',
            'flag_reason' => $result['reason'],
        ]);

        if (!$result['approved']) {
            NotificationHelper::notifyAdmin(
                $result['reason'] ? 'New Review Auto-Flagged' : 'New Review Submitted',
                Auth::user()->full_name . " submitted a {$request->rating}-star review for {$booking->property->property_name}. " .
                ($result['reason'] ? "Held for manual review ({$result['reason']})." : 'Pending approval.'),
                route('admin.reviews.index', [], false)
            );
        }

        $message = $result['approved']
            ? '⭐ Thank you for your review!'
            : '⭐ Thank you for your review! It will be published after a quick review.';

        return redirect()->route('customer.bookings.show', $booking)
            ->with('success', $message);
    }
}