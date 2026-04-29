{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- SAVE AS: resources/views/portal/property.blade.php              --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $property->property_name }} — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--sand:#f5f0e8;--stone:#2c2416;--cream:#fdfbf7;--terracotta:#c4673a;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;--white:#ffffff;--nav-h:72px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--cream);color:var(--stone);}
        .nav{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:rgba(44,36,22,.97);backdrop-filter:blur(12px);z-index:200;display:flex;align-items:center;justify-content:space-between;padding:0 40px;}
        .nav-brand{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:20px;text-decoration:none;}
        .nav-brand em{color:var(--gold-light);font-style:italic;}
        .nav-right{display:flex;align-items:center;gap:10px;}
        .nav-btn{padding:8px 18px;border-radius:7px;font-size:13px;font-weight:500;text-decoration:none;font-family:'Jost',sans-serif;transition:all .2s;}
        .nav-btn-ghost{color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.2);}
        .nav-btn-ghost:hover{color:#fff;}
        .nav-btn-gold{background:var(--gold);color:var(--stone);font-weight:600;}
        .nav-btn-gold:hover{background:var(--gold-light);}
        .main{margin-top:var(--nav-h);max-width:1100px;margin-left:auto;margin-right:auto;padding:40px;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:28px;}
        .breadcrumb-row a{color:var(--muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--stone);}

        /* ── Gallery ── */
        .gallery{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:300px 200px;gap:10px;border-radius:20px;overflow:hidden;margin-bottom:36px;}
        .gallery-main{grid-row:1/-1;position:relative;}
        .gallery-img{width:100%;height:100%;object-fit:cover;display:block;}
        .gallery-placeholder{width:100%;height:100%;background:var(--sand);display:flex;align-items:center;justify-content:center;font-size:64px;color:var(--muted);opacity:.3;}
        .gallery-count{position:absolute;bottom:14px;right:14px;background:rgba(0,0,0,.6);color:#fff;padding:5px 12px;border-radius:100px;font-size:12px;}

        /* ── Layout ── */
        .detail-grid{display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start;}

        /* ── Property Info ── */
        .prop-type{font-size:11px;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:8px;}
        .prop-name{font-family:'Playfair Display',serif;font-size:36px;font-weight:700;margin-bottom:14px;line-height:1.1;}
        .prop-meta{display:flex;gap:20px;font-size:13px;color:var(--muted);margin-bottom:24px;flex-wrap:wrap;}
        .prop-meta span{display:flex;align-items:center;gap:5px;}
        .section-title{font-family:'Playfair Display',serif;font-size:20px;font-weight:600;margin-bottom:14px;padding-top:28px;border-top:1px solid var(--border);}
        .section-title:first-of-type{border-top:none;padding-top:0;}
        .description{font-size:14px;line-height:1.8;color:#5a4f3e;}

        /* ── Amenities ── */
        .amenities-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;}
        .amenity-item{display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--sand);border-radius:10px;font-size:13px;}
        .amenity-item i{color:var(--gold);font-size:14px;}

        /* ── Booking Card ── */
        .booking-card{background:#fff;border-radius:20px;border:1px solid var(--border);padding:24px;position:sticky;top:calc(var(--nav-h) + 20px);box-shadow:0 8px 32px rgba(44,36,22,.08);}
        .booking-price{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;margin-bottom:4px;}
        .booking-price span{font-size:15px;font-weight:400;color:var(--muted);font-family:'Jost',sans-serif;}
        .price-weekend{font-size:12px;color:var(--gold);margin-bottom:18px;}
        .form-label{font-size:11px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;display:block;font-weight:600;}
        .form-control,.form-select{border:1.5px solid var(--border);border-radius:8px;padding:11px 14px;font-size:14px;font-family:'Jost',sans-serif;width:100%;background:#fff;transition:border-color .2s;}
        .form-control:focus,.form-select:focus{outline:none;border-color:var(--stone);}
        .price-preview{background:var(--sand);border-radius:12px;padding:16px;margin:16px 0;}
        .price-line{display:flex;justify-content:space-between;font-size:13px;padding:5px 0;color:var(--muted);}
        .price-line.total{font-weight:700;font-size:15px;color:var(--stone);border-top:1px solid var(--border);padding-top:10px;margin-top:4px;}
        .price-deposit{font-size:12px;color:var(--terracotta);text-align:center;margin-top:8px;}
        .btn-book-now{background:var(--stone);color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:700;font-family:'Jost',sans-serif;width:100%;cursor:pointer;transition:all .2s;margin-top:8px;}
        .btn-book-now:hover{background:var(--gold);color:var(--stone);}
        .btn-book-now:disabled{background:var(--border);color:var(--muted);cursor:not-allowed;}
        .login-prompt{background:var(--sand);border-radius:10px;padding:14px;text-align:center;font-size:13px;color:var(--muted);margin-top:8px;}
        .login-prompt a{color:var(--gold);font-weight:600;text-decoration:none;}
        .unavail-banner{background:#fee2e2;color:#dc2626;border-radius:10px;padding:12px;text-align:center;font-size:13px;font-weight:500;}
        .is-invalid{border-color:#dc2626 !important;}
        .invalid-feedback{font-size:12px;color:#dc2626;margin-top:4px;display:block;}

        @media(max-width:900px){
            .detail-grid{grid-template-columns:1fr;}
            .gallery{grid-template-columns:1fr;grid-template-rows:260px 150px;}
            .gallery-main{grid-row:auto;}
        }
    </style>
</head>
<body>
<nav class="nav">
    <a href="{{ route('home') }}" class="nav-brand">Villa <em>Elena</em></a>
    <div class="nav-right">
        @auth
            <a href="{{ route('customer.home') }}" class="nav-btn nav-btn-ghost">My Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="nav-btn nav-btn-ghost">Sign In</a>
            <a href="{{ route('register') }}" class="nav-btn nav-btn-gold">Register</a>
        @endauth
    </div>
</nav>

<main class="main">
    <div class="breadcrumb-row">
        <a href="{{ route('home') }}">Home</a>
        <span>›</span>
        <a href="{{ route('home') }}#properties">Properties</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">{{ $property->property_name }}</span>
    </div>

    @if($errors->any())
        <div style="background:#fee2e2;color:#dc2626;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
        </div>
    @endif

    {{-- Gallery --}}
    <div class="gallery">
        <div class="gallery-main">
            @if($property->primaryImage)
                <img src="{{ asset('storage/'.$property->primaryImage->image_path) }}" class="gallery-img" alt="{{ $property->property_name }}">
                @if($property->images->count() > 1)
                    <div class="gallery-count"><i class="bi bi-images"></i> {{ $property->images->count() }} photos</div>
                @endif
            @else
                <div class="gallery-placeholder"><i class="bi bi-house"></i></div>
            @endif
        </div>
        @foreach($property->images->where('is_primary', 0)->take(2) as $img)
        <div>
            <img src="{{ asset('storage/'.$img->image_path) }}" class="gallery-img" alt="">
        </div>
        @endforeach
    </div>

    <div class="detail-grid">

        {{-- Left: Property Info --}}
        <div>
            <div class="prop-type">{{ ucfirst($property->type) }}</div>
            <div class="prop-name">{{ $property->property_name }}</div>
            <div class="prop-meta">
                <span><i class="bi bi-people"></i> Up to {{ $property->max_capacity }} guests</span>
                @if($property->floor_area)
                <span><i class="bi bi-arrows-angle-expand"></i> {{ $property->floor_area }} sqm</span>
                @endif
                <span><i class="bi bi-geo-alt"></i> Villa Elena Resort</span>
            </div>

            @if($property->description)
            <div class="section-title" style="border-top:none;padding-top:0;">About This Property</div>
            <p class="description">{{ $property->description }}</p>
            @endif

            {{-- Amenities --}}
            @php
                $amenities = is_array($property->amenities)
                    ? $property->amenities
                    : json_decode($property->amenities ?? '[]', true);
                $amenityIcons = ['pool'=>'bi-water','wifi'=>'bi-wifi','ac'=>'bi-thermometer-snow','parking'=>'bi-car-front','kitchen'=>'bi-cup-hot','bbq'=>'bi-fire','tv'=>'bi-tv','washer'=>'bi-basket','gym'=>'bi-bicycle','bar'=>'bi-cup-straw','breakfast'=>'bi-egg-fried','spa'=>'bi-flower1'];
            @endphp
            @if(count($amenities ?? []))
            <div class="section-title">Amenities</div>
            <div class="amenities-grid">
                @foreach($amenities as $amenity)
                <div class="amenity-item">
                    <i class="bi {{ $amenityIcons[$amenity] ?? 'bi-check-circle' }}"></i>
                    {{ ucwords(str_replace('_',' ',$amenity)) }}
                </div>
                @endforeach
            </div>
            @endif

            {{-- Pricing Info --}}
            <div class="section-title">Pricing</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:400px;">
                <div style="background:var(--sand);border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Weekday Rate</div>
                    <div style="font-family:'Playfair Display',serif;font-size:24px;font-weight:700;">₱{{ number_format($property->base_price,0) }}</div>
                    <div style="font-size:12px;color:var(--muted);">per night</div>
                </div>
                @if($property->weekend_price && $property->weekend_price != $property->base_price)
                <div style="background:rgba(184,148,63,.1);border:1px solid rgba(184,148,63,.25);border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:11px;color:var(--gold);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Weekend Rate</div>
                    <div style="font-family:'Playfair Display',serif;font-size:24px;font-weight:700;color:var(--stone);">₱{{ number_format($property->weekend_price,0) }}</div>
                    <div style="font-size:12px;color:var(--muted);">Fri–Sun</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Right: Booking Card --}}
        <div>
            <div class="booking-card">
                @if($property->status === 'available')
                    <div class="booking-price">₱{{ number_format($property->base_price,0) }} <span>/ night</span></div>
                    @if($property->weekend_price && $property->weekend_price != $property->base_price)
                    <div class="price-weekend">₱{{ number_format($property->weekend_price,0) }} on weekends</div>
                    @endif

                    <form method="GET" action="{{ route('portal.book', $property) }}" id="bookingForm">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Check-in Date</label>
                            <input type="date" name="checkin" id="checkin" class="form-control"
                                value="{{ $checkin }}" min="{{ date('Y-m-d') }}" required>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Check-out Date</label>
                            <input type="date" name="checkout" id="checkout" class="form-control"
                                value="{{ $checkout }}" required>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label class="form-label">Guests</label>
                            <select name="guests" class="form-select">
                                @for($g = 1; $g <= $property->max_capacity; $g++)
                                <option value="{{ $g }}" {{ (int)$guests === $g ? 'selected' : '' }}>
                                    {{ $g }} guest{{ $g > 1 ? 's' : '' }}
                                </option>
                                @endfor
                            </select>
                        </div>

                        {{-- Price Preview --}}
                        <div class="price-preview" id="pricePreview" style="display:none;">
                            <div class="price-line">
                                <span id="previewNights">— nights</span>
                                <span id="previewBase">—</span>
                            </div>
                            <div class="price-line total">
                                <span>Total</span>
                                <span id="previewTotal">—</span>
                            </div>
                        </div>

                        @auth
                            <button type="submit" class="btn-book-now" id="bookBtn">
                                Check Availability
                            </button>
                        @else
                            <button type="button" class="btn-book-now" onclick="window.location='{{ route('login') }}'">
                                Sign In to Book
                            </button>
                            <div class="login-prompt">
                                Don't have an account?
                                <a href="{{ route('register') }}">Create one free →</a>
                            </div>
                        @endauth
                    </form>

                    {{-- Booked dates notice --}}
                    @if($bookedRanges->count())
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size:12px;color:var(--muted);">
                        <i class="bi bi-info-circle me-1"></i>
                        Some dates may not be available. We'll confirm availability when you proceed.
                    </div>
                    @endif

                @else
                    <div class="unavail-banner">
                        <i class="bi bi-x-circle me-2"></i>
                        This property is currently unavailable.<br>
                        <a href="{{ route('home') }}" style="color:#dc2626;font-weight:600;">View other properties →</a>
                    </div>
                @endif
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_PRICE    = {{ $property->base_price }};
const WEEKEND_PRICE = {{ $property->weekend_price ?? $property->base_price }};

function updatePreview() {
    const ci = document.getElementById('checkin').value;
    const co = document.getElementById('checkout').value;
    if (!ci || !co) { document.getElementById('pricePreview').style.display = 'none'; return; }
    const d1 = new Date(ci), d2 = new Date(co);
    const nights = Math.round((d2 - d1) / 86400000);
    if (nights <= 0) return;
    let total = 0;
    for (let i = 0; i < nights; i++) {
        const d = new Date(d1); d.setDate(d.getDate() + i);
        total += (d.getDay() === 0 || d.getDay() === 6) ? WEEKEND_PRICE : BASE_PRICE;
    }
    document.getElementById('previewNights').textContent = nights + ' night' + (nights > 1 ? 's' : '');
    document.getElementById('previewBase').textContent   = '₱' + BASE_PRICE.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('previewTotal').textContent  = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('pricePreview').style.display = 'block';
    document.getElementById('bookBtn') && (document.getElementById('bookBtn').textContent = 'Reserve Now →');
}

document.getElementById('checkin')?.addEventListener('change', function() {
    const co = document.getElementById('checkout');
    const d  = new Date(this.value); d.setDate(d.getDate() + 1);
    co.min   = d.toISOString().split('T')[0];
    if (co.value && co.value <= this.value) { co.value = d.toISOString().split('T')[0]; }
    updatePreview();
});
document.getElementById('checkout')?.addEventListener('change', updatePreview);

// Run on load if dates pre-filled
updatePreview();
</script>
@include('partials.chatbot')
</body>
</html>