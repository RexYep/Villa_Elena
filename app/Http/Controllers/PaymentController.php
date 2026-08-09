<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\StaffLog;
use App\Services\PayMongoService;
use App\Mail\BookingConfirmedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        // Deposit amount from settings — minimum 50% ngayon (dating 30%).
        $depositPct    = (float) \App\Models\Setting::get('deposit_percentage', 50);
        $depositAmount = round($booking->total_amount * $depositPct / 100, 2);
        $isDepositOnly = $booking->amount_paid == 0; // first payment = deposit

        // Anti-abuse: kung 3+ na ang cancellation ng guest na ito sa
        // loob ng 30 araw, full payment na lang ang pinapayagan —
        // walang deposit option, para hindi na sila makapag-hold ng
        // slot nang mura lang tapos ica-cancel din lang pala ulit.
        $forceFullPayment = $isDepositOnly && Booking::hasExcessiveCancellations($booking->user_id);

        return view('payment.checkout', compact('booking', 'depositAmount', 'isDepositOnly', 'depositPct', 'forceFullPayment'));
    }

    // ── Create PayMongo Checkout Session ──────────────────────────
    // POST /pay/{booking}/checkout
    public function createCheckout(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);

        $request->validate([
            'payment_type' => 'required|in:deposit,full_payment',
        ]);

        // Anti-abuse: server-side re-check (hindi lang basta umaasa sa
        // UI) — kung naka-flag ang guest sa excessive cancellations,
        // hindi papayagang "deposit" ang piliin kahit i-bypass ang form.
        if ($request->payment_type === 'deposit'
            && $booking->amount_paid == 0
            && Booking::hasExcessiveCancellations($booking->user_id)) {
            return back()->with('error', 'Dahil sa cancellation history mo, kailangan ng full payment para sa booking na ito — hindi available ang deposit option.');
        }

        $booking->load(['property', 'user']);

        $depositPct    = (float) \App\Models\Setting::get('deposit_percentage', 50);
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
                    'status'           => 'success',
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

                // Auto-confirm pending bookings — WALANG admin approval
                // step. Kapag successful ang unang bayad (deposit o full),
                // automatic nang "confirmed" ang booking, at doon din
                // ipapadala ang confirmation email.
                $wasPending = $booking->status === 'pending';

                if ($wasPending) {
                    $booking->update(['status' => 'confirmed']);
                }

                // Notify guest (in-app)
                Notification::create([
                    'user_id' => $booking->user_id,
                    'type'    => 'in_app',
                    'title'   => 'Payment Received!',
                    'message' => "Payment of ₱" . number_format($amountPaid, 2) .
                        " for booking {$booking->booking_ref} confirmed.",
                    'link'    => route('customer.bookings.show', $booking, false),
                    'is_read' => 0,
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);

                // Confirmation Email — ipinapadala LANG kapag ito yung
                // unang beses na naging "confirmed" ang booking (hindi
                // kada partial/balance payment pagkatapos).
                if ($wasPending) {
                    try {
                        Mail::to($booking->user->email)
                            ->send(new BookingConfirmedMail($booking->fresh(['user', 'property'])));
                    } catch (\Exception $mailException) {
                        // Hindi dapat i-fail ang buong request kung may
                        // isyu ang email delivery — naka-log lang, dahil
                        // matagumpay naman talaga ang bayad at booking.
                        \Log::error('Failed sending booking confirmation email: ' . $mailException->getMessage());
                    }
                }
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

        if (!$this->paymongo->verifyWebhook($payload, $signature)) {
            \Log::warning('PayMongo webhook: invalid signature attempt', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

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