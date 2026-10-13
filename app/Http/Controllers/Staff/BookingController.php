<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * Looking a booking up from the frontdesk (v7.76).
 *
 * The frontdesk could only act on today's arrivals and the guest in the
 * villa. A guest who called about Saturday, or came to pay Saturday's
 * balance, could not be served: the Availability grid showed a name and a
 * reference and opened nothing.
 *
 * Read-only, with one exception: the details page carries the same Record
 * Payment form as the Today list, posting to the same
 * FrontDeskController::recordPayment(). Nothing here creates, moves or
 * cancels a booking; those stay with the walk-in form and the admin.
 */
class BookingController extends Controller
{
    /** Rows shown before the page says there are more. */
    private const LIST_LIMIT = 50;

    /** Shortest search worth running: one letter matches nearly everyone. */
    private const MIN_SEARCH = 2;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $searching = mb_strlen($search) >= self::MIN_SEARCH;

        $query = Booking::with('user');

        if ($searching) {
            // A search covers every booking, past and cancelled included:
            // "did this guest stay with us in August?" is a lookup too.
            // Newest stay first.
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function ($q) use ($like) {
                $q->where('booking_ref', 'like', $like)
                    ->orWhereHas('user', function ($guest) use ($like) {
                        $guest->where('full_name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
            })->orderByDesc('check_in_date')->orderByDesc('check_in_time');
        } else {
            // Without a search: what is still ahead, soonest first. A
            // booking counts until its check-out date has passed.
            $query->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->where('check_out_date', '>=', today())
                ->orderBy('check_in_date')
                ->orderBy('check_in_time');
        }

        // One more than is shown, to know whether to say "there are more".
        $bookings = $query->limit(self::LIST_LIMIT + 1)->get();
        $hasMore = $bookings->count() > self::LIST_LIMIT;

        return view('staff.bookings.index', [
            'bookings' => $bookings->take(self::LIST_LIMIT),
            'hasMore' => $hasMore,
            'limit' => self::LIST_LIMIT,
            'search' => $search,
            'searching' => $searching,
            'tooShort' => $search !== '' && ! $searching,
            'minSearch' => self::MIN_SEARCH,
        ]);
    }

    public function show(Booking $booking)
    {
        $booking->load(['user', 'property']);

        // `payment_date` is a date with no time, so same-day payments tie:
        // `id` settles the order (see CLAUDE.md).
        $payments = $booking->payments()->orderBy('payment_date')->orderBy('id')->get();

        // The same two tests as Payment::manualEntryProblem() opens with,
        // so the form is not offered for a payment that would be refused.
        $canRecordPayment = ! in_array($booking->status, ['cancelled', 'no_show'], true)
            && (float) $booking->balance_due > 0;

        // A refused payment comes back here with what was typed, in the
        // `payment` error bag (FrontDeskController::recordPayment()).
        $retry = session('payment_retry');
        $askSeparate = ($retry['booking'] ?? null) === $booking->id && ($retry['ask_separate'] ?? false);

        return view('staff.bookings.show', compact('booking', 'payments', 'canRecordPayment', 'askSeparate'));
    }
}
