<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Booking — Villa Elena Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0D1B2A;--navy-mid:#1A2F45;--gold:#C9A84C;--gold-light:#E8C97A;--bg:#F4F6F9;--white:#fff;--border:#E2E8F0;--muted:#6B7A8D;--sidebar:260px;--topbar:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:var(--navy);z-index:100;display:flex;flex-direction:column;}
        .sidebar-brand{padding:22px 24px 18px;border-bottom:1px solid rgba(255,255,255,.07);text-decoration:none;display:block;}
        .brand-name{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:18px;}
        .brand-role{color:rgba(255,255,255,.3);font-size:11px;letter-spacing:1px;text-transform:uppercase;margin-top:2px;}
        .sidebar-nav{flex:1;padding:16px 12px;}
        .nav-label{font-size:10px;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:1.5px;padding:10px 12px 6px;font-weight:600;}
        .nav-item{display:flex;align-items:center;gap:11px;padding:10px 14px;border-radius:9px;color:rgba(255,255,255,.55);text-decoration:none;font-size:13.5px;font-weight:500;margin-bottom:2px;transition:all .2s;}
        .nav-item:hover{background:rgba(255,255,255,.06);color:#fff;}
        .nav-item.active{background:rgba(201,168,76,.15);color:var(--gold-light);}
        .nav-item i{font-size:16px;width:20px;text-align:center;}
        .sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.07);}
        .staff-info{display:flex;align-items:center;gap:10px;}
        .staff-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold);color:var(--navy);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;}
        .staff-name{color:rgba(255,255,255,.7);font-size:13px;}
        .staff-tag{color:rgba(255,255,255,.3);font-size:11px;}
        .logout-btn{margin-left:auto;color:rgba(255,255,255,.3);font-size:18px;text-decoration:none;transition:color .2s;background:none;border:none;cursor:pointer;}
        .logout-btn:hover{color:#fff;}
        .topbar{position:fixed;top:0;left:var(--sidebar);right:0;height:var(--topbar);background:#fff;border-bottom:1px solid var(--border);z-index:99;display:flex;align-items:center;justify-content:space-between;padding:0 28px;}
        .topbar-title{font-family:'Playfair Display',serif;font-size:20px;color:var(--navy);font-weight:700;}
        .topbar-sub{font-size:13px;color:var(--muted);margin-top:2px;}
        .main{margin-left:var(--sidebar);margin-top:var(--topbar);padding:28px;max-width:900px;}

        /* Form */
        .section-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .section-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
        .section-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
        .section-head h3{font-family:'Playfair Display',serif;font-size:16px;font-weight:600;}
        .section-body{padding:22px;}
        .form-label{font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;display:block;letter-spacing:.2px;}
        .form-control,.form-select{border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;background:#fff;transition:border-color .2s;}
        .form-control:focus,.form-select:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(13,27,42,.06);}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
        .mb-14{margin-bottom:14px;}
        .is-invalid{border-color:#dc2626!important;}
        .invalid-feedback{font-size:11px;color:#dc2626;margin-top:3px;}

        /* Guest toggle */
        .guest-toggle{display:flex;gap:8px;margin-bottom:18px;}
        .toggle-btn{flex:1;padding:10px;border-radius:9px;border:1.5px solid var(--border);background:#fff;font-size:13px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;color:var(--muted);transition:all .2s;text-align:center;}
        .toggle-btn.active{background:var(--navy);color:#fff;border-color:var(--navy);}

        /* Price preview */
        .price-preview{background:#f8fafc;border-radius:10px;border:1px solid var(--border);padding:16px;margin-top:14px;}
        .price-row{display:flex;justify-content:space-between;font-size:13px;padding:5px 0;border-bottom:1px solid #f1f5f9;}
        .price-row:last-child{border-bottom:none;}
        .price-row.total{font-weight:700;font-size:15px;border-top:2px solid var(--border);padding-top:10px;margin-top:4px;}
        .price-row.balance{color:#dc2626;font-weight:600;}
        .price-row.paid{color:#16a34a;}

        /* Buttons */
        .btn-submit{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:13px 28px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:8px;transition:all .2s;}
        .btn-submit:hover{background:var(--gold);color:var(--navy);}
        .btn-back{background:#fff;color:var(--muted);border:1.5px solid var(--border);border-radius:9px;padding:13px 24px;font-size:14px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;}
        .btn-back:hover{border-color:var(--navy);color:var(--navy);}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;border:none;margin-bottom:20px;}
        .alert-danger{background:#fee2e2;color:#dc2626;}
        .optional-tag{color:var(--muted);font-weight:400;font-size:11px;}
        .new-guest-password-note{background:#fef9c3;border-radius:8px;padding:10px 14px;font-size:12px;color:#a16207;margin-top:10px;}
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="{{ route('staff.frontdesk') }}" class="sidebar-brand">
        <div class="brand-name">Villa Elena</div>
        <div class="brand-role">Staff Portal</div>
    </a>
    <nav class="sidebar-nav">
        <div class="nav-label">Operations</div>
        <a href="{{ route('staff.frontdesk') }}" class="nav-item">
            <i class="bi bi-house-door"></i> Frontdesk
        </a>
        <a href="{{ route('staff.walkin') }}" class="nav-item active">
            <i class="bi bi-person-plus"></i> Walk-in Booking
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="staff-info">
            <div class="staff-avatar">{{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}</div>
            <div>
                <div class="staff-name">{{ auth()->user()->full_name }}</div>
                <div class="staff-tag">Staff</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;margin-left:auto;">
                @csrf
                <button type="submit" class="logout-btn" title="Sign out">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="topbar">
    <div>
        <div class="topbar-title">Walk-in Booking</div>
        <div class="topbar-sub">Create a new booking for a walk-in guest</div>
    </div>
</div>

<main class="main">

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('staff.walkin.store') }}" id="walkinForm">
        @csrf

        {{-- Guest Information --}}
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-person"></i></div>
                <h3>Guest Information</h3>
            </div>
            <div class="section-body">
                <div class="guest-toggle">
                    <button type="button" class="toggle-btn active" id="btnExisting" onclick="setGuestType('existing')">
                        <i class="bi bi-person-check me-1"></i> Existing Guest
                    </button>
                    <button type="button" class="toggle-btn" id="btnNew" onclick="setGuestType('new')">
                        <i class="bi bi-person-plus me-1"></i> New Guest
                    </button>
                </div>
                <input type="hidden" name="guest_type" id="guestType" value="existing">

                {{-- Existing Guest --}}
                <div id="existingGuestFields">
                    <label class="form-label">Select Guest</label>
                    <select name="user_id" class="form-select {{ $errors->has('user_id') ? 'is-invalid' : '' }}">
                        <option value="">-- Select existing guest --</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ old('user_id') == $customer->id ? 'selected' : '' }}>
                            {{ $customer->full_name }} — {{ $customer->email }}
                        </option>
                        @endforeach
                    </select>
                    @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- New Guest --}}
                <div id="newGuestFields" style="display:none;">
                    <div class="two-col mb-14">
                        <div>
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control {{ $errors->has('full_name') ? 'is-invalid' : '' }}"
                                value="{{ old('full_name') }}" placeholder="Juan Dela Cruz">
                            @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                value="{{ old('email') }}" placeholder="juan@email.com">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Phone <span class="optional-tag">(optional)</span></label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="09XX XXX XXXX">
                    </div>
                    <div class="new-guest-password-note">
                        <i class="bi bi-info-circle me-1"></i>
                        A guest account will be created with default password: <strong>VillaElena@2026</strong>
                        — advise the guest to change it after logging in.
                    </div>
                </div>
            </div>
        </div>

        {{-- Stay Details --}}
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-calendar3"></i></div>
                <h3>Stay Details</h3>
            </div>
            <div class="section-body">
                <div class="mb-14">
                    <label class="form-label">Property</label>
                    <select name="property_id" id="propertySelect" class="form-select {{ $errors->has('property_id') ? 'is-invalid' : '' }}" onchange="updatePrice()">
                        <option value="">-- Select available property --</option>
                        @foreach($availableProperties as $property)
                        <option value="{{ $property->id }}"
                            data-base="{{ $property->base_price }}"
                            data-weekend="{{ $property->weekend_price ?? $property->base_price }}"
                            data-max="{{ $property->max_capacity }}"
                            {{ old('property_id') == $property->id ? 'selected' : '' }}>
                            {{ $property->property_name }} —
                            ₱{{ number_format($property->base_price, 0) }}/night
                            (Max {{ $property->max_capacity }} guests)
                        </option>
                        @endforeach
                    </select>
                    @error('property_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="two-col mb-14">
                    <div>
                        <label class="form-label">Check-in Date</label>
                        <input type="date" name="check_in_date" id="checkinDate"
                            class="form-control {{ $errors->has('check_in_date') ? 'is-invalid' : '' }}"
                            value="{{ old('check_in_date', date('Y-m-d')) }}"
                            min="{{ date('Y-m-d') }}" onchange="updatePrice()">
                        @error('check_in_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label">Check-out Date</label>
                        <input type="date" name="check_out_date" id="checkoutDate"
                            class="form-control {{ $errors->has('check_out_date') ? 'is-invalid' : '' }}"
                            value="{{ old('check_out_date') }}"
                            onchange="updatePrice()">
                        @error('check_out_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="two-col mb-14">
                    <div>
                        <label class="form-label">Number of Guests</label>
                        <input type="number" name="num_guests" id="numGuests" class="form-control"
                            value="{{ old('num_guests', 1) }}" min="1" max="30">
                    </div>
                    <div>
                        <label class="form-label">Special Requests <span class="optional-tag">(optional)</span></label>
                        <input type="text" name="special_requests" class="form-control"
                            value="{{ old('special_requests') }}" placeholder="Early check-in, extra bed, etc.">
                    </div>
                </div>

                {{-- Price Preview --}}
                <div class="price-preview" id="pricePreview" style="display:none;">
                    <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Price Breakdown</div>
                    <div id="nightBreakdown"></div>
                    <div class="price-row total">
                        <span>Total Amount</span>
                        <span id="totalAmount">₱0.00</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment --}}
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-cash-stack"></i></div>
                <h3>Payment <span class="optional-tag" style="font-size:13px;font-family:'DM Sans',sans-serif;">(optional — can be recorded later)</span></h3>
            </div>
            <div class="section-body">
                <div class="three-col mb-14">
                    <div>
                        <label class="form-label">Amount Received</label>
                        <input type="number" name="payment_amount" id="paymentAmount" class="form-control"
                            value="{{ old('payment_amount', 0) }}" min="0" step="0.01"
                            placeholder="0.00" oninput="updatePaymentPreview()">
                    </div>
                    <div>
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">-- Select --</option>
                            <option value="cash"          {{ old('payment_method')=='cash'          ? 'selected':'' }}>Cash</option>
                            <option value="gcash"         {{ old('payment_method')=='gcash'         ? 'selected':'' }}>GCash</option>
                            <option value="bank_transfer" {{ old('payment_method')=='bank_transfer' ? 'selected':'' }}>Bank Transfer</option>
                            <option value="credit_card"   {{ old('payment_method')=='credit_card'   ? 'selected':'' }}>Credit Card</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Payment Type</label>
                        <select name="payment_type" class="form-select">
                            <option value="deposit"      {{ old('payment_type')=='deposit'      ? 'selected':'' }}>Deposit</option>
                            <option value="full_payment" {{ old('payment_type')=='full_payment' ? 'selected':'' }}>Full Payment</option>
                            <option value="partial"      {{ old('payment_type')=='partial'      ? 'selected':'' }}>Partial</option>
                        </select>
                    </div>
                </div>

                {{-- Payment summary --}}
                <div id="paymentSummary" style="display:none;" class="price-preview">
                    <div class="price-row total"><span>Total</span><span id="psTotalAmount">₱0.00</span></div>
                    <div class="price-row paid"><span>Amount Paid</span><span id="psPaidAmount">₱0.00</span></div>
                    <div class="price-row balance"><span>Balance Due</span><span id="psBalanceAmount">₱0.00</span></div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:12px;align-items:center;">
            <a href="{{ route('staff.frontdesk') }}" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Frontdesk
            </a>
            <button type="submit" class="btn-submit">
                <i class="bi bi-calendar-check"></i> Create Walk-in Booking
            </button>
        </div>
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Guest type toggle ──────────────────────────────────────────
function setGuestType(type) {
    document.getElementById('guestType').value = type;
    document.getElementById('existingGuestFields').style.display = type === 'existing' ? 'block' : 'none';
    document.getElementById('newGuestFields').style.display      = type === 'new'      ? 'block' : 'none';
    document.getElementById('btnExisting').classList.toggle('active', type === 'existing');
    document.getElementById('btnNew').classList.toggle('active',      type === 'new');
}

// Restore on validation error
@if(old('guest_type') === 'new')
    setGuestType('new');
@endif

// ── Price calculation ──────────────────────────────────────────
let calculatedTotal = 0;

function updatePrice() {
    const propSelect = document.getElementById('propertySelect');
    const option     = propSelect.options[propSelect.selectedIndex];
    const ci         = document.getElementById('checkinDate').value;
    const co         = document.getElementById('checkoutDate').value;
    const preview    = document.getElementById('pricePreview');

    if (!option || !option.value || !ci || !co) { preview.style.display = 'none'; return; }

    const basePrice    = parseFloat(option.dataset.base);
    const weekendPrice = parseFloat(option.dataset.weekend);
    const d1 = new Date(ci), d2 = new Date(co);
    const nights = Math.round((d2 - d1) / 86400000);
    if (nights <= 0) { preview.style.display = 'none'; return; }

    let total = 0;
    let breakdownHtml = '';
    for (let i = 0; i < nights; i++) {
        const d    = new Date(d1); d.setDate(d.getDate() + i);
        const dow  = d.getDay();
        const isWE = dow === 0 || dow === 6;
        const price = isWE ? weekendPrice : basePrice;
        total += price;
        const dayName = d.toLocaleDateString('en-PH', { weekday:'short', month:'short', day:'numeric' });
        breakdownHtml += `<div class="price-row">
            <span style="color:var(--muted);">${dayName}${isWE ? ' <span style="color:#b8943f;">★</span>' : ''}</span>
            <span>₱${price.toLocaleString('en-PH', {minimumFractionDigits:2})}</span>
        </div>`;
    }

    calculatedTotal = total;
    document.getElementById('nightBreakdown').innerHTML = breakdownHtml;
    document.getElementById('totalAmount').textContent  = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
    preview.style.display = 'block';
    updatePaymentPreview();

    // Update max guests
    const maxGuests = parseInt(option.dataset.max);
    document.getElementById('numGuests').max = maxGuests;
}

function updatePaymentPreview() {
    const paid    = parseFloat(document.getElementById('paymentAmount').value) || 0;
    const summary = document.getElementById('paymentSummary');
    if (paid <= 0 || calculatedTotal <= 0) { summary.style.display = 'none'; return; }
    const balance = Math.max(0, calculatedTotal - paid);
    document.getElementById('psTotalAmount').textContent   = '₱' + calculatedTotal.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('psPaidAmount').textContent    = '₱' + paid.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('psBalanceAmount').textContent = '₱' + balance.toLocaleString('en-PH', {minimumFractionDigits:2});
    summary.style.display = 'block';
}

// Auto set checkout min
document.getElementById('checkinDate').addEventListener('change', function() {
    const co = document.getElementById('checkoutDate');
    const d  = new Date(this.value); d.setDate(d.getDate() + 1);
    co.min   = d.toISOString().split('T')[0];
    if (co.value && co.value <= this.value) co.value = d.toISOString().split('T')[0];
    updatePrice();
});

// Run on load
updatePrice();
</script>
</body>
</html>