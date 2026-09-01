@extends('layouts.customer')

@section('title', 'My Bookings — Villa Elena Resort')

@push('styles')
    <style>
        /* ================= FILTER ================= */
        .filter-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            border: 1.5px solid var(--border);
            color: var(--muted);
            background: #fff;
            cursor: pointer;
            transition: .2s;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--stone);
            color: #fff;
            border-color: var(--stone);
        }

        /* ================= BOOKING CARD ================= */
        .booking-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 14px;
            transition: .2s;
        }

        .booking-card:hover {
            box-shadow: 0 4px 20px rgba(44, 36, 22, .08);
        }

        .booking-card-inner {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
        }

        .bc-img,
        .bc-img-placeholder {
            width: 68px;
            height: 68px;
            border-radius: 12px;
            flex-shrink: 0;
        }

        .bc-img {
            object-fit: cover;
            background: var(--sand);
        }

        .bc-img-placeholder {
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--muted);
        }

        .bc-info {
            flex: 1;
            min-width: 0;
        }

        .bc-ref {
            font-weight: 600;
            font-size: 14px;
            color: var(--stone);
            text-decoration: none;
        }

        .bc-ref:hover {
            color: var(--gold);
        }

        .bc-property {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
        }

        .bc-dates {
            font-size: 14px;
            color: var(--muted);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .bc-right {
            text-align: right;
            flex-shrink: 0;
        }

        .bc-amount {
            margin-top: 7px;
            font-weight: 700;
            font-size: 15px;
            font-family: 'Playfair Display', serif;
        }

        .bc-balance {
            font-size: 13px;
            color: var(--terracotta);
            margin-top: 2px;
        }

        /* ================= EMPTY ================= */
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 44px;
            display: block;
            margin-bottom: 12px;
            opacity: .35;
        }

        /* ================= MOBILE ================= */
        @media (max-width:768px) {
            .booking-card-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .bc-right {
                text-align: left;
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="page-title">My Bookings</div>
    <div class="page-sub">All your reservations at Villa Elena Resort</div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('customer.bookings') }}">
        <div class="filter-bar">
            <button type="submit" name="status" value=""
                class="filter-btn {{ !request('status') ? 'active' : '' }}">All</button>
            <button type="submit" name="status" value="pending"
                class="filter-btn {{ request('status') == 'pending' ? 'active' : '' }}">Pending</button>
            <button type="submit" name="status" value="confirmed"
                class="filter-btn {{ request('status') == 'confirmed' ? 'active' : '' }}">Confirmed</button>
            <button type="submit" name="status" value="checked_in"
                class="filter-btn {{ request('status') == 'checked_in' ? 'active' : '' }}">Checked In</button>
            <button type="submit" name="status" value="checked_out"
                class="filter-btn {{ request('status') == 'checked_out' ? 'active' : '' }}">Completed</button>
            <button type="submit" name="status" value="cancelled"
                class="filter-btn {{ request('status') == 'cancelled' ? 'active' : '' }}">Cancelled</button>
        </div>
    </form>

    @forelse($bookings as $booking)
        <div class="booking-card">
            <div class="booking-card-inner">
                @if ($booking->property?->primaryImage)
                    <img src="{{ $booking->property->primaryImage->url }}" class="bc-img" alt="">
                @else
                    <div class="bc-img-placeholder"><i class="bi bi-house"></i></div>
                @endif
                <div class="bc-info">
                    <a href="{{ route('customer.bookings.show', $booking) }}"
                        class="bc-ref">{{ $booking->booking_ref }}</a>
                    <div class="bc-property">{{ $booking->property->property_name ?? 'N/A' }}</div>
                    <div class="bc-dates">
                        <i class="bi bi-calendar3" style="font-size: 13px;"></i>
                        {{ $booking->check_in_date->format('M d, Y') }} &rarr;
                        {{ $booking->check_out_date->format('M d, Y') }}
                        &nbsp;·&nbsp; {{ $booking->num_nights }} night{{ $booking->num_nights != 1 ? 's' : '' }}
                        &nbsp;·&nbsp; {{ $booking->num_guests }} guest{{ $booking->num_guests != 1 ? 's' : '' }}
                    </div>
                </div>
                <div class="bc-right">
                    <span
                        class="badge b-{{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                    <div class="bc-amount" style="margin-top:7px;">₱{{ number_format($booking->total_amount, 2) }}</div>
                    @if ($booking->balance_due > 0 && $booking->status !== 'cancelled')
                        <div class="bc-balance">₱{{ number_format($booking->balance_due, 2) }} balance due</div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p style="font-size:15px;margin-bottom:6px;">No bookings found</p>
            <a href="{{ route('home') }}" style="color:var(--gold);font-size:13px;">Browse our properties →</a>
        </div>
    @endforelse

    @if ($bookings->hasPages())
        <div style="margin-top:20px;">{{ $bookings->links() }}</div>
    @endif
@endsection

