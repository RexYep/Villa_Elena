{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- SAVE AS: resources/views/portal/booking_form.blade.php          --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Booking — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--sand:#f5f0e8;--stone:#2c2416;--cream:#fdfbf7;--terracotta:#c4673a;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;--white:#ffffff;--nav-h:72px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--sand);color:var(--stone);}
        .nav{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:rgba(44,36,22,.97);z-index:200;display:flex;align-items:center;justify-content:space-between;padding:0 40px;}
        .nav-brand{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:20px;text-decoration:none;}
        .main{margin-top:var(--nav-h);max-width:960px;margin-left:auto;margin-right:auto;padding:40px;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:28px;}
        .breadcrumb-row a{color:var(--muted);text-decoration:none;}

        /* Steps */
        .steps{display:flex;align-items:center;gap:0;margin-bottom:36px;}
        .step{display:flex;align-items:center;gap:10px;font-size:13px;}
        .step-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
        .step.done .step-num{background:var(--gold);color:var(--stone);}
        .step.active .step-num{background:var(--stone);color:#fff;}
        .step.inactive .step-num{background:var(--border);color:var(--muted);}
        .step.active .step-label{font-weight:600;color:var(--stone);}
        .step.inactive .step-label{color:var(--muted);}
        .step-line{flex:1;height:1px;background:var(--border);margin:0 16px;}

        /* Layout */
        .form-grid{display:grid;grid-template-columns:1fr 320px;gap:28px;align-items:start;}
        .form-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .form-card-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
        .form-card-head .icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;}
        .form-card-head h3{font-family:'Playfair Display',serif;font-size:17px;font-weight:600;}
        .form-card-body{padding:24px;}
        .form-label{font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;display:block;letter-spacing:.3px;}
        .form-control,.form-select{border:1.5px solid var(--border);border-radius:8px;padding:11px 14px;font-size:14px;font-family:'Jost',sans-serif;width:100%;background:#fff;transition:border-color .2s;}
        .form-control:focus,.form-select:focus{outline:none;border-color:var(--stone);box-shadow:0 0 0 3px rgba(44,36,22,.06);}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        .mb-14{margin-bottom:14px;}

        /* Summary card */
        .summary-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;position:sticky;top:calc(var(--nav-h)+20px);}
        .summary-img{width:100%;height:160px;object-fit:cover;}
        .summary-img-placeholder{width:100%;height:160px;background:var(--sand);display:flex;align-items:center;justify-content:center;font-size:40px;color:var(--muted);opacity:.4;}
        .summary-body{padding:20px;}
        .summary-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:600;margin-bottom:4px;}
        .summary-dates{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-bottom:16px;}
        .price-row{display:flex;justify-content:space-between;font-size:13px;padding:7px 0;border-bottom:1px solid #f4efe6;}
        .price-row:last-child{border-bottom:none;}
        .price-row.total{font-weight:700;font-size:16px;border-top:2px solid var(--border);padding-top:12px;margin-top:4px;}
        .deposit-box{background:rgba(196,103,58,.07);border:1px solid rgba(196,103,58,.2);border-radius:10px;padding:12px;margin:12px 0;font-size:12px;color:var(--terracotta);text-align:center;}
        .deposit-amount{font-size:18px;font-weight:700;font-family:'Playfair Display',serif;display:block;margin:4px 0;}
        .night-breakdown{max-height:140px;overflow-y:auto;}
        .night-row{display:flex;justify-content:space-between;font-size:12px;padding:4px 0;color:var(--muted);}
        .night-row.weekend{color:var(--gold);}

        /* Submit */
        .btn-submit{background:var(--stone);color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:700;font-family:'Jost',sans-serif;width:100%;cursor:pointer;transition:all .2s;margin-top:8px;}
        .btn-submit:hover{background:var(--gold);color:var(--stone);}
        .terms-note{font-size:11px;color:var(--muted);text-align:center;margin-top:8px;line-height:1.5;}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-danger{background:#fee2e2;color:#dc2626;}
    </style>
</head>
<body>
<nav class="nav">
    <a href="{{ route('home') }}" class="nav-brand">Villa Elena</a>
</nav>

<main class="main">
    <div class="breadcrumb-row">
        <a href="{{ route('home') }}">Home</a> <span>›</span>
        <a href="{{ route('portal.property', $property) }}">{{ $property->property_name }}</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">Complete Booking</span>
    </div>

    {{-- Steps --}}
    <div class="steps">
        <div class="step done"><div class="step-num"><i class="bi bi-check"></i></div><span class="step-label">Select Dates</span></div>
        <div class="step-line"></div>
        <div class="step active"><div class="step-num">2</div><span class="step-label">Your Details</span></div>
        <div class="step-line"></div>
        <div class="step inactive"><div class="step-num">3</div><span class="step-label">Confirmation</span></div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('portal.book.submit', $property) }}">
        @csrf
        <input type="hidden" name="checkin"  value="{{ $checkin->toDateString() }}">
        <input type="hidden" name="checkout" value="{{ $checkout->toDateString() }}">
        <input type="hidden" name="guests"   value="{{ $request->guests }}">

        <div class="form-grid">
            <div>
                {{-- Guest Info --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-person"></i></div>
                        <h3>Guest Information</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="{{ auth()->user()->full_name }}" readonly style="background:#f9f5ee;">
                            </div>
                            <div>
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control" value="{{ auth()->user()->email }}" readonly style="background:#f9f5ee;">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" value="{{ auth()->user()->phone ?? '' }}" readonly style="background:#f9f5ee;">
                        </div>
                    </div>
                </div>

                {{-- Stay Details --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-calendar3"></i></div>
                        <h3>Stay Details</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Check-in</label>
                                <input type="text" class="form-control" value="{{ $checkin->format('l, M d, Y') }}" readonly style="background:#f9f5ee;">
                            </div>
                            <div>
                                <label class="form-label">Check-out</label>
                                <input type="text" class="form-control" value="{{ $checkout->format('l, M d, Y') }}" readonly style="background:#f9f5ee;">
                            </div>
                        </div>
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Duration</label>
                                <input type="text" class="form-control" value="{{ $nights }} night{{ $nights!=1?'s':'' }}" readonly style="background:#f9f5ee;">
                            </div>
                            <div>
                                <label class="form-label">Guests</label>
                                <input type="text" class="form-control" value="{{ $request->guests }} guest{{ $request->guests > 1 ? 's' : '' }}" readonly style="background:#f9f5ee;">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Special Requests <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
                            <textarea name="special_requests" class="form-control" rows="3"
                                placeholder="Early check-in, dietary requirements, celebrations, etc.">{{ old('special_requests') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Policies --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-info-circle"></i></div>
                        <h3>Booking Policies</h3>
                    </div>
                    <div class="form-card-body">
                        <div style="font-size:13px;color:var(--muted);line-height:1.8;">
                            <p style="margin-bottom:8px;"><strong style="color:var(--stone);">Deposit Required:</strong> A {{ $depositPct }}% deposit (₱{{ number_format($depositAmount,2) }}) is required to confirm your booking.</p>
                            <p style="margin-bottom:8px;"><strong style="color:var(--stone);">Check-in Time:</strong> 2:00 PM onwards</p>
                            <p style="margin-bottom:8px;"><strong style="color:var(--stone);">Check-out Time:</strong> 12:00 PM</p>
                            <p><strong style="color:var(--stone);">Cancellation:</strong> Free cancellation 48 hours before check-in. Later cancellations may forfeit the deposit.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div>
                <div class="summary-card">
                    @if($property->primaryImage)
                        <img src="{{ asset('storage/'.$property->primaryImage->image_path) }}" class="summary-img" alt="">
                    @else
                        <div class="summary-img-placeholder"><i class="bi bi-house"></i></div>
                    @endif
                    <div class="summary-body">
                        <div class="summary-name">{{ $property->property_name }}</div>
                        <div class="summary-dates">
                            <i class="bi bi-calendar3" style="font-size:11px;"></i>
                            {{ $checkin->format('M d') }} → {{ $checkout->format('M d, Y') }}
                        </div>

                        {{-- Night Breakdown --}}
                        <div class="night-breakdown">
                            @foreach($nightBreakdown as $night)
                            <div class="night-row {{ $night['weekend'] ? 'weekend' : '' }}">
                                <span>{{ $night['date'] }} {{ $night['weekend'] ? '★' : '' }}</span>
                                <span>₱{{ number_format($night['price'],0) }}</span>
                            </div>
                            @endforeach
                        </div>

                        <div style="height:1px;background:var(--border);margin:12px 0;"></div>

                        <div class="price-row">
                            <span style="color:var(--muted);">{{ $nights }} night{{ $nights!=1?'s':'' }}</span>
                            <span>₱{{ number_format($baseAmount,2) }}</span>
                        </div>
                        <div class="price-row total">
                            <span>Total</span>
                            <span>₱{{ number_format($baseAmount,2) }}</span>
                        </div>

                        <div class="deposit-box">
                            Required deposit ({{ $depositPct }}%)
                            <span class="deposit-amount">₱{{ number_format($depositAmount,2) }}</span>
                            Pay upon confirmation
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="bi bi-calendar-check me-2"></i> Confirm Booking Request
                        </button>
                        <div class="terms-note">
                            By confirming, you agree to our booking policies.<br>
                            You will be contacted to arrange payment.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.chatbot')
</body>
</html>