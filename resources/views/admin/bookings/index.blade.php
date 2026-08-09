@extends('layouts.admin')

@section('title', 'Bookings — Villa Elena Admin')
@section('page-title', 'Bookings')
@section('page-subtitle', 'Manage all reservations, check-ins, and check-outs')

@push('styles')
<style>
/* Stats */
.stats-row{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:12px;
    margin-bottom:24px;
}
.stat-chip{
    background:var(--cream);
    border-radius:10px;
    padding:14px 16px;
    border:1px solid var(--border);
    cursor:pointer;
    transition:all .2s;
    text-decoration:none;
    display:block;
}
.stat-chip:hover,
.stat-chip.active{
    border-color:var(--terracotta);
    box-shadow:0 2px 8px rgba(44,36,22,0.08);
}
.stat-chip.active{
    background:var(--terracotta);
    color:#fff;
}
.stat-chip .val{
    font-family:'Cormorant Garamond',serif;
    font-size:24px;
    font-weight:700;
    color:var(--text-main);
    line-height:1;
}
.stat-chip.active .val{
    color:#fff !important;
}
.stat-chip .lbl{
    font-size:11px;
    color:var(--muted);
    margin-top:3px;
}
.stat-chip.active .lbl{
    color:rgba(255,255,255,0.7);
}
/* Filters */
.filters-bar{background:var(--cream);border-radius:12px;border:1px solid var(--border);padding:16px 20px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.filter-input{border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;color:var(--text-main);transition:border-color .2s;}
.filter-input:focus{outline:none;border-color:var(--terracotta);}
.btn-filter{background:var(--terracotta);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s;}
.btn-filter:hover{background:var(--gold);}
.btn-clear{background:var(--sand);color:var(--muted);border:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;}

.btn-add{display:flex;align-items:center;gap:7px;background:var(--terracotta);color:#fff;border:none;border-radius:9px;padding:9px 18px;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:background .2s;}
.btn-add:hover{background:var(--gold);color:#fff;}

/* Badges — semantic status colors, unchanged */
.s-pending   {background:var(--tag-amber-bg);color:var(--tag-amber-fg);}
.s-confirmed {background:var(--tag-green-bg);color:var(--tag-green-fg);}
.s-checked_in{background:var(--tag-blue-bg);color:var(--tag-blue-fg);}
.s-checked_out{background:var(--tag-slate-bg);color:var(--tag-slate-fg);}
.s-cancelled {background:var(--tag-red-bg);color:var(--tag-red-fg);}
.s-no_show   {background:var(--stone);color:#c9b8a3;}
.p-unpaid    {background:var(--tag-red-bg);color:var(--tag-red-fg);}
.p-partial   {background:var(--tag-amber-bg);color:var(--tag-amber-fg);}
.p-paid      {background:var(--tag-green-bg);color:var(--tag-green-fg);}
.p-refunded  {background:var(--tag-cyan-bg);color:var(--tag-cyan-fg);}

/* Pagination prev/next (‹ ›) buttons — this app's published pagination
   view (resources/views/vendor/pagination/bootstrap-5.blade.php) renders
   these as plain text glyphs, not <svg>/<i> icons. The old rule forced a
   28x28 box with no centering, so the glyph sat top-left and effectively
   disappeared — still clickable, just not visible. */
.pagination .page-link[aria-label],
.pagination .page-item:first-child .page-link,
.pagination .page-item:last-child .page-link{
    width:28px !important;
    height:28px !important;
    min-width:28px !important;
    max-width:28px !important;
    padding:0 !important;
    font-size:15px !important;
    line-height:1 !important;
    display:flex !important;
    align-items:center;
    justify-content:center;
}
</style>
@endpush

@section('content')

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    {{-- Stats Chips --}}
    <div class="stats-row">
        <a href="{{ route('admin.bookings.index') }}" class="stat-chip {{ !request('status') ? 'active' : '' }}">
            <div class="val">{{ $stats['total'] }}</div>
            <div class="lbl">All Bookings</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status'=>'pending']) }}" class="stat-chip {{ request('status')=='pending' ? 'active' : '' }}">
            <div class="val" style="color:#a16207;">{{ $stats['pending'] }}</div>
            <div class="lbl">Pending</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status'=>'confirmed']) }}" class="stat-chip {{ request('status')=='confirmed' ? 'active' : '' }}">
            <div class="val" style="color:#15803d;">{{ $stats['confirmed'] }}</div>
            <div class="lbl">Confirmed</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status'=>'checked_in']) }}" class="stat-chip {{ request('status')=='checked_in' ? 'active' : '' }}">
            <div class="val" style="color:#1d4ed8;">{{ $stats['checked_in'] }}</div>
            <div class="lbl">Checked In</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status'=>'cancelled']) }}" class="stat-chip {{ request('status')=='cancelled' ? 'active' : '' }}">
            <div class="val" style="color:#dc2626;">{{ $stats['cancelled'] }}</div>
            <div class="lbl">Cancelled</div>
        </a>

    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.bookings.index') }}">
        <div class="filters-bar">
            <input type="text" name="search" class="filter-input" style="width:200px;"
                placeholder="Search ref / guest name..." value="{{ request('search') }}">
            <select name="status" class="filter-input">
                <option value="">All Status</option>
                <option value="pending"     {{ request('status')=='pending'     ?'selected':'' }}>Pending</option>
                <option value="confirmed"   {{ request('status')=='confirmed'   ?'selected':'' }}>Confirmed</option>
                <option value="checked_in"  {{ request('status')=='checked_in'  ?'selected':'' }}>Checked In</option>
                <option value="checked_out" {{ request('status')=='checked_out' ?'selected':'' }}>Checked Out</option>
                <option value="cancelled"   {{ request('status')=='cancelled'   ?'selected':'' }}>Cancelled</option>
            </select>
            <select name="payment_status" class="filter-input">
                <option value="">All Payments</option>
                <option value="unpaid"   {{ request('payment_status')=='unpaid'   ?'selected':'' }}>Unpaid</option>
                <option value="partial"  {{ request('payment_status')=='partial'  ?'selected':'' }}>Partial</option>
                <option value="paid"     {{ request('payment_status')=='paid'     ?'selected':'' }}>Paid</option>
            </select>
            <input type="date" name="date_from" class="filter-input" value="{{ request('date_from') }}" title="Check-in from">
            <input type="date" name="date_to" class="filter-input" value="{{ request('date_to') }}" title="Check-in to">
            <button type="submit" class="btn-filter"><i class="bi bi-search me-1"></i> Filter</button>
            <a href="{{ route('admin.bookings.index') }}" class="btn-clear">Clear</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="table-card">
        <div class="table-header">
            <h3>{{ $bookings->total() }} Booking{{ $bookings->total() != 1 ? 's' : '' }} Found</h3>
            <a href="{{ route('admin.bookings.create') }}" class="btn-add">
                <i class="bi bi-plus-lg"></i> New Booking
            </a>
        </div>

        @if($bookings->isEmpty())
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>No bookings found matching your filters.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Guest</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $booking)
                    <tr>
                        <td>
                            <a href="{{ route('admin.bookings.show', $booking) }}"
                               style="font-weight:600;color:var(--stone);text-decoration:none;font-size:13px;">
                                {{ $booking->booking_ref }}
                            </a>
                            <div style="font-size:10px;color:#94a3b8;margin-top:1px;">
                                {{ $booking->source }}
                            </div>
                        </td>
                        <td>
                            <div class="fw-medium">{{ $booking->user->full_name ?? 'N/A' }}</div>
                            <div style="font-size:11px;color:#94a3b8;">{{ $booking->user->phone ?? '' }}</div>
                        </td>
                        <td style="white-space:nowrap;">{{ $booking->check_in_date->format('M d, Y') }}@if($booking->check_in_time)<br><small class="text-muted-theme">{{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}</small>@endif</td>
                        <td style="white-space:nowrap;">{{ $booking->check_out_date->format('M d, Y') }}@if($booking->check_out_time)<br><small class="text-muted-theme">{{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}</small>@endif</td>
                        <td style="font-weight:600;">₱{{ number_format($booking->total_amount, 2) }}</td>
                        <td><span class="status-badge s-{{ $booking->status }}">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</span></td>
                        <td><span class="status-badge p-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span></td>
                        <td>
                            <div class="action-btns">
                                <a href="{{ route('admin.bookings.show', $booking) }}" class="btn-icon" title="View"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('admin.bookings.edit', $booking) }}" class="btn-icon" title="Edit"><i class="bi bi-pencil"></i></a>
                                <button onclick="confirmDelete({{ $booking->id }}, '{{ $booking->booking_ref }}')"
                                    class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($bookings->hasPages())
                <div class="pagination-wrap">
                    {{ $bookings->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection

@section('modals')
{{-- Delete Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-body text-center p-4">
                <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px;color:#ef4444;">
                    <i class="bi bi-trash"></i>
                </div>
                <h5 style="font-family:'Cormorant Garamond',serif;font-size:19px;margin-bottom:8px;">Delete Booking?</h5>
                <p style="font-size:13px;color:#64748b;margin-bottom:20px;" id="deleteMsg"></p>
                <form id="deleteForm" method="POST">
                    @csrf @method('DELETE')
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger w-50">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const deleteBookingUrlTemplate = '{{ route('admin.bookings.destroy', ['booking' => '__ID__']) }}';
function confirmDelete(id, ref) {
    document.getElementById('deleteMsg').textContent = `Booking "${ref}" will be permanently deleted.`;
    document.getElementById('deleteForm').action = deleteBookingUrlTemplate.replace('__ID__', id);
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endpush
