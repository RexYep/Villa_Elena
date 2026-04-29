{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- SAVE AS: resources/views/portal/confirmation.blade.php          --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--sand:#f5f0e8;--stone:#2c2416;--cream:#fdfbf7;--terracotta:#c4673a;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--sand);color:var(--stone);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;}
        .card{background:#fff;border-radius:24px;border:1px solid var(--border);max-width:560px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(44,36,22,.1);}

        /* Top strip */
        .card-top{background:var(--stone);padding:36px 40px;text-align:center;position:relative;overflow:hidden;}
        .card-top::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(184,148,63,.2) 0%,transparent 65%);top:50%;left:50%;transform:translate(-50%,-50%);}
        .check-circle{width:72px;height:72px;border-radius:50%;background:rgba(184,148,63,.2);border:2px solid var(--gold);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;position:relative;z-index:1;}
        .check-circle i{font-size:30px;color:var(--gold-light);}
        .card-top h1{font-family:'Playfair Display',serif;color:#fff;font-size:28px;font-weight:700;position:relative;z-index:1;}
        .card-top p{color:rgba(255,255,255,.5);font-size:14px;margin-top:6px;position:relative;z-index:1;}

        /* Body */
        .card-body{padding:32px 36px;}
        .ref-badge{background:rgba(184,148,63,.1);border:1px solid rgba(184,148,63,.25);border-radius:10px;padding:14px 20px;text-align:center;margin-bottom:24px;}
        .ref-label{font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);margin-bottom:6px;}
        .ref-val{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--stone);}

        /* Detail rows */
        .detail-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f4efe6;font-size:14px;}
        .detail-row:last-child{border-bottom:none;}
        .detail-row .lbl{color:var(--muted);}
        .detail-row .val{font-weight:600;text-align:right;}

        /* Deposit box */
        .deposit-notice{background:rgba(196,103,58,.07);border:1px solid rgba(196,103,58,.2);border-radius:12px;padding:16px 20px;margin:20px 0;display:flex;gap:12px;align-items:flex-start;}
        .deposit-notice i{color:var(--terracotta);font-size:20px;flex-shrink:0;margin-top:2px;}
        .deposit-notice-text{font-size:13px;color:var(--stone);line-height:1.6;}
        .deposit-notice-text strong{color:var(--terracotta);}

        /* Buttons */
        .btn-row{display:flex;gap:10px;margin-top:24px;}
        .btn{padding:12px 20px;border-radius:9px;font-size:14px;font-weight:600;font-family:'Jost',sans-serif;text-decoration:none;text-align:center;flex:1;transition:all .2s;}
        .btn-primary{background:var(--stone);color:#fff;}
        .btn-primary:hover{background:var(--gold);color:var(--stone);}
        .btn-outline{border:1.5px solid var(--border);color:var(--muted);}
        .btn-outline:hover{border-color:var(--stone);color:var(--stone);}

        /* Steps */
        .next-steps{margin-top:24px;padding-top:20px;border-top:1px solid var(--border);}
        .next-steps-title{font-size:12px;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);margin-bottom:14px;font-weight:600;}
        .next-step{display:flex;gap:12px;padding:8px 0;font-size:13px;color:var(--muted);}
        .step-num{width:22px;height:22px;border-radius:50%;background:var(--sand);color:var(--stone);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
    </style>
</head>
<body>

<div class="card">
    <div class="card-top">
        <div class="check-circle"><i class="bi bi-check-lg"></i></div>
        <h1>Booking Received!</h1>
        <p>We'll be in touch shortly to confirm your reservation.</p>
    </div>

    <div class="card-body">
        <div class="ref-badge">
            <div class="ref-label">Your Booking Reference</div>
            <div class="ref-val">{{ $booking->booking_ref }}</div>
        </div>

        <div class="detail-row"><span class="lbl">Property</span><span class="val">{{ $booking->property->property_name }}</span></div>
        <div class="detail-row">
            <span class="lbl">Check-in</span>
            <span class="val">{{ $booking->check_in_date->format('l, M d, Y') }}</span>
        </div>
        <div class="detail-row">
            <span class="lbl">Check-out</span>
            <span class="val">{{ $booking->check_out_date->format('l, M d, Y') }}</span>
        </div>
        <div class="detail-row">
            <span class="lbl">Duration</span>
            <span class="val">{{ $booking->num_nights }} night{{ $booking->num_nights != 1 ? 's' : '' }}</span>
        </div>
        <div class="detail-row">
            <span class="lbl">Guests</span>
            <span class="val">{{ $booking->num_guests }} guest{{ $booking->num_guests != 1 ? 's' : '' }}</span>
        </div>
        <div class="detail-row">
            <span class="lbl">Total Amount</span>
            <span class="val" style="font-family:'Playfair Display',serif;font-size:17px;">₱{{ number_format($booking->total_amount, 2) }}</span>
        </div>
        <div class="detail-row">
            <span class="lbl">Status</span>
            <span class="val"><span style="background:#fef9c3;color:#a16207;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">Pending Confirmation</span></span>
        </div>

        <div class="deposit-notice">
            <i class="bi bi-credit-card"></i>
            <div class="deposit-notice-text">
                <strong>Payment not collected yet.</strong> Our team will contact you at <strong>{{ auth()->user()->email }}</strong> within 24 hours to arrange your deposit payment and confirm your booking.
            </div>
        </div>

        <div class="next-steps">
            <div class="next-steps-title">What Happens Next</div>
            <div class="next-step"><div class="step-num">1</div><span>Our team reviews your booking request</span></div>
            <div class="next-step"><div class="step-num">2</div><span>We contact you to arrange the deposit payment</span></div>
            <div class="next-step"><div class="step-num">3</div><span>Booking is confirmed once deposit is received</span></div>
            <div class="next-step"><div class="step-num">4</div><span>Enjoy your stay at Villa Elena Resort!</span></div>
        </div>

        <div class="btn-row">
            <a href="{{ route('customer.bookings.show', $booking) }}" class="btn btn-primary">
                <i class="bi bi-calendar-check me-1"></i> View Booking
            </a>
            <a href="{{ route('home') }}" class="btn btn-outline">
                Browse More
            </a>
        </div>
    </div>
</div>

</body>
</html>