<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // ── My Payment History ─────────────────────────────────────────
    public function index()
    {
        $payments = Payment::whereHas('booking', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->with('booking.property')
            ->orderByDesc('payment_date')
            ->paginate(15);

        return view('customer.payments', compact('payments'));
    }
}
