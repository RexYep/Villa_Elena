@extends('layouts.admin')

@section('title', 'Edit Booking ' . $booking->booking_ref . ' — Villa Elena Admin')
@section('page-title', 'Edit Booking')
@section('page-subtitle', $booking->booking_ref . ' · ' . ($booking->property->property_name ?? ''))

@push('styles')
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            align-items: start;
        }

        .btn-submit {
            padding: 12px 24px;
            width: 100%;
        }

        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
            padding: 8px;
            border-radius: 8px;
        }

        .btn-cancel:hover {
            background: var(--sand);
            color: var(--text-main);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .info-item .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .info-item .value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.bookings.index') }}">Bookings</a>
        <span class="sep">›</span>
        <span class="current">Edit {{ $booking->booking_ref }}</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach ($errors->all() as $error)
                {{ $error }}.
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
        @csrf @method('PUT')

        <div class="form-grid">

            <div>
                {{-- Booking Info (read-only) --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-cyan"><i class="bi bi-info-circle"></i></div>
                        <h3>Booking Info</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label">Guest</div>
                                <div class="value">{{ $booking->user->full_name ?? 'N/A' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Property</div>
                                <div class="value">{{ $booking->property->property_name ?? 'N/A' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Check-in</div>
                                <div class="value">
                                    {{ $booking->check_in_date->format('M d, Y') }}
                                    @if ($booking->check_in_time)
                                        {{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}
                                    @endif
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="label">Check-out</div>
                                <div class="value">
                                    {{ $booking->check_out_date->format('M d, Y') }}
                                    @if ($booking->check_out_time)
                                        {{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}
                                    @endif
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="label">Status</div>
                                <div class="value">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</div>
                            </div>
                            <div class="info-item">
                                <div class="label">Total Amount</div>
                                <div class="value">₱{{ number_format($booking->total_amount, 2) }}</div>
                            </div>
                        </div>
                        <p class="text-muted-theme" style="font-size:11.5px;margin-top:14px;margin-bottom:0;">
                            <i class="bi bi-info-circle me-1"></i>
                            Guest, property, dates, and status are not editable here. Use the status action buttons on the
                            <a href="{{ route('admin.bookings.show', $booking) }}">booking detail page</a> to change
                            status,
                            or "Extend Stay" there to move the check-out.
                        </p>
                    </div>
                </div>

                {{-- Editable Fields --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-green"><i class="bi bi-pencil"></i></div>
                        <h3>Editable Details</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label">Number of Guests <span class="req">*</span></label>
                            <input type="number" name="num_guests"
                                class="form-control @error('num_guests') is-invalid @enderror"
                                value="{{ old('num_guests', $booking->num_guests) }}" min="1" required>
                            @error('num_guests')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label">Special Requests</label>
                            <textarea name="special_requests" class="form-control" rows="4"
                                placeholder="Any special requests or notes for this booking...">{{ old('special_requests', $booking->special_requests) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Save --}}
            <div>
                <div class="form-card">
                    <div class="form-card-body">
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-check-lg me-2"></i> Save Changes
                        </button>
                        <a href="{{ route('admin.bookings.show', $booking) }}" class="btn-cancel">Cancel</a>
                    </div>
                </div>
            </div>

        </div>
    </form>

@endsection

