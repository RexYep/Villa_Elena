@extends('layouts.admin')

@section('title', 'New Booking — Villa Elena Admin')
@section('page-title', 'New Booking')
@section('page-subtitle', 'Create a booking manually or for a walk-in guest')

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

        /* Price Preview */
        .price-preview {
            background: var(--terracotta);
            border-radius: 12px;
            padding: 20px;
            color: #fff;
        }

        .price-preview h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            color: var(--gold-light);
            margin-bottom: 14px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .price-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 16px;
            margin-top: 6px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .price-row span:first-child {
            color: rgba(255, 255, 255, 0.6);
        }

        @media (max-width: 900px) {
            .form-grid {
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
        <span class="current">New Booking</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach ($errors->all() as $error)
                {{ $error }}.
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bookings.store') }}" id="bookingForm">
        @csrf

        <div class="form-grid">

            <div>
                {{-- Guest --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-cyan"><i class="bi bi-person"></i></div>
                        <h3>Guest</h3>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label">Select Guest <span class="req">*</span></label>
                        <select name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">Choose a guest...</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('user_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->full_name }} — {{ $customer->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <div class="text-muted-theme" style="margin-top:10px;font-size:12px;">
                            Guest not in the list? <a href="{{ route('admin.users.create') }}" style="color:#2e5fa3;">Create
                                new guest account →</a>
                        </div>
                    </div>
                </div>

                {{-- Property & Dates --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-green"><i class="bi bi-house-door"></i></div>
                        <h3>Property & Dates</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label">Property <span class="req">*</span></label>
                            <select name="property_id" id="propertySelect"
                                class="form-select @error('property_id') is-invalid @enderror" required>
                                <option value="">Choose a property...</option>
                                @foreach ($properties as $property)
                                    <option value="{{ $property->id }}" data-price="{{ $property->base_price }}"
                                        data-weekend="{{ $property->weekend_price ?? $property->base_price }}"
                                        {{ old('property_id') == $property->id ? 'selected' : '' }}>
                                        {{ $property->property_name }} —
                                        ₱{{ number_format($property->base_price, 2) }}/night
                                    </option>
                                @endforeach
                            </select>
                            @error('property_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Check-in Date <span class="req">*</span></label>
                            <input type="date" name="check_in_date" id="checkIn"
                                class="form-control @error('check_in_date') is-invalid @enderror"
                                value="{{ old('check_in_date') }}" min="{{ date('Y-m-d') }}" required>
                            @error('check_in_date')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slot <span class="req">*</span></label>
                            <div class="two-col">
                                <div class="form-check">
                                    <input type="radio" name="slot" value="day" id="slot_day"
                                        class="form-check-input" {{ old('slot', 'day') === 'day' ? 'checked' : '' }}
                                        required>
                                    <label for="slot_day" class="form-check-label">Day (8:00 AM – 5:00 PM)</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" name="slot" value="night" id="slot_night"
                                        class="form-check-input" {{ old('slot') === 'night' ? 'checked' : '' }}>
                                    <label for="slot_night" class="form-check-label">Night (7:00 PM – 6:00 AM)</label>
                                </div>
                            </div>
                            @error('slot')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="two-col">
                            <div>
                                <label class="form-label">Number of Guests <span class="req">*</span></label>
                                <input type="number" name="num_guests"
                                    class="form-control @error('num_guests') is-invalid @enderror"
                                    value="{{ old('num_guests', 1) }}" min="1" required>
                                @error('num_guests')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Booking Source <span class="req">*</span></label>
                                <select name="source" class="form-select" required>
                                    <option value="walk_in" {{ old('source') == 'walk_in' ? 'selected' : '' }}>🚶 Walk-in
                                    </option>
                                    <option value="phone" {{ old('source') == 'phone' ? 'selected' : '' }}>📞 Phone</option>
                                    <option value="online" {{ old('source') == 'online' ? 'selected' : '' }}>🌐 Online</option>
                                    <option value="partner" {{ old('source') == 'partner' ? 'selected' : '' }}>🤝 Partner
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Special Requests --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-amber"><i class="bi bi-chat-dots"></i></div>
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
                        <div class="card-icon tag-amber"><i class="bi bi-receipt"></i></div>
                        <h3>Price Preview</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="price-preview">
                            <h4>Estimated Cost</h4>
                            <div class="price-row">
                                <span>Slot</span>
                                <span id="previewNights">—</span>
                            </div>
                            <div class="price-row">
                                <span>Package Rate</span>
                                <span id="previewRate">—</span>
                            </div>
                            <div class="price-row">
                                <span>Total</span>
                                <span id="previewTotal">—</span>
                            </div>
                        </div>
                        <p class="text-muted-theme" style="font-size:11px;margin-top:10px;text-align:center;">
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

@endsection

@push('scripts')
    <script>
        const SLOT_TIMES = {
            day: '08:00',
            night: '19:00'
        };

        function updatePreview() {
            const checkIn = document.getElementById('checkIn').value;
            const slotInput = document.querySelector('input[name="slot"]:checked');
            const select = document.getElementById('propertySelect');
            const option = select.options[select.selectedIndex];

            if (!checkIn || !slotInput || !option.value) return;

            const slot = slotInput.value;
            const ciTime = SLOT_TIMES[slot];

            const basePrice = parseFloat(option.dataset.price) || 0;
            const weekendPrice = parseFloat(option.dataset.weekend) || basePrice;

            // Flat/package price base lang sa CHECK-IN day/slot — hindi per-night.
            // Kailangang tumugma ito sa Property::getPackagePrice() sa backend.
            // Preview lang ito; hindi kasama ang holiday/pricing-rule overrides.
            const d1 = new Date(checkIn);
            const dow = d1.getDay(); // 0=Sunday ... 6=Saturday
            let price;

            if (dow === 0) {
                price = ciTime < '18:00' ? weekendPrice : basePrice;
            } else if (dow === 5 || dow === 6) {
                price = weekendPrice;
            } else {
                price = basePrice;
            }

            document.getElementById('previewNights').textContent = slot === 'day' ? 'Day (9 hrs)' : 'Night (11 hrs)';
            document.getElementById('previewRate').textContent = '₱' + basePrice.toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('previewTotal').textContent = '₱' + price.toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
        }

        document.getElementById('checkIn').addEventListener('change', updatePreview);
        document.querySelectorAll('input[name="slot"]').forEach(el => el.addEventListener('change', updatePreview));
        document.getElementById('propertySelect').addEventListener('change', updatePreview);
    </script>
@endpush

