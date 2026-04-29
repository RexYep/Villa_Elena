<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Notification;
use App\Models\StaffLog;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // ── Reviews List ───────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Review::with(['user', 'property', 'booking'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $reviews = $query->paginate(12)->withQueryString();

        // Stats
        $stats = [
            'total'    => Review::count(),
            'pending'  => Review::where('status', 'pending')->count(),
            'approved' => Review::where('status', 'approved')->count(),
            'rejected' => Review::where('status', 'rejected')->count(),
            'avg_rating' => round(Review::where('status', 'approved')->avg('rating'), 1),
        ];

        $properties = \App\Models\Property::orderBy('property_name')->get(['id', 'property_name']);

        return view('admin.reviews.index', compact('reviews', 'stats', 'properties'));
    }

    // ── Approve Review ─────────────────────────────────────────────
    public function approve(Review $review)
    {
        $review->update(['status' => 'approved']);

        // Notify guest
        Notification::create([
            'user_id' => $review->user_id,
            'type'    => 'in_app',
            'title'   => 'Your Review Was Approved!',
            'message' => "Your {$review->rating}-star review for {$review->property->property_name} has been approved and is now visible.",
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('review_approved', 'reviews', $review->id,
            "Approved review by {$review->user->full_name} for {$review->property->property_name}");

        return back()->with('success', '✅ Review approved and published.');
    }

    // ── Reject Review ──────────────────────────────────────────────
    public function reject(Request $request, Review $review)
    {
        $request->validate([
            'reject_reason' => 'nullable|string|max:300',
        ]);

        $review->update([
            'status'        => 'rejected',
            'admin_note'    => $request->reject_reason,
        ]);

        // Notify guest
        Notification::create([
            'user_id' => $review->user_id,
            'type'    => 'in_app',
            'title'   => 'Review Not Published',
            'message' => "Your review for {$review->property->property_name} was not published." .
                ($request->reject_reason ? " Reason: {$request->reject_reason}" : ''),
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('review_rejected', 'reviews', $review->id,
            "Rejected review by {$review->user->full_name}");

        return back()->with('success', 'Review has been rejected.');
    }

    // ── Reply to Review ────────────────────────────────────────────
    public function reply(Request $request, Review $review)
    {
        $request->validate([
            'reply' => 'required|string|min:5|max:500',
        ]);

        $review->update([
            'admin_reply'    => $request->reply,
            'admin_reply_at' => now(),
        ]);

        // Notify guest
        Notification::create([
            'user_id' => $review->user_id,
            'type'    => 'in_app',
            'title'   => 'Villa Elena Replied to Your Review',
            'message' => "The resort replied to your review for {$review->property->property_name}.",
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('review_replied', 'reviews', $review->id,
            "Replied to review by {$review->user->full_name}");

        return back()->with('success', '✅ Reply posted successfully.');
    }

    // ── Delete Review ──────────────────────────────────────────────
    public function destroy(Review $review)
    {
        StaffLog::record('review_deleted', 'reviews', $review->id,
            "Deleted review by {$review->user->full_name} for {$review->property->property_name}");

        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}