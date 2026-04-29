<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $booking->booking_ref }} — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--sand:#f5f0e8;--stone:#2c2416;--cream:#fdfbf7;--terracotta:#c4673a;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;--white:#ffffff;--nav-h:72px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--sand);color:var(--stone);}
        .topnav{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--stone);z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 40px;}
        .nav-brand{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:20px;letter-spacing:1px;text-decoration:none;}
        .nav-brand span{color:rgba(255,255,255,.35);font-style:italic;font-size:14px;margin-left:8px;}
        .nav-link-item{color:rgba(255,255,255,.55);text-decoration:none;font-size:13px;padding:8px 14px;border-radius:6px;transition:all .2s;}
        .nav-link-item:hover{color:#fff;}
        .logout-link{color:rgba(255,255,255,.4);font-size:12px;text-decoration:none;}
        .logout-link:hover{color:#fff;}
        .main{margin-top:var(--nav-h);padding:40px;max-width:860px;margin-left:auto;margin-right:auto;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:24px;}
        .breadcrumb-row a{color:var(--muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--stone);}

        /* Hero */
        .booking-hero{background:var(--stone);border-radius:20px;padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
        .hero-ref{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:26px;font-weight:600;}
        .hero-sub{color:rgba(255,255,255,.4);font-size:12px;margin-top:3px;text-transform:uppercase;letter-spacing:1px;}
        .hero-dates{text-align:center;}
        .hero-date-label{color:rgba(255,255,255,.4);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;}
        .hero-date-val{font-family:'Playfair Display',serif;color:#fff;font-size:18px;font-weight:600;}
        .hero-arrow{color:rgba(255,255,255,.3);font-size:20px;}
        .hero-nights{color:#fff;text-align:center;}
        .hero-nights-val{font-family:'Playfair Display',serif;font-size:32px;font-weight:700;}
        .hero-nights-label{color:rgba(255,255,255,.4);font-size:12px;}
        .badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}
        .b-pending{background:#fef9c3;color:#a16207;}
        .b-confirmed{background:#dcfce7;color:#15803d;}
        .b-checked_in{background:#dbeafe;color:#1d4ed8;}
        .b-checked_out{background:#f1f5f9;color:#475569;}
        .b-cancelled{background:#fee2e2;color:#dc2626;}
        .p-unpaid{background:#fee2e2;color:#dc2626;}
        .p-partial{background:#fef9c3;color:#a16207;}
        .p-paid{background:#dcfce7;color:#15803d;}

        /* Layout */
        .detail-grid{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;}

        /* Cards */
        .card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;margin-bottom:16px;}
        .card-head{padding:16px 22px;border-bottom:1px solid var(--border);}
        .card-head h3{font-family:'Playfair Display',serif;font-size:16px;font-weight:600;}
        .card-body{padding:20px 22px;}
        .info-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f4efe6;font-size:13px;}
        .info-row:last-child{border-bottom:none;}
        .info-row .lbl{color:var(--muted);}
        .info-row .val{font-weight:500;text-align:right;}

        /* Price */
        .price-row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px;border-bottom:1px solid #f4efe6;}
        .price-row:last-child{border-bottom:none;}
        .price-row.total{font-weight:700;font-size:16px;font-family:'Playfair Display',serif;border-top:2px solid var(--border);padding-top:12px;margin-top:4px;}
        .price-row.paid{color:#15803d;}
        .price-row.due{color:var(--terracotta);font-weight:600;}

        /* Payment table */
        .pay-table{width:100%;border-collapse:collapse;font-size:12px;}
        .pay-table th{color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;letter-spacing:.7px;padding:7px 0;border-bottom:1px solid var(--border);}
        .pay-table td{padding:9px 0;border-bottom:1px solid #f4efe6;}
        .pay-table tr:last-child td{border-bottom:none;}

        /* Cancel form */
        .cancel-section{background:#fff8f6;border:1px solid #fdd9cc;border-radius:12px;padding:18px;}
        .cancel-title{font-size:14px;font-weight:600;color:var(--terracotta);margin-bottom:8px;}
        .cancel-desc{font-size:12px;color:var(--muted);margin-bottom:12px;line-height:1.5;}
        .form-control{border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:'Jost',sans-serif;width:100%;}
        .form-control:focus{outline:none;border-color:var(--stone);}
        .btn-cancel-booking{background:var(--terracotta);color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:'Jost',sans-serif;width:100%;margin-top:10px;transition:opacity .2s;}
        .btn-cancel-booking:hover{opacity:.88;}

        /* Property image */
        .property-img{width:100%;height:160px;object-fit:cover;border-radius:10px;margin-bottom:12px;}

        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-success{background:#dcfce7;color:#15803d;}
        .alert-error{background:#fee2e2;color:#dc2626;}

        .cancelled-banner{background:#fee2e2;border-radius:10px;padding:14px 18px;margin-bottom:16px;font-size:13px;color:#dc2626;}
        .cancelled-banner strong{display:block;margin-bottom:3px;}
    </style>
</head>
<body>
<nav class="topnav">
    <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena <span>Resort</span></a>
    <div style="display:flex;gap:4px;">
        <a href="{{ route('customer.home') }}" class="nav-link-item">Dashboard</a>
        <a href="{{ route('customer.bookings') }}" class="nav-link-item">My Bookings</a>
        <a href="{{ route('customer.notifications') }}" class="nav-link-item">Notifications</a>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
            <span class="logout-link">Sign out</span>
        </button>
    </form>
</nav>

<main class="main">
    <div class="breadcrumb-row">
        <a href="{{ route('customer.home') }}">Dashboard</a>
        <span>›</span>
        <a href="{{ route('customer.bookings') }}">My Bookings</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">{{ $booking->booking_ref }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    @if($booking->status === 'cancelled')
        <div class="cancelled-banner">
            <strong>This booking was cancelled</strong>
            {{ $booking->cancellation_reason }}
            @if($booking->cancelled_at)
                · {{ $booking->cancelled_at->format('M d, Y') }}
            @endif
        </div>
    @endif

    {{-- Hero --}}
    <div class="booking-hero">
        <div>
            <div class="hero-ref">{{ $booking->booking_ref }}</div>
            <div class="hero-sub">{{ $booking->property->property_name ?? '' }} · {{ ucfirst(str_replace('_',' ',$booking->source)) }}</div>
        </div>
        <div class="hero-dates">
            <div class="hero-date-label">Check-in</div>
            <div class="hero-date-val">{{ $booking->check_in_date->format('M d, Y') }}</div>
        </div>
        <div class="hero-arrow">→</div>
        <div class="hero-dates">
            <div class="hero-date-label">Check-out</div>
            <div class="hero-date-val">{{ $booking->check_out_date->format('M d, Y') }}</div>
        </div>
        <div class="hero-nights">
            <div class="hero-nights-val">{{ $booking->num_nights }}</div>
            <div class="hero-nights-label">night{{ $booking->num_nights!=1?'s':'' }}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:7px;align-items:flex-end;">
            <span class="badge b-{{ $booking->status }}">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</span>
            <span class="badge p-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span>
        </div>
         @if($booking->status === 'checked_out')
     @php
         $alreadyReviewed = \App\Models\Review::where('booking_id', $booking->id)
             ->where('user_id', auth()->id())->exists();
     @endphp
     @if(!$alreadyReviewed)
     <a href="{{ route('customer.reviews.create', $booking) }}"
        style="display:block;background:#f59e0b;color:#fff;border-radius:10px;padding:13px;
               text-align:center;font-size:14px;font-weight:600;text-decoration:none;
               margin-top:14px;transition:all .2s;"
        onmouseover="this.style.background='#d97706'"
       onmouseout="this.style.background='#f59e0b'">
         ⭐ Write a Review
     </a>
     @else
     <div style="background:#f8fafc;border-radius:10px;padding:12px;text-align:center;
                 font-size:13px;color:#6B7A8D;margin-top:14px;">
         <i class="bi bi-check-circle me-1" style="color:#16a34a;"></i>
         You have already submitted a review for this stay.
     </div>
 @endif
@endif
    </div>

    <div class="detail-grid">

        {{-- Left --}}
        <div>
            {{-- Booking Info --}}
            <div class="card">
                <div class="card-head"><h3>Booking Details</h3></div>
                <div class="card-body">
                    <div class="info-row"><span class="lbl">Property</span><span class="val">{{ $booking->property->property_name ?? 'N/A' }}</span></div>
                    <div class="info-row"><span class="lbl">Check-in</span><span class="val">{{ $booking->check_in_date->format('l, F j, Y') }}</span></div>
                    <div class="info-row"><span class="lbl">Check-out</span><span class="val">{{ $booking->check_out_date->format('l, F j, Y') }}</span></div>
                    <div class="info-row"><span class="lbl">Duration</span><span class="val">{{ $booking->num_nights }} night{{ $booking->num_nights!=1?'s':'' }}</span></div>
                    <div class="info-row"><span class="lbl">Guests</span><span class="val">{{ $booking->num_guests }} guest{{ $booking->num_guests!=1?'s':'' }}</span></div>
                    <div class="info-row"><span class="lbl">Booking Source</span><span class="val">{{ ucfirst(str_replace('_',' ',$booking->source)) }}</span></div>
                    <div class="info-row"><span class="lbl">Booked On</span><span class="val">{{ $booking->created_at->format('M d, Y h:i A') }}</span></div>
                    @if($booking->special_requests)
                    <div style="margin-top:12px;background:#f9f5ee;border-radius:8px;padding:12px;font-size:13px;color:var(--muted);">
                        <strong style="color:var(--stone);display:block;margin-bottom:4px;">Special Requests:</strong>
                        {{ $booking->special_requests }}
                    </div>
                    @endif
                </div>
            </div>

            {{-- Payment History --}}
            <div class="card">
                <div class="card-head"><h3>Payment History</h3></div>
                <div class="card-body">
                    @if($booking->payments->isEmpty())
                        <p style="font-size:13px;color:var(--muted);text-align:center;padding:16px 0;">No payments recorded yet.</p>
                    @else
                        <table class="pay-table">
                            <thead><tr>
                                <th>Date</th><th>Method</th><th>Type</th><th style="text-align:right;">Amount</th>
                            </tr></thead>
                            <tbody>
                                @foreach($booking->payments as $payment)
                                <tr>
                                    <td style="color:var(--muted);">{{ $payment->payment_date?->format('M d, Y') }}</td>
                                    <td>{{ ucfirst(str_replace('_',' ',$payment->payment_method)) }}</td>
                                    <td><span style="background:#f1f5f9;padding:2px 8px;border-radius:10px;font-size:10px;">{{ ucfirst($payment->payment_type) }}</span></td>
                                    <td style="text-align:right;font-weight:600;color:{{ $payment->payment_type==='refund'?'#dc2626':'#15803d' }};">
                                        {{ $payment->payment_type==='refund'?'-':'+' }}₱{{ number_format($payment->amount,2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right --}}
        <div>
            {{-- Property Image & Info --}}
            <div class="card">
                <div class="card-body">
                    @if($booking->property?->primaryImage)
                        <img src="{{ asset('storage/'.$booking->property->primaryImage->image_path) }}" class="property-img" alt="">
                    @endif
                    <div style="font-weight:600;font-size:15px;font-family:'Playfair Display',serif;">{{ $booking->property->property_name ?? 'N/A' }}</div>
                    @if($booking->property)
                    <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                        {{ ucfirst($booking->property->type) }} ·
                        Max {{ $booking->property->max_capacity }} guests
                    </div>
                    @endif
                </div>
            </div>

            {{-- Price Summary --}}
            <div class="card">
                <div class="card-head"><h3>Price Summary</h3></div>
                <div class="card-body">
                    <div class="price-row"><span style="color:var(--muted);">Base ({{ $booking->num_nights }}n)</span><span>₱{{ number_format($booking->base_amount,2) }}</span></div>
                    @if($booking->extras_amount > 0)
                    <div class="price-row"><span style="color:var(--muted);">Add-ons</span><span>₱{{ number_format($booking->extras_amount,2) }}</span></div>
                    @endif
                    @if($booking->discount_amount > 0)
                    <div class="price-row"><span style="color:#15803d;">Discount</span><span style="color:#15803d;">-₱{{ number_format($booking->discount_amount,2) }}</span></div>
                    @endif
                    <div class="price-row total"><span>Total</span><span>₱{{ number_format($booking->total_amount,2) }}</span></div>
                    <div class="price-row paid"><span>Paid</span><span>₱{{ number_format($booking->amount_paid,2) }}</span></div>
                    @if($booking->balance_due > 0)
                    <div class="price-row due"><span>Balance Due</span><span>₱{{ number_format($booking->balance_due,2) }}</span></div>
                    @endif

                    @if($booking->balance_due > 0 && !in_array($booking->status, ['cancelled','checked_out']))
                    <a href="{{ route('payment.page', $booking) }}"
                    style="display:block;background:#2c2416;color:#fff;border-radius:10px;padding:13px;
                            text-align:center;font-size:14px;font-weight:600;text-decoration:none;
                            margin-top:14px;transition:all .2s;"
                    onmouseover="this.style.background='#b8943f';this.style.color='#2c2416'"
                    onmouseout="this.style.background='#2c2416';this.style.color='#fff'">
                        💳 Pay Now — ₱{{ number_format($booking->balance_due,2) }}
                    </a>
                    @endif
                </div>
            </div>

            {{-- Cancel --}}
            @if(in_array($booking->status, ['pending', 'confirmed']))
            <div class="cancel-section">
                <div class="cancel-title"><i class="bi bi-x-circle me-1"></i> Cancel Booking</div>
                <div class="cancel-desc">
                    Need to cancel? Please provide a reason below. Cancellations may be subject to our
                    cancellation policy depending on how close to the check-in date.
                </div>
                <form method="POST" action="{{ route('customer.bookings.cancel', $booking) }}" id="cancelForm">
                    @csrf @method('PATCH')
                    <textarea name="cancellation_reason" class="form-control" rows="2"
                        placeholder="Reason for cancellation..." required minlength="5"></textarea>
                    <button type="button" class="btn-cancel-booking" onclick="confirmCancel()">
                        Cancel This Booking
                    </button>
                </form>
            </div>
            @endif
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmCancel() {
    if (confirm('Are you sure you want to cancel booking {{ $booking->booking_ref }}? This cannot be undone.')) {
        document.getElementById('cancelForm').submit();
    }
}
</script>
</body>
</html>