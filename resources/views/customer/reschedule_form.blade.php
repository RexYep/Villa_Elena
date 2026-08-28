@extends('layouts.customer')

@section('title', 'Reschedule Booking — Villa Elena')

@push('styles')
    <style>
        .main {
            max-width: 600px;
        }

        .page-title {
            font-weight: 700;
        }

        .booking-summary {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .summary-name {
            font-weight: 600;
            font-size: 15px;
        }

        .summary-dates {
            font-size: 12px;
            color: var(--muted);
            margin-top: 3px;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .form-card-head {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
        }

        .form-card-head h3 {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 600;
        }

        .form-card-body {
            padding: 22px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            display: block;
            margin-bottom: 8px;
            letter-spacing: .2px;
        }

        .form-control {
            padding: 11px 14px;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .mb-16 {
            margin-bottom: 16px;
        }

        .is-invalid {
            border-color: #dc2626 !important;
        }

        .field-error {
            font-size: 11px;
            color: #dc2626;
            margin-top: 4px;
        }

        .notice-box {
            background: #f9f5ee;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .btn-submit {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            width: 100%;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            margin-top: 8px;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--gold);
            color: var(--stone);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .btn-back:hover {
            color: var(--stone);
        }

        @media (max-width:480px) {
            .two-col {
                grid-template-columns: 1fr;
            }

            .booking-summary {
                flex-wrap: wrap;
            }
        }
    </style>
@endpush

@section('content')
    <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back to booking
    </a>

    <div class="page-title">Reschedule Booking</div>
    <div class="page-sub">Change your check-in/check-out date and time</div>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    <div class="booking-summary">
        <div class="summary-icon">🏝️</div>
        <div>
            <div class="summary-name">{{ $booking->property->property_name }}</div>
            <div class="summary-dates">
                Currently: {{ $booking->check_in_date->format('M d, Y') }}
                {{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}
                — {{ $booking->check_out_date->format('M d, Y') }}
                {{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}
                · {{ $booking->booking_ref }}
            </div>
        </div>
    </div>

    <div class="notice-box">
        <i class="bi bi-info-circle me-1"></i>
        Choose a new check-in date and slot — Day (8:00 AM–5:00 PM) or Night (7:00 PM–6:00 AM).
        If the new dates have a different price, we'll bill you the difference or issue a refund automatically.
        <br><br>
        <strong style="color:var(--stone);">Reschedule policy:</strong>
        Each booking may be rescheduled up to {{ \App\Models\Booking::MAX_RESCHEDULES }} times, and only up to
        {{ \App\Models\Booking::RESCHEDULE_CUTOFF_DAYS }} days before check-in.
        You have <strong style="color:var(--stone);">{{ $booking->reschedulesRemaining() }}</strong> left for this booking.
    </div>

    <form method="POST" action="{{ route('customer.bookings.reschedule.update', $booking) }}">
        @csrf
        @method('PATCH')
        <div class="form-card">
            <div class="form-card-head">
                <h3>New Dates</h3>
            </div>
            <div class="form-card-body">
                <div class="mb-16">
                    <label class="form-label">Check-in Date</label>
                    <input type="date" name="checkin" class="form-control @error('checkin') is-invalid @enderror"
                        value="{{ old('checkin', $booking->check_in_date->format('Y-m-d')) }}"
                        min="{{ today()->format('Y-m-d') }}" required>
                </div>
                <div class="mb-16">
                    <label class="form-label">Slot</label>
                    <div class="two-col">
                        <div class="form-check">
                            <input type="radio" name="slot" value="day" id="slot_day" class="form-check-input"
                                {{ old('slot', $booking->slotKey() ?? 'day') === 'day' ? 'checked' : '' }}>
                            <label for="slot_day" class="form-check-label">Day (8:00 AM – 5:00 PM)</label>
                        </div>
                        <div class="form-check">
                            <input type="radio" name="slot" value="night" id="slot_night" class="form-check-input"
                                {{ old('slot', $booking->slotKey() ?? 'day') === 'night' ? 'checked' : '' }}>
                            <label for="slot_night" class="form-check-label">Night (7:00 PM – 6:00 AM)</label>
                        </div>
                    </div>
                </div>
                @error('dates')
                    <div class="field-error" style="margin-bottom:12px;">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn-submit"><i class="bi bi-calendar-check"></i> Confirm Reschedule</button>
            </div>
        </div>
    </form>
@endsection

