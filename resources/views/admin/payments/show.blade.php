@extends('layouts.admin')

@section('title', 'Payment Detail — Villa Elena')
@section('page-title', 'Payment Detail')
@section('page-subtitle', $payment->booking->booking_ref)

@push('styles')
<style>
.main-content{max-width:820px;}

.card{background:var(--cream);border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
.card-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
.card-head h3{font-family:'Cormorant Garamond',serif;font-size:16px;font-weight:600;color:var(--text-main);}
.card-body{padding:22px;}
.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;}
.info-row:last-child{border-bottom:none;}
.info-row .lbl{color:var(--muted);}
.info-row .val{font-weight:500;text-align:right;color:var(--text-main);}

.amount-hero{background:var(--terracotta);border-radius:16px;padding:28px;text-align:center;margin-bottom:20px;position:relative;overflow:hidden;}
.amount-hero::before{content:'';position:absolute;width:250px;height:250px;border-radius:50%;background:radial-gradient(circle,rgba(184,148,63,.28) 0%,transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%);}
.amount-label{color:rgba(255,255,255,.55);font-size:11px;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;position:relative;z-index:1;}
.amount-val{font-family:'Cormorant Garamond',serif;font-size:44px;font-weight:700;color:#fff;position:relative;z-index:1;}
.amount-ref{color:var(--gold-light);font-size:14px;margin-top:8px;position:relative;z-index:1;}

/* Badges — semantic, unchanged */
.badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
.b-gcash{background:var(--tag-blue-bg);color:var(--tag-blue-fg);}
.b-cash{background:var(--tag-green-bg);color:var(--tag-green-fg);}
.b-online{background:var(--tag-cyan-bg);color:var(--tag-cyan-fg);}
.b-bank_transfer{background:var(--tag-purple-bg);color:var(--tag-purple-fg);}
.b-credit_card{background:var(--tag-amber-bg);color:var(--tag-amber-fg);}
.b-deposit{background:var(--tag-emerald-bg);color:var(--tag-emerald-fg);}
.b-full_payment{background:var(--tag-green-bg);color:var(--tag-green-fg);}
.b-partial{background:var(--tag-amber-bg);color:var(--tag-amber-fg);}
.b-balance{background:var(--tag-blue-bg);color:var(--tag-blue-fg);}
.b-refund{background:var(--tag-red-bg);color:var(--tag-red-fg);}

.back-btn{display:inline-flex;align-items:center;gap:7px;color:var(--muted);text-decoration:none;font-size:13px;padding:8px 16px;border:1.5px solid var(--border);border-radius:8px;background:#fff;transition:all .2s;}
.back-btn:hover{border-color:var(--terracotta);color:var(--terracotta);}

@media (max-width: 700px) {
    .payment-detail-grid{grid-template-columns:1fr !important;}
    .amount-hero{padding:20px;}
    .amount-val{font-size:34px;}
}
</style>
@endpush

@section('content')
    <div class="breadcrumb-row">
        <a href="{{ route('admin.payments.index') }}">Payments</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">{{ $payment->booking->booking_ref }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    {{-- Amount Hero --}}
    <div class="amount-hero">
        <div class="amount-label">{{ $payment->payment_type === 'refund' ? 'Refund Amount' : 'Amount Paid' }}</div>
        <div class="amount-val" style="{{ $payment->payment_type === 'refund' ? 'color:#fca5a5;' : '' }}">
            {{ $payment->payment_type === 'refund' ? '-' : '' }}₱{{ number_format($payment->amount, 2) }}
        </div>
        <div class="amount-ref">{{ $payment->booking->booking_ref }} · {{ $payment->payment_date?->format('F d, Y') }}</div>
    </div>

    <div class="payment-detail-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        {{-- Payment Info --}}
        <div class="card">
            <div class="card-head"><h3>Payment Information</h3></div>
            <div class="card-body">
                <div class="info-row"><span class="lbl">Amount</span><span class="val" style="font-family:'Playfair Display',serif;font-size:16px;">₱{{ number_format($payment->amount,2) }}</span></div>
                <div class="info-row"><span class="lbl">Method</span><span class="val"><span class="badge b-{{ $payment->payment_method }}">{{ ucfirst(str_replace('_',' ',$payment->payment_method)) }}</span></span></div>
                <div class="info-row"><span class="lbl">Type</span><span class="val"><span class="badge b-{{ $payment->payment_type }}">{{ ucfirst(str_replace('_',' ',$payment->payment_type)) }}</span></span></div>
                <div class="info-row"><span class="lbl">Date</span><span class="val">{{ $payment->payment_date?->format('M d, Y') }}</span></div>
                @if($payment->reference_number)
                <div class="info-row"><span class="lbl">Reference</span><span class="val" style="font-size:12px;word-break:break-all;">{{ $payment->reference_number }}</span></div>
                @endif
                @if($payment->notes)
                <div class="info-row"><span class="lbl">Notes</span><span class="val" style="text-align:right;max-width:200px;">{{ $payment->notes }}</span></div>
                @endif
            </div>
        </div>

        {{-- Booking Info --}}
        <div class="card">
            <div class="card-head"><h3>Booking Summary</h3></div>
            <div class="card-body">
                <div class="info-row"><span class="lbl">Guest</span><span class="val">{{ $payment->booking->user->full_name }}</span></div>
                <div class="info-row"><span class="lbl">Property</span><span class="val">{{ $payment->booking->property->property_name }}</span></div>
                <div class="info-row"><span class="lbl">Check-in</span><span class="val">{{ $payment->booking->check_in_date->format('M d, Y') }}</span></div>
                <div class="info-row"><span class="lbl">Check-out</span><span class="val">{{ $payment->booking->check_out_date->format('M d, Y') }}</span></div>
                <div class="info-row"><span class="lbl">Total Amount</span><span class="val">₱{{ number_format($payment->booking->total_amount,2) }}</span></div>
                <div class="info-row"><span class="lbl">Amount Paid</span><span class="val" style="color:#16a34a;">₱{{ number_format($payment->booking->amount_paid,2) }}</span></div>
                @if($payment->booking->balance_due > 0)
                <div class="info-row"><span class="lbl">Balance Due</span><span class="val" style="color:#dc2626;">₱{{ number_format($payment->booking->balance_due,2) }}</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- All Payments for this Booking --}}
    <div class="card">
        <div class="card-head"><h3>All Payments for {{ $payment->booking->booking_ref }}</h3></div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:520px;">
                <thead><tr style="background:#f8fafc;">
                    <th class="text-muted-theme" style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">Date</th>
                    <th class="text-muted-theme" style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">Method</th>
                    <th class="text-muted-theme" style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">Type</th>
                    <th class="text-muted-theme" style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);text-align:right;">Amount</th>
                </tr></thead>
                <tbody>
                    @foreach($payment->booking->payments as $p)
                    <tr style="border-bottom:1px solid #f8fafc;{{ $p->id === $payment->id ? 'background:rgba(201,168,76,.07);' : '' }}">
                        <td style="padding:12px 16px;font-size:13px;">{{ $p->payment_date?->format('M d, Y') }}</td>
                        <td style="padding:12px 16px;"><span class="badge b-{{ $p->payment_method }}">{{ ucfirst(str_replace('_',' ',$p->payment_method)) }}</span></td>
                        <td style="padding:12px 16px;"><span class="badge b-{{ $p->payment_type }}">{{ ucfirst(str_replace('_',' ',$p->payment_type)) }}</span></td>
                        <td style="padding:12px 16px;text-align:right;font-weight:700;font-family:'Playfair Display',serif;color:{{ $p->payment_type==='refund'?'#dc2626':'#0D1B2A' }};">
                            {{ $p->payment_type==='refund'?'-':'' }}₱{{ number_format($p->amount,2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <a href="{{ route('admin.payments.index') }}" class="back-btn">
        <i class="bi bi-arrow-left"></i> Back to Payments
    </a>
@endsection
