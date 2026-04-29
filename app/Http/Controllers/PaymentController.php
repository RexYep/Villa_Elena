<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\StaffLog;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;

class PaymentController extends Controller
{
    public function __construct(private PayMongoService $paymongo) {}

    // ── Guest initiates payment from customer portal ───────────────
    // GET /pay/{booking}
    public function showPaymentPage(Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);
        abort_if($booking->balance_due <= 0, 400, 'No balance due for this booking.');
        abort_if(in_array($booking->status, ['cancelled', 'checked_out']), 400, 'Cannot pay for this booking.');

        $booking->load('property');

        // Deposit amount from settings
        $depositPct    = (float) \App\Models\Setting::get('deposit_percentage', 30);
        $depositAmount = round($booking->total_amount * $depositPct / 100, 2);
        $isDepositOnly = $booking->amount_paid == 0; // first payment = deposit

        return view('payment.checkout', compact('booking', 'depositAmount', 'isDepositOnly', 'depositPct'));
    }

    // ── Create PayMongo Checkout Session ──────────────────────────
    // POST /pay/{booking}/checkout
    public function createCheckout(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);

        $request->validate([
            'payment_type' => 'required|in:deposit,full_payment',
        ]);

        $booking->load(['property', 'user']);

        $depositPct    = (float) \App\Models\Setting::get('deposit_percentage', 30);
        $depositAmount = round($booking->total_amount * $depositPct / 100, 2);

        $amount      = $request->payment_type === 'deposit' ? $depositAmount : $booking->balance_due;
        $description = $request->payment_type === 'deposit'
            ? "Deposit ({$depositPct}%) for {$booking->property->property_name} — {$booking->booking_ref}"
            : "Full balance for {$booking->property->property_name} — {$booking->booking_ref}";

        try {
            $session = $this->paymongo->createCheckoutSession([
                'amount'           => $amount,
                'description'      => $description,
                'guest_name'       => $booking->user->full_name,
                'guest_email'      => $booking->user->email,
                'guest_phone'      => $booking->user->phone ?? '',
                'reference_number' => $booking->booking_ref,
                'booking_id'       => $booking->id,
                'payment_type'     => $request->payment_type,
                'success_url' => route('payment.success', $booking->id),
                'cancel_url'       => route('payment.cancel',  $booking->id),
            ]);

            // Store session ID in booking for verification later
            $booking->update([
                'paymongo_session_id' => $session['id'],
                'paymongo_payment_type' => $request->payment_type,
            ]);

            // Redirect to PayMongo hosted checkout page
            $checkoutUrl = $session['attributes']['checkout_url'];
            return redirect($checkoutUrl);

        } catch (\Exception $e) {
            return back()->with('error', 'Payment gateway error: ' . $e->getMessage());
        }
    }

    // ── Success Callback ───────────────────────────────────────────
    // GET /pay/{booking}/success
   public function success(Request $request, Booking $booking)
{
    abort_if($booking->user_id !== auth()->id(), 403);
    $booking->load(['property', 'user']);

    // Get session ID from booking record (stored during createCheckout)
    $sessionId = $booking->paymongo_session_id;

    if (!$sessionId) {
        // Already processed or no session — just show current state
        return view('payment.success', [
            'booking'     => $booking,
            'amountPaid'  => $booking->amount_paid,
            'paymentType' => $booking->paymongo_payment_type ?? 'payment',
        ]);
    }

    try {
        
        $session    = $this->paymongo->getCheckoutSession($sessionId);
        $attributes = $session['attributes'];

            // ADD THIS — log the full response para makita natin
 
    
    $attributes = $session['attributes'];
    $status     = $attributes['status'] ?? '';
    $amountPaid = ($attributes['line_items'][0]['amount'] ?? 0) / 100;
    

        // PayMongo checkout session status
        $status = $attributes['status'] ?? '';

        // Amount from line_items
        $amountPaid  = ($attributes['line_items'][0]['amount'] ?? 0) / 100;
        $paymentType = $booking->paymongo_payment_type ?? 'deposit';

        // Get payment reference from payments array
        $payments   = $attributes['payments'] ?? [];
        $paymentRef = !empty($payments)
            ? ($payments[0]['id'] ?? $session['id'])
            : $session['id'];

        if (in_array($status, ['paid', 'active']) && $amountPaid > 0) {

            // Avoid duplicate
            $alreadyRecorded = Payment::where('booking_id', $booking->id)
                ->where('reference_number', $paymentRef)
                ->exists();

            if (!$alreadyRecorded) {
                Payment::create([
                    'booking_id'       => $booking->id,
                    'amount'           => $amountPaid,
                    'payment_method' => $attributes['payment_method_used'] ?? 'gcash',
                    'payment_type'     => $paymentType,
                    'payment_date'     => today(),
                    'reference_number' => $paymentRef,
                    'notes'            => 'PayMongo online payment',
                ]);

                NotificationHelper::paymentReceived(
            $booking->fresh(),
            $amountPaid,
            $attributes['payment_method_used'] ?? 'online'
        );
 

                // Recalculate
                $totalPaid  = Payment::where('booking_id', $booking->id)
                    ->where('payment_type', '!=', 'refund')
                    ->sum('amount');
                $balanceDue    = max(0, $booking->total_amount - $totalPaid);
                $paymentStatus = $totalPaid >= $booking->total_amount ? 'paid' : 'partial';

                $booking->update([
                    'amount_paid'         => $totalPaid,
                    'balance_due'         => $balanceDue,
                    'payment_status'      => $paymentStatus,
                    'paymongo_session_id' => null,
                ]);

                // Auto-confirm pending bookings
                if ($booking->status === 'pending') {
                    $booking->update(['status' => 'confirmed']);
                }

                // Notify guest
                Notification::create([
                    'user_id' => $booking->user_id,
                    'type'    => 'in_app',
                    'title'   => 'Payment Received!',
                    'message' => "Payment of ₱" . number_format($amountPaid, 2) . 
                        " for booking {$booking->booking_ref} confirmed.",
                    'is_read' => 0,
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
            }

            $booking->refresh();
        }

        return view('payment.success', compact('booking', 'amountPaid', 'paymentType'));

    } catch (\Exception $e) {

        // Fallback — show current booking state
        return view('payment.success', [
            'booking'    => $booking,
            'amountPaid' => $booking->amount_paid,
            'paymentType'=> 'payment',
        ]);
    }
}

    // ── Cancel Callback ────────────────────────────────────────────
    // GET /pay/{booking}/cancel
    public function cancel(Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);
        $booking->update(['paymongo_session_id' => null]);
        return redirect()->route('customer.bookings.show', $booking)
            ->with('error', 'Payment was cancelled. Your booking is still reserved — you can try again anytime.');
    }

    // ── PayMongo Webhook ───────────────────────────────────────────
    // POST /webhooks/paymongo  (no auth middleware)
    public function webhook(Request $request)
    {
        $payload   = $request->getContent();
        $signature = $request->header('Paymongo-Signature', '');

        // Verify signature (optional in sandbox)
        // if (!$this->paymongo->verifyWebhook($payload, $signature)) {
        //     return response()->json(['error' => 'Invalid signature'], 401);
        // }

        $data      = $request->json('data');
        $eventType = $data['attributes']['type'] ?? '';

        if ($eventType === 'payment.paid') {
            $paymentData = $data['attributes']['data']['attributes'] ?? [];
            $metadata    = $paymentData['metadata'] ?? [];
            $bookingId   = $metadata['booking_id'] ?? null;

            if ($bookingId) {
                $booking = Booking::find($bookingId);
                if ($booking && $booking->balance_due > 0) {
                    $amount = ($paymentData['amount'] ?? 0) / 100;
                    // Payment already handled in success callback typically
                    // This is a fallback for missed callbacks
                    \Log::info("PayMongo webhook: payment.paid for booking #{$bookingId}, amount ₱{$amount}");
                }
            }
        }

        return response()->json(['received' => true]);
    }
}