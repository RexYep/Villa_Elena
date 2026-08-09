@extends('layouts.admin')

@section('title', 'Payments — Villa Elena Admin')
@section('page-title', 'Payments')
@section('page-subtitle', 'All transactions and payment records')

@section('topbar-right')
    <button class="btn-navy" onclick="openRecordModal()">
        <i class="bi bi-plus-lg"></i> Record Payment
    </button>
    <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf
        <button type="submit" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i> Logout
        </button>
    </form>
@endsection

@push('styles')
<style>
/* ── STAT CARDS ── */
.stats-row{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:16px;
    margin-bottom:24px;
}
.stat-card{
    background:var(--cream);
    border-radius:14px;
    border:1px solid var(--border);
    padding:20px 22px;
    transition:transform .2s, box-shadow .2s;
}
.stat-card:hover{ transform:translateY(-2px); box-shadow:0 8px 24px rgba(44,36,22,.09); }
.stat-icon{
    width:38px; height:38px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:17px; margin-bottom:12px;
    background:var(--gold-dim); color:var(--gold);
}
.stat-val{
    font-family:'Cormorant Garamond',serif;
    font-size:24px; font-weight:700;
    color:var(--stone); line-height:1;
}
.stat-lbl{ font-size:11px; color:var(--muted); margin-top:4px; }

/* ── FILTER CARD ── */
.filter-card{
    background:var(--cream);
    border-radius:14px;
    border:1px solid var(--border);
    padding:18px 22px;
    margin-bottom:20px;
}
.form-label-sm{
    font-size:11px; font-weight:600; color:var(--text-main);
    display:block; margin-bottom:5px;
    text-transform:uppercase; letter-spacing:.3px;
}

/* ── TABLE CARD ── */
.table-card-header{
    padding:16px 22px;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; justify-content:space-between;
}
.table-card-header h3{
    font-family:'Cormorant Garamond',serif;
    font-size:17px; font-weight:600; color:var(--stone);
}
thead tr{ background:var(--sand); }
thead th{
    padding:11px 16px;
    font-size:11px; font-weight:700;
    color:var(--muted);
    text-transform:uppercase; letter-spacing:.5px;
    border-bottom:1px solid var(--border);
    white-space:nowrap;
}
tbody tr{ border-bottom:1px solid var(--border); transition:background .15s; }
tbody tr:last-child{ border-bottom:none; }
tbody tr:hover{ background:var(--sand); }
tbody td{ padding:13px 16px; font-size:13px; vertical-align:middle; color:var(--text-main); }

/* ── BADGES — semantic, unchanged ── */
.badge{
    padding:3px 10px; border-radius:20px;
    font-size:10px; font-weight:700;
    text-transform:uppercase; letter-spacing:.3px;
    white-space:nowrap;
}
.b-cash        { background:var(--tag-green-bg); color:var(--tag-green-fg); }
.b-gcash       { background:var(--tag-blue-bg); color:var(--tag-blue-fg); }
.b-bank_transfer { background:var(--tag-purple-bg); color:var(--tag-purple-fg); }
.b-credit_card { background:var(--tag-amber-bg); color:var(--tag-amber-fg); }
.b-online      { background:var(--tag-cyan-bg); color:var(--tag-cyan-fg); }
.b-paymaya     { background:var(--tag-green-bg); color:var(--tag-green-fg); }
.b-grab_pay    { background:var(--tag-green-bg); color:var(--tag-green-fg); }
.b-deposit     { background:var(--tag-emerald-bg); color:var(--tag-emerald-fg); }
.b-full_payment { background:var(--tag-green-bg); color:var(--tag-green-fg); }
.b-partial     { background:var(--tag-amber-bg); color:var(--tag-amber-fg); }
.b-balance     { background:var(--tag-blue-bg); color:var(--tag-blue-fg); }
.b-refund      { background:var(--tag-red-bg); color:var(--tag-red-fg); }

/* ── BUTTONS ── */
.btn-navy{
    background:var(--terracotta); color:#fff;
    border:none; border-radius:9px;
    padding:9px 18px; font-size:13px; font-weight:600;
    cursor:pointer; font-family:'DM Sans',sans-serif;
    display:inline-flex; align-items:center; gap:6px;
    transition:all .2s; text-decoration:none;
}
.btn-navy:hover{ background:var(--gold); color:#fff; }
.view-link{ color:var(--gold); text-decoration:none; font-weight:600; font-size:12px; }
.view-link:hover{ color:var(--terracotta); }
.refund-link{ color:#dc2626; text-decoration:none; font-weight:600; font-size:12px; }

/* ── MODAL ── */
.modal-box{ width:480px; }
.modal-head{ background:var(--terracotta); }
.two-col{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.mb-12{ margin-bottom:12px; }

.payments-filter-grid{ display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr auto auto; gap:12px; align-items:end; }

@media (max-width: 1100px) {
    .stats-row{ grid-template-columns:repeat(3,1fr); }
    .payments-filter-grid{ grid-template-columns:1fr 1fr 1fr; }
}
@media (max-width: 700px) {
    .stats-row{ grid-template-columns:1fr 1fr; }
    .payments-filter-grid{ grid-template-columns:1fr 1fr; }
}
@media (max-width: 480px) {
    .stats-row{ grid-template-columns:1fr; }
    .payments-filter-grid{ grid-template-columns:1fr; }
}
</style>
@endpush

@section('content')

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Stat Cards --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-val">₱{{ number_format($totalRevenue, 2) }}</div>
            <div class="stat-lbl">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="bi bi-calendar-day"></i></div>
            <div class="stat-val">₱{{ number_format($todayRevenue, 2) }}</div>
            <div class="stat-lbl">Today's Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-val">₱{{ number_format($monthRevenue, 2) }}</div>
            <div class="stat-lbl">This Month</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7; color:#d97706;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">₱{{ number_format($pendingBalance, 2) }}</div>
            <div class="stat-lbl">Pending Balance</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2; color:#dc2626;"><i class="bi bi-arrow-counterclockwise"></i></div>
            <div class="stat-val">₱{{ number_format($totalRefunds, 2) }}</div>
            <div class="stat-lbl">Total Refunds</div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.payments.index') }}">
            <div class="payments-filter-grid">
                <div>
                    <label class="form-label-sm">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Booking ref or guest name"
                        value="{{ request('search') }}">
                </div>
                <div>
                    <label class="form-label-sm">Method</label>
                    <select name="method" class="form-select form-select-sm">
                        <option value="">All Methods</option>
                        <option value="cash"          {{ request('method')=='cash' ? 'selected' : '' }}>Cash</option>
                        <option value="gcash"         {{ request('method')=='gcash' ? 'selected' : '' }}>GCash</option>
                        <option value="bank_transfer" {{ request('method')=='bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="credit_card"   {{ request('method')=='credit_card' ? 'selected' : '' }}>Credit Card</option>
                        <option value="online"        {{ request('method')=='online' ? 'selected' : '' }}>Online</option>
                        <option value="paymaya"       {{ request('method')=='paymaya' ? 'selected' : '' }}>PayMaya</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="deposit"      {{ request('type')=='deposit' ? 'selected' : '' }}>Deposit</option>
                        <option value="full_payment" {{ request('type')=='full_payment' ? 'selected' : '' }}>Full Payment</option>
                        <option value="partial"      {{ request('type')=='partial' ? 'selected' : '' }}>Partial</option>
                        <option value="balance"      {{ request('type')=='balance' ? 'selected' : '' }}>Balance</option>
                        <option value="refund"       {{ request('type')=='refund' ? 'selected' : '' }}>Refund</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                </div>
                <div>
                    <label class="form-label-sm">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div style="padding-top:18px;">
                    <button type="submit" class="btn-navy" style="padding:8px 16px; font-size:12px;">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div style="padding-top:18px;">
                    <a href="{{ route('admin.payments.index') }}"
                       class="text-muted-theme"
                       style="background:#fff; border:1.5px solid var(--border);
                              border-radius:7px; padding:8px 14px; font-size:12px; text-decoration:none;
                              display:inline-block;">
                        Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Payment Records Table --}}
    <div class="table-card">
        <div class="table-card-header">
            <h3>Payment Records</h3>
            <span class="text-muted-theme" style="font-size:12px;">{{ $payments->total() }} records</span>
        </div>

        @if($payments->isEmpty())
            <div class="empty-state">
                <i class="bi bi-credit-card-2-back"></i>
                No payment records found.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Booking Ref</th>
                        <th>Guest</th>
                        <th>Property</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                    <tr>
                        <td class="text-muted-theme" style="font-size:12px;">
                            {{ $payment->created_at->format('M d, Y') }}
                        </td>
                        <td>
                            <strong style="font-size:13px;">{{ $payment->booking->booking_ref ?? 'N/A' }}</strong>
                        </td>
                        <td>{{ $payment->booking->user->full_name ?? 'N/A' }}</td>
                        <td>{{ $payment->booking->property->property_name ?? 'N/A' }}</td>
                        <td>
                            <span class="badge b-{{ $payment->payment_method }}">
                                {{ strtoupper($payment->payment_method) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge b-{{ $payment->payment_type }}">
                                {{ strtoupper(str_replace('_', ' ', $payment->payment_type)) }}
                            </span>
                        </td>
                        <td>
                            <strong>₱{{ number_format($payment->amount, 2) }}</strong>
                        </td>
                        <td class="text-muted-theme" style="font-size:12px; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ $payment->notes ?? '—' }}
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="{{ route('admin.bookings.show', $payment->booking_id) }}" class="view-link me-2">View</a>
                            @if($payment->payment_type !== 'refund')
                                <a href="#" class="refund-link"
                                   onclick="openRefundModal({{ $payment->id }}, {{ $payment->amount }}, '{{ $payment->booking->booking_ref ?? '' }}')">
                                   Refund
                                </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($payments->hasPages())
                <div style="padding:16px 22px;">
                    {{ $payments->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection

@section('modals')
{{-- Record Payment Modal --}}
<div class="modal-overlay" id="recordModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">Record Payment</div>
            <button class="modal-close" onclick="closeModal('recordModal')">✕</button>
        </div>
        <form method="POST" action="{{ route('admin.payments.store') }}" class="modal-body">
            @csrf
            <div class="mb-12">
                <label class="form-label">Booking Reference</label>
                <input type="text" name="booking_ref" class="form-control"
                    placeholder="e.g. VE-XXXXXXXX" autocomplete="off"
                    oninput="lookupBooking(this.value)">
                <input type="hidden" name="booking_id" id="bookingIdInput">
                <div id="bookingInfo" class="text-muted-theme" style="margin-top:8px; font-size:12px; display:none; background:#f8fafc; border-radius:8px; padding:10px 12px;"></div>
            </div>
            <div class="two-col mb-12">
                <div>
                    <label class="form-label">Amount (₱)</label>
                    <input type="number" name="amount" class="form-control" min="1" step="0.01" required placeholder="0.00">
                </div>
                <div>
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>
            <div class="two-col mb-12">
                <div>
                    <label class="form-label">Method</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="online">Online</option>
                        <option value="paymaya">PayMaya</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Type</label>
                    <select name="payment_type" class="form-select" required>
                        <option value="deposit">Deposit</option>
                        <option value="full_payment">Full Payment</option>
                        <option value="partial">Partial</option>
                        <option value="balance">Balance</option>
                    </select>
                </div>
            </div>
            <div class="mb-12">
                <label class="form-label">Notes <span class="text-muted-theme" style="font-weight:400;">(optional)</span></label>
                <input type="text" name="notes" class="form-control" placeholder="e.g. Cash received at frontdesk">
            </div>
            <button type="submit" class="btn-submit"><i class="bi bi-check-circle me-2"></i> Record Payment</button>
        </form>
    </div>
</div>

{{-- Refund Modal --}}
<div class="modal-overlay" id="refundModal">
    <div class="modal-box">
        <div class="modal-head" style="background:#dc2626;">
            <div class="modal-title">Issue Refund</div>
            <button class="modal-close" onclick="closeModal('refundModal')">✕</button>
        </div>
        <form method="POST" id="refundForm" class="modal-body">
            @csrf
            <div style="background:#fee2e2; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#dc2626;">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Refund for booking <strong id="refundBookingRef"></strong>
            </div>
            <div class="mb-12">
                <label class="form-label">Refund Amount (₱)</label>
                <input type="number" name="refund_amount" id="refundAmountInput" class="form-control" min="1" step="0.01" required>
                <div class="text-muted-theme" style="font-size:11px; margin-top:4px;">Max: ₱<span id="refundMax"></span></div>
            </div>
            <div class="mb-12">
                <label class="form-label">Reason for Refund</label>
                <textarea name="refund_reason" class="form-control" rows="2" required minlength="5"
                    placeholder="e.g. Guest cancelled 48 hours before check-in"></textarea>
            </div>
            <button type="submit" style="background:#dc2626; color:#fff; border:none; border-radius:9px; padding:12px; width:100%; font-size:14px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif;">
                <i class="bi bi-arrow-counterclockwise me-2"></i> Process Refund
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openRecordModal() {
    document.getElementById('recordModal').style.display = 'flex';
}
function openRefundModal(paymentId, amount, bookingRef) {
    document.getElementById('refundBookingRef').textContent = bookingRef;
    document.getElementById('refundMax').textContent        = parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('refundAmountInput').max        = amount;
    document.getElementById('refundAmountInput').value      = amount;
    // Gumagamit ng route() helper (naka-embed via Blade) sa halip na
    // hardcoded na "/villa-elena/public/..." path — para gumana ito
    // kahit paano ma-access ang app (php artisan serve, XAMPP subfolder,
    // custom domain, atbp.), dahil laging tama ang APP_URL-aware na URL
    // na ginagawa ng Laravel mismo.
    document.getElementById('refundForm').action            =
        "{{ route('admin.payments.refund', ['payment' => '__PAYMENT_ID__']) }}".replace('__PAYMENT_ID__', paymentId);
    document.getElementById('refundModal').style.display    = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
['recordModal','refundModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});

let lookupTimer;
const bookingLookupUrlTemplate = '{{ route('admin.bookings.lookup', ['ref' => '__REF__']) }}';
function lookupBooking(ref) {
    clearTimeout(lookupTimer);
    if (ref.length < 6) { document.getElementById('bookingInfo').style.display = 'none'; return; }
    lookupTimer = setTimeout(() => {
        fetch(bookingLookupUrlTemplate.replace('__REF__', encodeURIComponent(ref)), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.id) {
                document.getElementById('bookingIdInput').value = data.id;
                const info = document.getElementById('bookingInfo');
                info.style.display = 'block';
                info.innerHTML = `<strong>${data.guest}</strong> · ${data.property} · Balance: <strong style="color:#dc2626;">₱${parseFloat(data.balance).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>`;
            }
        }).catch(() => {});
    }, 400);
}

setTimeout(() => { document.querySelectorAll('.alert').forEach(a => a.style.display='none'); }, 5000);
</script>
@endpush
