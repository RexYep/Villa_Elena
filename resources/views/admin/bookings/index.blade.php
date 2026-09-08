@extends('layouts.admin')

@section('title', 'Bookings — Villa Elena Admin')
@section('page-title', 'Bookings')
@section('page-subtitle', 'Manage all reservations, check-ins, and check-outs')

@push('styles')
    <style>
        /* Stats — minmax(140px…) rather than 180px so a phone gets two columns
           instead of one. At 180px these five chips stacked single-file and stood
           431px tall on a 390px screen: half a phone screen of filter chrome
           before the first booking row. */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-chip {
            background: var(--cream);
            border-radius: 10px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: block;
        }

        .stat-chip:hover,
        .stat-chip.active {
            border-color: var(--terracotta);
            box-shadow: 0 2px 8px rgba(44, 36, 22, 0.08);
        }

        .stat-chip.active {
            background: var(--terracotta);
            color: #fff;
        }

        .stat-chip .val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        .stat-chip.active .val {
            color: #fff !important;
        }

        .stat-chip .lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }

        .stat-chip.active .lbl {
            color: rgba(255, 255, 255, 0.7);
        }

        /* Filters */
        .filters-bar {
            background: var(--cream);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-search {
            width: 200px;
        }

        .filter-input {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            background: #fff;
            color: var(--text-main);
            transition: border-color .2s;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--terracotta);
        }

        .btn-filter {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background .2s;
        }

        .btn-filter:hover {
            background: var(--gold);
        }

        .btn-clear {
            background: var(--sand);
            color: var(--muted);
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
        }

        .btn-add {
            display: flex;
            align-items: center;
            gap: 7px;
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-add:hover {
            background: var(--gold);
            color: #fff;
        }

        /* Badges — semantic status colors, unchanged */
        .s-pending {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .s-confirmed {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .s-checked_in {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
        }

        .s-checked_out {
            background: var(--tag-slate-bg);
            color: var(--tag-slate-fg);
        }

        .s-cancelled {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .s-no_show {
            background: var(--stone);
            color: #c9b8a3;
        }

        .p-unpaid {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .p-partial {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .p-paid {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .p-refunded {
            background: var(--tag-cyan-bg);
            color: var(--tag-cyan-fg);
        }

        /* Table cells.

           `table { width: 100% }` in admin.css means that once the card is
           narrower than the table's content — every screen under ~1100px, since
           this table has eight columns — the browser falls back to min-content
           widths and wraps every cell it can. A booking ref came out as three
           stacked lines ("VE-" / "20260907-" / "0012"), the guest name as four,
           the phone number as two, and rows grew past 100px tall. None of these
           three values is ever worth breaking; the table scrolls instead (see the
           min-width in the 900px block below). */
        .ref-link {
            font-weight: 600;
            color: var(--stone);
            text-decoration: none;
            font-size: 13px;
            white-space: nowrap;
        }

        .ref-source {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 1px;
        }

        .guest-name {
            white-space: nowrap;
        }

        .guest-phone {
            font-size: 13px;
            color: #94a3b8;
            white-space: nowrap;
        }


        .pagination .page-link[aria-label],
        .pagination .page-item:first-child .page-link,
        .pagination .page-item:last-child .page-link {
            width: 28px !important;
            height: 28px !important;
            min-width: 28px !important;
            max-width: 28px !important;
            padding: 0 !important;
            font-size: 15px !important;
            line-height: 1 !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        /* Laravel renders up to ~11 page links; on a phone that is wider than the
           card, and .pagination is a nowrap flex row by default. */
        .pagination-wrap .pagination {
            flex-wrap: wrap;
            row-gap: 6px;
        }

        /* ── RESPONSIVE ─────────────────────────────────────────────── */

        /* This table wants ~1160px for its eight columns — more than the content
           area offers on anything short of a very wide monitor. admin.css makes
           .table-card a scroller only below 900px, so between 900px and ~1500px
           the base `overflow: hidden` simply cut the Actions column off with no
           scrollbar to reveal it. The nowrap rules above make that width honest
           rather than hiding it behind shredded cells, so the scroller has to
           exist at every width, not just on mobile. */
        /* Prefixed with .main-content purely for specificity: admin.css sets
           `overflow: hidden` on a bare .table-card, and under `composer dev`
           Vite injects admin.css at runtime — after this inline block — so an
           equal-specificity override would silently lose in dev and work in
           production. Every rule here that contends with admin.css is written
           this way rather than relying on source order. */
        .main-content .table-card {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Both sit inside the scroller, so without this they slide out of view
           with the table when it is dragged sideways. admin.css already does
           this below 900px; the rule has to hold at every width now. */
        .table-card>.table-header,
        .table-card>.pagination-wrap {
            position: sticky;
            left: 0;
        }

        /* admin.css pairs its ≤900px scroller with `table { min-width: 640px }`.
           640px is a sensible floor for the 4- and 5-column tables elsewhere in
           the admin panel, but at eight columns it is below this table's own
           content width and does nothing. */
        @media (max-width: 900px) {
            .main-content .table-card table {
                min-width: 880px;
            }
        }

        @media (max-width: 768px) {

            /* .table-header goes column at 600px (admin.css); .btn-add is a flex
               container, so without this it stretches to the full card width. */
            .table-header .btn-add {
                align-self: flex-start;
            }
        }

        /* Phones only. Above this the flex row still packs three controls per
           line and is genuinely shorter — forcing the grid at 768px made the bar
           GROW from 119px to 206px, which is the opposite of the point. */
        @media (max-width: 560px) {

            /* The bar is a flex row of seven controls at seven different widths
               (the search box carried an inline width:200px), so on a phone it
               wrapped into ragged rows with holes in them. A grid pairs them
               evenly: search across the top, then the selects, dates and buttons
               two-up. */
            .filters-bar {
                display: grid;
                grid-template-columns: 1fr 1fr;
                align-items: stretch;
                padding: 14px;
                gap: 8px;
            }

            .filter-search {
                width: auto;
                grid-column: 1 / -1;
            }

            .filters-bar .btn-filter,
            .filters-bar .btn-clear {
                text-align: center;
                padding: 10px 14px;
            }

            .stats-row {
                gap: 8px;
                margin-bottom: 16px;
            }

            .stat-chip {
                padding: 10px 12px;
            }

            .stat-chip .val {
                font-size: 20px;
            }

            .stat-chip .lbl {
                font-size: 12px;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    {{-- Stats Chips --}}
    <div class="stats-row">
        <a href="{{ route('admin.bookings.index') }}" class="stat-chip {{ !request('status') ? 'active' : '' }}">
            <div class="val">{{ $stats['total'] }}</div>
            <div class="lbl">All Bookings</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}"
            class="stat-chip {{ request('status') == 'pending' ? 'active' : '' }}">
            <div class="val" style="color:#a16207;">{{ $stats['pending'] }}</div>
            <div class="lbl">Pending</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status' => 'confirmed']) }}"
            class="stat-chip {{ request('status') == 'confirmed' ? 'active' : '' }}">
            <div class="val" style="color:#15803d;">{{ $stats['confirmed'] }}</div>
            <div class="lbl">Confirmed</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status' => 'checked_in']) }}"
            class="stat-chip {{ request('status') == 'checked_in' ? 'active' : '' }}">
            <div class="val" style="color:#1d4ed8;">{{ $stats['checked_in'] }}</div>
            <div class="lbl">Checked In</div>
        </a>
        <a href="{{ route('admin.bookings.index', ['status' => 'cancelled']) }}"
            class="stat-chip {{ request('status') == 'cancelled' ? 'active' : '' }}">
            <div class="val" style="color:#dc2626;">{{ $stats['cancelled'] }}</div>
            <div class="lbl">Cancelled</div>
        </a>

    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.bookings.index') }}">
        <div class="filters-bar">
            <input type="text" name="search" class="filter-input filter-search"
                placeholder="Search ref / guest name..." value="{{ request('search') }}">
            <select name="status" class="filter-input">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="checked_in" {{ request('status') == 'checked_in' ? 'selected' : '' }}>Checked In</option>
                <option value="checked_out" {{ request('status') == 'checked_out' ? 'selected' : '' }}>Checked Out</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            <select name="payment_status" class="filter-input">
                <option value="">All Payments</option>
                <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
            </select>
            <input type="date" name="date_from" class="filter-input" value="{{ request('date_from') }}"
                title="Check-in from">
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

        @if ($bookings->isEmpty())
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
                    @foreach ($bookings as $booking)
                        <tr>
                            <td>
                                <a href="{{ route('admin.bookings.show', $booking) }}" class="ref-link">
                                    {{ $booking->booking_ref }}
                                </a>
                                <div class="ref-source">{{ $booking->source }}</div>
                            </td>
                            <td>
                                <div class="fw-medium guest-name">{{ $booking->user->full_name ?? 'N/A' }}</div>
                                <div class="guest-phone">{{ $booking->user->phone ?? '' }}</div>
                            </td>
                            <td style="white-space:nowrap;">{{ $booking->check_in_date->format('M d, Y') }}@if ($booking->check_in_time)
                                    <br><small
                                        class="text-muted-theme">{{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}</small>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">{{ $booking->check_out_date->format('M d, Y') }}@if ($booking->check_out_time)
                                    <br><small
                                        class="text-muted-theme">{{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}</small>
                                @endif
                            </td>
                            <td style="font-weight:600;">₱{{ number_format($booking->total_amount, 2) }}</td>
                            <td><span
                                    class="status-badge s-{{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                            </td>
                            <td><span
                                    class="status-badge p-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="{{ route('admin.bookings.show', $booking) }}" class="btn-icon"
                                        title="View"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('admin.bookings.edit', $booking) }}" class="btn-icon"
                                        title="Edit"><i class="bi bi-pencil"></i></a>
                                    <button onclick="confirmDelete({{ $booking->id }}, '{{ $booking->booking_ref }}')"
                                        class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($bookings->hasPages())
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
                    <div
                        style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px;color:#ef4444;">
                        <i class="bi bi-trash"></i>
                    </div>
                    <h5 style="font-family:'Cormorant Garamond',serif;font-size:19px;margin-bottom:8px;">Delete Booking?
                    </h5>
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

