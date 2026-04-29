<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>New Booking — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0d1b2a;--navy-mid:#1a2f45;--gold:#c9a84c;--gold-light:#e8c97a;--gold-dim:rgba(201,168,76,0.15);--off-white:#f4f6f9;--border:#e2e8f0;--text-main:#1a2f45;--text-muted:#6b7a8d;--sidebar-w:260px;--topbar-h:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--off-white);color:var(--text-main);}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--navy);display:flex;flex-direction:column;z-index:1000;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .sidebar-brand h1{font-family:'Cormorant Garamond',serif;color:var(--gold-light);font-size:22px;font-weight:700;}
        .sidebar-brand p{color:rgba(255,255,255,0.35);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-top:3px;}
        .sidebar-section{padding:20px 16px 8px;}
        .sidebar-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:0 8px;margin-bottom:6px;}
        .nav-item-custom{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:14px;transition:all .2s;margin-bottom:2px;}
        .nav-item-custom:hover{background:rgba(255,255,255,0.07);color:#fff;}
        .nav-item-custom.active{background:var(--gold-dim);color:var(--gold-light);font-weight:500;}
        .nav-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:rgba(255,255,255,0.05);}
        .nav-item-custom.active .nav-icon{background:var(--gold-dim);color:var(--gold);}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .user-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);}
        .user-avatar{width:36px;height:36px;border-radius:50%;background:var(--gold-dim);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;}
        .user-info .name{color:#fff;font-size:13px;font-weight:500;}
        .user-info .role-badge{font-size:10px;color:var(--gold);letter-spacing:0.5px;text-transform:uppercase;}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;z-index:900;}
        .topbar-left h2{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;}
        .topbar-left p{font-size:12px;color:var(--text-muted);margin-top:1px;}
        .logout-btn{display:flex;align-items:center;gap:7px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;}
        .logout-btn:hover{background:#ef4444;color:white;border-color:#ef4444;}
        .main-content{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);margin-bottom:24px;}
        .breadcrumb-row a{color:var(--text-muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--navy);}
        .breadcrumb-row .sep{color:#cbd5e1;}
        .breadcrumb-row .current{color:var(--text-main);font-weight:500;}
        .form-grid{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;}
        .form-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .form-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
        .form-card-header .card-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;}
        .form-card-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        .form-card-body{padding:24px;}
        .form-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;display:block;}
        .req{color:#ef4444;margin-left:3px;}
        .form-control,.form-select{border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;font-size:14px;font-family:'DM Sans',sans-serif;width:100%;transition:border-color .2s;background:#fff;}
        .form-control:focus,.form-select:focus{outline:none;border-color:var(--navy-mid);box-shadow:0 0 0 3px rgba(26,47,69,0.08);}
        .is-invalid{border-color:#ef4444 !important;}
        .invalid-feedback{font-size:12px;color:#ef4444;margin-top:4px;display:block;}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .btn-submit{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:12px 24px;font-size:14px;font-weight:600;width:100%;cursor:pointer;font-family:'DM Sans',sans-serif;}
        .btn-submit:hover{opacity:.88;}
        .btn-cancel{display:block;text-align:center;margin-top:10px;color:var(--text-muted);font-size:13px;text-decoration:none;padding:8px;border-radius:8px;}
        .btn-cancel:hover{background:#f1f5f9;color:var(--text-main);}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-danger{background:#fee2e2;color:#dc2626;}

        /* Price Preview */
        .price-preview{background:var(--navy);border-radius:12px;padding:20px;color:#fff;}
        .price-preview h4{font-family:'Cormorant Garamond',serif;font-size:16px;color:var(--gold-light);margin-bottom:14px;}
        .price-row{display:flex;justify-content:space-between;font-size:13px;padding:5px 0;border-bottom:1px solid rgba(255,255,255,0.08);}
        .price-row:last-child{border-bottom:none;font-weight:700;font-size:16px;margin-top:6px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.2);}
        .price-row span:first-child{color:rgba(255,255,255,0.6);}
    </style>
</head>
<body>


<header class="topbar">
    <div class="topbar-left">
        <h2>New Booking</h2>
        <p>Create a booking manually or for a walk-in guest</p>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
</header>

<main class="main-content">

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.bookings.index') }}">Bookings</a>
        <span class="sep">›</span>
        <span class="current">New Booking</span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach($errors->all() as $error){{ $error }}. @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bookings.store') }}" id="bookingForm">
        @csrf

        <div class="form-grid">

            <div>
                {{-- Guest --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#e0f2fe;color:#0369a1;"><i class="bi bi-person"></i></div>
                        <h3>Guest</h3>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label">Select Guest <span class="req">*</span></label>
                        <select name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">Choose a guest...</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('user_id')==$customer->id?'selected':'' }}>
                                    {{ $customer->full_name }} — {{ $customer->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <div style="margin-top:10px;font-size:12px;color:var(--text-muted);">
                            Guest not in the list? <a href="{{ route('admin.users.create') }}" style="color:#2e5fa3;">Create new guest account →</a>
                        </div>
                    </div>
                </div>

                {{-- Property & Dates --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#dcfce7;color:#15803d;"><i class="bi bi-house-door"></i></div>
                        <h3>Property & Dates</h3>
                    </div>
                    <div class="form-card-body">
                        <div style="margin-bottom:16px;">
                            <label class="form-label">Property <span class="req">*</span></label>
                            <select name="property_id" id="propertySelect"
                                class="form-select @error('property_id') is-invalid @enderror" required>
                                <option value="">Choose a property...</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}"
                                        data-price="{{ $property->base_price }}"
                                        data-weekend="{{ $property->weekend_price ?? $property->base_price }}"
                                        {{ old('property_id')==$property->id?'selected':'' }}>
                                        {{ $property->property_name }} — ₱{{ number_format($property->base_price,2) }}/night
                                    </option>
                                @endforeach
                            </select>
                            @error('property_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="two-col" style="margin-bottom:16px;">
                            <div>
                                <label class="form-label">Check-in Date <span class="req">*</span></label>
                                <input type="date" name="check_in_date" id="checkIn" class="form-control @error('check_in_date') is-invalid @enderror"
                                    value="{{ old('check_in_date') }}" min="{{ date('Y-m-d') }}" required>
                                @error('check_in_date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div>
                                <label class="form-label">Check-out Date <span class="req">*</span></label>
                                <input type="date" name="check_out_date" id="checkOut" class="form-control @error('check_out_date') is-invalid @enderror"
                                    value="{{ old('check_out_date') }}" required>
                                @error('check_out_date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="two-col">
                            <div>
                                <label class="form-label">Number of Guests <span class="req">*</span></label>
                                <input type="number" name="num_guests" class="form-control @error('num_guests') is-invalid @enderror"
                                    value="{{ old('num_guests', 1) }}" min="1" required>
                                @error('num_guests')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div>
                                <label class="form-label">Booking Source <span class="req">*</span></label>
                                <select name="source" class="form-select" required>
                                    <option value="walk_in" {{ old('source')=='walk_in'?'selected':'' }}>🚶 Walk-in</option>
                                    <option value="phone"   {{ old('source')=='phone'  ?'selected':'' }}>📞 Phone</option>
                                    <option value="online"  {{ old('source')=='online' ?'selected':'' }}>🌐 Online</option>
                                    <option value="partner" {{ old('source')=='partner'?'selected':'' }}>🤝 Partner</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Special Requests --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-chat-dots"></i></div>
                        <h3>Special Requests</h3>
                    </div>
                    <div class="form-card-body">
                        <textarea name="special_requests" class="form-control" rows="3"
                            placeholder="Any special requests or notes for this booking...">{{ old('special_requests') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Price Preview --}}
            <div>
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-receipt"></i></div>
                        <h3>Price Preview</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="price-preview">
                            <h4>Estimated Cost</h4>
                            <div class="price-row">
                                <span>Nights</span>
                                <span id="previewNights">—</span>
                            </div>
                            <div class="price-row">
                                <span>Rate / night</span>
                                <span id="previewRate">—</span>
                            </div>
                            <div class="price-row">
                                <span>Total</span>
                                <span id="previewTotal">—</span>
                            </div>
                        </div>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:10px;text-align:center;">
                            Final price calculated on submit based on seasonal pricing rules.
                        </p>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-body">
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-calendar-plus me-2"></i> Create Booking
                        </button>
                        <a href="{{ route('admin.bookings.index') }}" class="btn-cancel">Cancel</a>
                    </div>
                </div>
            </div>

        </div>
    </form>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updatePreview() {
    const checkIn  = document.getElementById('checkIn').value;
    const checkOut = document.getElementById('checkOut').value;
    const select   = document.getElementById('propertySelect');
    const option   = select.options[select.selectedIndex];

    if (!checkIn || !checkOut || !option.value) return;

    const d1 = new Date(checkIn);
    const d2 = new Date(checkOut);
    const nights = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
    if (nights <= 0) return;

    const basePrice    = parseFloat(option.dataset.price) || 0;
    const weekendPrice = parseFloat(option.dataset.weekend) || basePrice;

    // Simple estimate
    let total = 0;
    for (let i = 0; i < nights; i++) {
        const d = new Date(d1);
        d.setDate(d.getDate() + i);
        const day = d.getDay();
        total += (day === 0 || day === 6) ? weekendPrice : basePrice;
    }

    document.getElementById('previewNights').textContent = nights + ' night' + (nights !== 1 ? 's' : '');
    document.getElementById('previewRate').textContent = '₱' + basePrice.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('previewTotal').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});

    // Set min check-out to day after check-in
    const minOut = new Date(d1);
    minOut.setDate(minOut.getDate() + 1);
    document.getElementById('checkOut').min = minOut.toISOString().split('T')[0];
}

document.getElementById('checkIn').addEventListener('change', updatePreview);
document.getElementById('checkOut').addEventListener('change', updatePreview);
document.getElementById('propertySelect').addEventListener('change', updatePreview);
</script>
@include('admin.partials.sidebar')
@include('admin.partials.realtime') 

</body>
</html>