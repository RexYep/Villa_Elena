<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\StaffLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\NotificationHelper;
use App\Events\PaymentReceived;

class PaymentController extends Controller
{
    // ── Payments List ──────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Payment::with(['booking.user', 'booking.property'])
            ->latest('payment_date');

        // Filters
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }
        if ($request->filled('type')) {
            $query->where('payment_type', $request->type);
        }
        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('booking', function ($q) use ($search) {
                $q->where('booking_ref', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('full_name', 'like', "%{$search}%"));
            });
        }

        $payments = $query->paginate(15)->withQueryString();

        // Summary stats
        $totalRevenue   = Payment::where('payment_type', '!=', 'refund')->sum('amount');
        $todayRevenue   = Payment::where('payment_type', '!=', 'refund')
            ->whereDate('payment_date', today())->sum('amount');
        $monthRevenue   = Payment::where('payment_type', '!=', 'refund')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date',  now()->year)->sum('amount');
        $totalRefunds   = Payment::where('payment_type', 'refund')->sum('amount');
        $pendingBalance = Booking::whereNotIn('status', ['cancelled', 'checked_out'])
            ->sum('balance_due');

        return view('admin.payments.index', compact(
            'payments', 'totalRevenue', 'todayRevenue',
            'monthRevenue', 'totalRefunds', 'pendingBalance'
        ));
    }

    // ── Payment Detail ─────────────────────────────────────────────
    public function show(Payment $payment)
    {
        $payment->load(['booking.user', 'booking.property', 'booking.payments']);
        return view('admin.payments.show', compact('payment'));
    }

    // ── Record Manual Payment ──────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'booking_id'     => 'required|exists:bookings,id',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required',
            'payment_type'   => 'required',
            'payment_date'   => 'required|date',
            'notes'          => 'nullable|string|max:300',
        ]);

        $booking = Booking::findOrFail($request->booking_id);

    $payment = Payment::create([
    'booking_id'     => $booking->id,
    'amount'         => $request->amount,
    'payment_method' => $request->payment_method,
    'payment_type'   => $request->payment_type,
    'status'         => 'success',
    'payment_date'   => $request->payment_date,
    'received_by'    => auth()->id(),
    'notes'          => $request->notes,
]);
        event(new PaymentReceived($payment));

         NotificationHelper::paymentRecorded($booking, $request->amount, $request->payment_method);

        // Recalculate booking
        $this->recalculateBooking($booking);

        StaffLog::record('payment_recorded', 'bookings', $booking->id,
            "Manual payment ₱{$request->amount} recorded for {$booking->booking_ref}");

        return back()->with('success', "✅ Payment of ₱" . number_format($request->amount, 2) . " recorded for {$booking->booking_ref}.");
    }

    // ── Issue Refund ───────────────────────────────────────────────
    public function refund(Request $request, Payment $payment)
    {
        $request->validate([
            'refund_amount' => 'required|numeric|min:1',
            'refund_reason' => 'required|string|min:5',
        ]);

        $booking = $payment->booking;

        // Naka-wrap sa transaction + lockForUpdate() para hindi ma-double
        // process ang refund (double-click, retry dahil sa slow network,
        // atbp.) — kinukumpara ang bagong refund laban sa AKTWAL na
        // natitirang refundable balance ng BUONG booking (total paid minus
        // total naunang na-refund), hindi lang sa orihinal na halaga ng
        // isang Payment record.
        $refundPayment = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $payment, $booking) {

            $totalPaid = Payment::where('booking_id', $booking->id)
                ->where('payment_type', '!=', 'refund')
                ->lockForUpdate()
                ->sum('amount');

            $totalRefunded = Payment::where('booking_id', $booking->id)
                ->where('payment_type', 'refund')
                ->lockForUpdate()
                ->sum('amount');

            $refundableBalance = $totalPaid - $totalRefunded;

            if ($request->refund_amount > $refundableBalance) {
                return null; // signal na lumampas sa refundable balance
            }

            $newRefund = Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $request->refund_amount,
                'payment_method' => $payment->payment_method,
                'payment_type'   => 'refund',
                'status'         => 'success',
                'payment_date'   => today(),
                'received_by'    => auth()->id(),
                'notes'          => "Refund: {$request->refund_reason}",
            ]);

            event(new PaymentReceived($newRefund));

            NotificationHelper::refundIssued($booking, $request->refund_amount, $request->refund_reason);

            Notification::create([
                'user_id' => $booking->user_id,
                'type'    => 'in_app',
                'title'   => 'Refund Processed',
                'message' => "A refund of ₱" . number_format($request->refund_amount, 2) .
                    " has been processed for booking {$booking->booking_ref}. Reason: {$request->refund_reason}",
                'link'    => route('customer.bookings.show', $booking, false),
                'is_read' => 0,
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            StaffLog::record('refund_issued', 'payments', $payment->id,
                "Refund ₱{$request->refund_amount} for booking {$booking->booking_ref}. Reason: {$request->refund_reason}");

            return $newRefund;
        });

        if (!$refundPayment) {
            return back()->with('error', 'The refund amount exceeds the remaining refundable balance for this booking. Please check the payment history first.');
        }

        // Recalculate (sa labas ng transaction, hindi problema dahil
        // consistent na ang Payment records nung nagsara na ang transaction)
        $this->recalculateBooking($booking);

        return back()->with('success', "✅ Refund of ₱" . number_format($request->refund_amount, 2) . " processed.");
    }

    // ── Helper: Recalculate booking financials ─────────────────────
    private function recalculateBooking(Booking $booking): void
    {
        $totalPaid  = Payment::where('booking_id', $booking->id)
            ->where('payment_type', '!=', 'refund')->sum('amount');
        $totalRefund = Payment::where('booking_id', $booking->id)
            ->where('payment_type', 'refund')->sum('amount');
        $netPaid    = max(0, $totalPaid - $totalRefund);
        $balanceDue = max(0, $booking->total_amount - $netPaid);

        $paymentStatus = 'unpaid';
        if ($netPaid >= $booking->total_amount) $paymentStatus = 'paid';
        elseif ($netPaid > 0)                   $paymentStatus = 'partial';

        $booking->update([
            'amount_paid'    => $netPaid,
            'balance_due'    => $balanceDue,
            'payment_status' => $paymentStatus,
        ]);
    }
}