<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    // ── My Payment History ─────────────────────────────────────────
    public function index()
    {
        $payments = Payment::whereHas('booking', function ($q) {
                $q->where('user_id', Auth::id());
            })
            ->with('booking.property')
            // Ang `payment_date` ay PETSA lang (walang oras), kaya ang
            // dalawang bayad sa iisang araw ay patas ang laban at basta
            // na lang pumipili ang MySQL — kadalasan ay pabalik pa.
            // Resulta: lumalabas ang deposit sa IBABAW ng balance
            // payment na mas bago. Ang `id` ang pumapasok bilang
            // tiebreaker: laging huli ang naitala, laging nasa taas.
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('customer.payments', compact('payments'));
    }
}
