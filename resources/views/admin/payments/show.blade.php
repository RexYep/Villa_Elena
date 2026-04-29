{{-- SAVE AS: resources/views/admin/payments/show.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Detail — Villa Elena</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0D1B2A;--gold:#C9A84C;--gold-light:#E8C97A;--bg:#F4F6F9;--border:#E2E8F0;--muted:#6B7A8D;--sidebar:260px;--topbar:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:var(--navy);z-index:100;display:flex;flex-direction:column;}
        .sidebar-brand{padding:22px 24px 18px;border-bottom:1px solid rgba(255,255,255,.07);text-decoration:none;display:block;}
        .brand-name{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:18px;}
        .brand-sub{color:rgba(255,255,255,.3);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-top:2px;}
        .sidebar-nav{flex:1;padding:16px 12px;}
        .nav-item{display:flex;align-items:center;gap:11px;padding:10px 14px;border-radius:9px;color:rgba(255,255,255,.55);text-decoration:none;font-size:13.5px;font-weight:500;margin-bottom:2px;transition:all .2s;}
        .nav-item:hover{background:rgba(255,255,255,.06);color:#fff;}
        .nav-item.active{background:rgba(201,168,76,.15);color:var(--gold-light);}
        .nav-item i{font-size:16px;width:20px;text-align:center;}
        .sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.07);}
        .user-info{display:flex;align-items:center;gap:10px;}
        .user-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold);color:var(--navy);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;}
        .user-name{color:rgba(255,255,255,.7);font-size:13px;}
        .user-role{color:rgba(255,255,255,.3);font-size:11px;}
        .logout-btn{margin-left:auto;color:rgba(255,255,255,.3);font-size:18px;background:none;border:none;cursor:pointer;}
        .topbar{position:fixed;top:0;left:var(--sidebar);right:0;height:var(--topbar);background:#fff;border-bottom:1px solid var(--border);z-index:99;display:flex;align-items:center;justify-content:space-between;padding:0 28px;}
        .topbar-title{font-family:'Playfair Display',serif;font-size:20px;color:var(--navy);font-weight:700;}
        .topbar-sub{font-size:13px;color:var(--muted);margin-top:2px;}
        .main{margin-left:var(--sidebar);margin-top:var(--topbar);padding:28px;max-width:820px;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:24px;}
        .breadcrumb-row a{color:var(--muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--navy);}

        .card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .card-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
        .card-head h3{font-family:'Playfair Display',serif;font-size:16px;font-weight:600;}
        .card-body{padding:22px;}
        .info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f4efe6;font-size:13px;}
        .info-row:last-child{border-bottom:none;}
        .info-row .lbl{color:var(--muted);}
        .info-row .val{font-weight:500;text-align:right;}

        .amount-hero{background:var(--navy);border-radius:16px;padding:28px;text-align:center;margin-bottom:20px;position:relative;overflow:hidden;}
        .amount-hero::before{content:'';position:absolute;width:250px;height:250px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.2) 0%,transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%);}
        .amount-label{color:rgba(255,255,255,.4);font-size:11px;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;position:relative;z-index:1;}
        .amount-val{font-family:'Playfair Display',serif;font-size:44px;font-weight:700;color:#fff;position:relative;z-index:1;}
        .amount-ref{color:var(--gold-light);font-size:14px;margin-top:8px;position:relative;z-index:1;}

        .badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
        .b-gcash{background:#dbeafe;color:#1d4ed8;}
        .b-cash{background:#dcfce7;color:#15803d;}
        .b-online{background:#e0f2fe;color:#0369a1;}
        .b-bank_transfer{background:#f3e8ff;color:#7c3aed;}
        .b-credit_card{background:#fef9c3;color:#a16207;}
        .b-deposit{background:#f0fdf4;color:#16a34a;}
        .b-full_payment{background:#dcfce7;color:#15803d;}
        .b-partial{background:#fef9c3;color:#a16207;}
        .b-balance{background:#dbeafe;color:#1d4ed8;}
        .b-refund{background:#fee2e2;color:#dc2626;}

        .back-btn{display:inline-flex;align-items:center;gap:7px;color:var(--muted);text-decoration:none;font-size:13px;padding:8px 16px;border:1.5px solid var(--border);border-radius:8px;background:#fff;transition:all .2s;}
        .back-btn:hover{border-color:var(--navy);color:var(--navy);}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;border:none;margin-bottom:18px;}
        .alert-success{background:#dcfce7;color:#15803d;}
    </style>
</head>
<body>
@include('admin.partials.sidebar')


<div class="topbar">
    <div>
        <div class="topbar-title">Payment Detail</div>
        <div class="topbar-sub">{{ $payment->booking->booking_ref }}</div>
    </div>
</div>

<main class="main">
    <div class="breadcrumb-row">
        <a href="{{ route('admin.payments.index') }}">Payments</a>
        <span>›</span>
        <span style="color:var(--navy);font-weight:500;">{{ $payment->booking->booking_ref }}</span>
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

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
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
        <div class="card-body" style="padding:0;">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:#f8fafc;">
                    <th style="padding:10px 16px;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">Date</th>
                    <th style="padding:10px 16px;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">Method</th>
                    <th style="padding:10px 16px;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">Type</th>
                    <th style="padding:10px 16px;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);text-align:right;">Amount</th>
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
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@include('admin.partials.realtime') 
</body>
</html>