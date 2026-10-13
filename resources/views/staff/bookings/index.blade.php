@extends('layouts.staff')

@section('title', 'Bookings — Villa Elena Staff')
@section('page-title', 'Bookings')
@section('page-subtitle', 'Find a booking by guest, reference, phone or email')

@push('styles')
    <style>
        .main {
            max-width: 1100px;
        }

        .lookup-search {
            margin-bottom: 20px;
        }

        .lookup-search-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .lookup-search-row .form-control {
            flex: 1 1 260px;
            width: auto;
        }

        .btn-lookup {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: var(--btn-primary);
            color: #fff;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-lookup:hover {
            background: var(--btn-primary-hover);
        }

        .btn-lookup-clear {
            display: inline-flex;
            align-items: center;
            padding: 10px 16px;
            border: 1.5px solid var(--border-strong);
            border-radius: 8px;
            background: #fff;
            color: var(--stone);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-lookup-clear:hover {
            border-color: var(--stone);
        }

        /* One booking per row, the whole row a link. Columns: guest, stay,
           status, money, chevron. */
        .lookup-row {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1.4fr) 116px minmax(0, 1fr) 16px;
            align-items: center;
            gap: 16px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--sand);
            color: var(--text-main);
            text-decoration: none;
            transition: background .15s;
        }

        .lookup-row:last-child {
            border-bottom: none;
        }

        .lookup-row:hover {
            background: var(--cream);
        }

        .lookup-cell {
            display: flex;
            flex-direction: column;
            min-width: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .lookup-cell strong {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            overflow-wrap: anywhere;
        }

        .lookup-money {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
        }

        .lookup-money.is-due {
            color: var(--tag-red-fg);
        }

        .lookup-row .bi-chevron-right {
            color: var(--muted);
            font-size: 13px;
        }

        .lookup-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
        }

        .lookup-more {
            padding: 12px 20px;
            border-top: 1px solid var(--sand);
            font-size: 13px;
            color: var(--muted);
        }

        /* Narrow: guest and status on the first line, stay and money on
           the second. */
        @media (max-width: 720px) {
            .lookup-row {
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 8px 12px;
            }

            .lookup-row .bi-chevron-right {
                display: none;
            }

            .lookup-status {
                grid-column: 2;
                grid-row: 1;
            }

            .lookup-stay {
                grid-column: 1;
                grid-row: 2;
            }

            .lookup-money {
                grid-column: 2;
                grid-row: 2;
            }

            .lookup-status,
            .lookup-money {
                justify-self: end;
                text-align: right;
            }
        }
    </style>
@endpush

@section('content')

    <form method="GET" action="{{ route('staff.bookings.index') }}" class="lookup-search" role="search">
        <label for="bookingSearch" class="field-label">Find a booking</label>
        <div class="lookup-search-row">
            <input type="search" id="bookingSearch" name="q" value="{{ $search }}" class="form-control"
                placeholder="Guest name, booking reference, phone or email" autocomplete="off"
                @if ($tooShort) aria-invalid="true" aria-describedby="bookingSearchError" @endif>
            <button type="submit" class="btn-lookup"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
            @if ($search !== '')
                <a href="{{ route('staff.bookings.index') }}" class="btn-lookup-clear">Clear</a>
            @endif
        </div>
        @if ($tooShort)
            <p class="field-error" id="bookingSearchError">Type at least {{ $minSearch }} characters to search.</p>
        @endif
    </form>

    <div class="panel">
        <div class="panel-head">
            <h2>
                @if ($searching)
                    Results for “{{ $search }}”
                @else
                    Upcoming and in the villa
                @endif
            </h2>
            <span class="note-text">
                {{ $bookings->count() }}{{ $hasMore ? '+' : '' }} {{ $bookings->count() === 1 && ! $hasMore ? 'booking' : 'bookings' }}
            </span>
        </div>

        @forelse ($bookings as $booking)
            {{-- By slot and stored times, never by nights (see CLAUDE.md). --}}
            @php
                $settled = in_array($booking->status, ['cancelled', 'no_show'], true) || (float) $booking->balance_due <= 0;
            @endphp
            <a class="lookup-row" href="{{ route('staff.bookings.show', $booking) }}">
                <span class="lookup-cell">
                    <strong>{{ $booking->user->full_name ?? 'Guest' }}</strong>
                    <span>{{ $booking->booking_ref }}</span>
                </span>
                <span class="lookup-cell lookup-stay">
                    <strong>{{ $booking->check_in_date->format('D, M j, Y') }}</strong>
                    <span>{{ $booking->slot_name ?? 'Stay' }}, {{ $booking->stay_hours }}</span>
                </span>
                <span class="lookup-status">
                    <span class="status-pill is-{{ $booking->status }}">{{ $booking->statusDisplay()['text'] }}</span>
                </span>
                <span class="lookup-money {{ $settled ? '' : 'is-due' }}">
                    @if ($settled)
                        {{ $booking->paymentProgressDisplay()['text'] }}
                    @else
                        ₱{{ number_format($booking->balance_due, 2) }} due
                    @endif
                </span>
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
        @empty
            <div class="lookup-empty">
                @if ($searching)
                    No booking matches “{{ $search }}”. Check the spelling, or search by the booking reference
                    (it starts with VE-).
                @else
                    No upcoming bookings. Search above to find a past one.
                @endif
            </div>
        @endforelse

        @if ($hasMore)
            <div class="lookup-more">
                Showing the first {{ $limit }}.
                {{ $searching ? 'Add more of the name or the full reference to narrow it down.' : 'Search above to find a later one.' }}
            </div>
        @endif
    </div>

@endsection
