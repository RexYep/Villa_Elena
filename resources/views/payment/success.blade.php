<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--stone:#2c2416;--sand:#f5f0e8;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--sand);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px;}
        .card{background:#fff;border-radius:22px;border:1px solid var(--border);width:100%;max-width:440px;overflow:hidden;box-shadow:0 16px 48px rgba(44,36,22,.12);}
        .card-top{background:var(--stone);padding:36px 32px;text-align:center;position:relative;overflow:hidden;}
        .card-top::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(184,148,63,.2) 0%,transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%);}
        .success-circle{width:72px;height:72px;border-radius:50%;background:rgba(74,222,128,.15);border:2px solid #4ade80;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;position:relative;z-index:1;}
        .success-circle i{font-size:30px;color:#4ade80;}
        .card-top h1{font-family:'Playfair Display',serif;color:#fff;font-size:26px;font-weight:700;position:relative;z-index:1;}
        .card-top p{color:rgba(255,255,255,.45);font-size:13px;margin-top:6px;position:relative;z-index:1;}
        .card-body{padding:28px 32px;}
        .amount-box{background:var(--sand);border-radius:14px;padding:20px;text-align:center;margin-bottom:22px;}
        .amount-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;}
        .amount-val{font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:var(--stone);}
        .detail-row{display:flex;justify-content:space-between;font-size:13px;padding:9px 0;border-bottom:1px solid #f4efe6;}
        .detail-row:last-child{border-bottom:none;}
        .detail-row .lbl{color:var(--muted);}
        .detail-row .val{font-weight:500;}
        .status-confirmed{background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
        .status-partial{background:#fef9c3;color:#a16207;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
        .btn-primary{display:block;background:var(--stone);color:#fff;border-radius:10px;padding:14px;text-align:center;font-size:14px;font-weight:600;text-decoration:none;margin-top:22px;transition:all .2s;}
        .btn-primary:hover{background:var(--gold);color:var(--stone);}
        .btn-secondary{display:block;text-align:center;margin-top:10px;color:var(--muted);font-size:13px;text-decoration:none;}
        .btn-secondary:hover{color:var(--stone);}
        .confetti{font-size:24px;margin-bottom:8px;position:relative;z-index:1;}
    </style>
</head>
<body>
<div class="card">
    <div class="card-top">
        <div class="confetti">🎉</div>
        <div class="success-circle"><i class="bi bi-check-lg"></i></div>
        <h1>Payment Successful!</h1>
        <p>Your payment has been received and confirmed.</p>
    </div>
    <div class="card-body">
        @php
            $booking->refresh();
            $paidNow = $amountPaid ?? 0;
        @endphp

        <div class="amount-box">
            <div class="amount-label">Amount Paid</div>
            <div class="amount-val">₱{{ number_format($paidNow, 2) }}</div>
        </div>

        <div class="detail-row"><span class="lbl">Booking Ref</span><span class="val">{{ $booking->booking_ref }}</span></div>
        <div class="detail-row"><span class="lbl">Property</span><span class="val">{{ $booking->property->property_name }}</span></div>
        <div class="detail-row"><span class="lbl">Check-in</span><span class="val">{{ $booking->check_in_date->format('M d, Y') }}</span></div>
        <div class="detail-row"><span class="lbl">Check-out</span><span class="val">{{ $booking->check_out_date->format('M d, Y') }}</span></div>
        <div class="detail-row">
            <span class="lbl">Booking Status</span>
            <span class="val">
                @if($booking->status === 'confirmed')
                    <span class="status-confirmed">Confirmed</span>
                @else
                    <span>{{ ucfirst($booking->status) }}</span>
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="lbl">Payment Status</span>
            <span class="val">
                @if($booking->payment_status === 'paid')
                    <span class="status-confirmed">Fully Paid ✓</span>
                @elseif($booking->payment_status === 'partial')
                    <span class="status-partial">Partial — ₱{{ number_format($booking->balance_due,2) }} remaining</span>
                @endif
            </span>
        </div>

        <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-primary">
            <i class="bi bi-calendar-check me-2"></i> View My Booking
        </a>
        <a href="{{ route('customer.home') }}" class="btn-secondary">← Back to Dashboard</a>
    </div>
</div>
</body>
</html>