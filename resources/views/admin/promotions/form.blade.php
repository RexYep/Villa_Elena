@extends('layouts.admin')

@php $isEdit = (bool) $promo->exists; @endphp

@section('title', ($isEdit ? 'Edit' : 'New') . ' Promo — Villa Elena Admin')
@section('page-title', $isEdit ? 'Edit Promo' : 'New Promo')
@section('page-subtitle', $isEdit ? 'Update ' . $promo->label : 'A seasonal discount that applies automatically to the villa rate')

@push('styles')
    <style>
        .form-wrapper { max-width: 760px; }

        .preview-card {
            background: var(--sand);
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 14px 18px;
            margin-top: 14px;
            font-size: 13px;
            color: var(--text-main);
        }

        .preview-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .preview-old {
            text-decoration: line-through;
            color: var(--muted);
            margin-right: 8px;
        }

        .preview-new {
            font-weight: 700;
            color: var(--terracotta);
        }

        .check-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
        }

        .check-row:first-child { border-top: none; }

        .check-row input[type="checkbox"] {
            width: 17px;
            height: 17px;
            margin-top: 2px;
            accent-color: var(--terracotta);
            flex-shrink: 0;
        }

        .check-row .check-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
        }

        .check-row .check-hint {
            font-size: 11.5px;
            color: var(--muted);
            line-height: 1.5;
            margin-top: 2px;
        }
    </style>
@endpush

@section('content')

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.promotions.index') }}">Promotions</a>
        <span class="sep">›</span>
        <span class="current">{{ $isEdit ? 'Edit: ' . $promo->label : 'New Promo' }}</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach ($errors->all() as $error)
                {{ $error }}.
            @endforeach
        </div>
    @endif

    <div class="form-wrapper">
        <form method="POST" action="{{ $isEdit ? route('admin.promotions.update', $promo) : route('admin.promotions.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            {{-- ── What the guest sees ─────────────────────────────── --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon"><i class="bi bi-megaphone"></i></div>
                    <h3>Promo details</h3>
                </div>
                <div class="form-card-body">
                    <div class="mb-3">
                        <label class="form-label">Promo name <span class="req">*</span></label>
                        <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                            value="{{ old('label', $promo->label) }}" placeholder="e.g. Rainy Season Special" required>
                        <span class="hint">This is the headline guests see on the landing page and in their notification.</span>
                        @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Short description</label>
                        <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                            value="{{ old('description', $promo->description) }}"
                            placeholder="e.g. Book any weekday night this August and save.">
                        <span class="hint">One line, shown under the promo name. Optional.</span>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- ── The discount itself ─────────────────────────────── --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon"><i class="bi bi-percent"></i></div>
                    <h3>Discount</h3>
                </div>
                <div class="form-card-body">
                    <div class="two-col mb-3">
                        <div>
                            <label class="form-label">Type <span class="req">*</span></label>
                            <select name="type" id="promoType" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="percentage" {{ old('type', $promo->type) === 'percentage' ? 'selected' : '' }}>Percentage off</option>
                                <option value="fixed" {{ old('type', $promo->type) === 'fixed' ? 'selected' : '' }}>Fixed peso amount off</option>
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Value <span class="req">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="value" id="promoValue"
                                class="form-control @error('value') is-invalid @enderror"
                                value="{{ old('value', $promo->value ?? \App\Models\Discount::DEFAULT_PERCENTAGE) }}" required>
                            <span class="hint" id="valueHint">Percentage off the villa base rate (max 100).</span>
                            @error('value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- Live preview off the two real package rates, so the
                         admin sees pesos rather than a percentage they have
                         to do arithmetic on. --}}
                    <div class="preview-card" data-base="{{ $rates['base'] }}" data-weekend="{{ $rates['weekend'] }}">
                        <div style="font-weight:600;margin-bottom:6px;">What guests will pay</div>
                        <div class="preview-row">
                            <span>Regular rate (Mon–Thu, Sun after 6PM)</span>
                            <span><span class="preview-old">₱{{ number_format($rates['base'], 2) }}</span><span class="preview-new" id="prevRegular">—</span></span>
                        </div>
                        <div class="preview-row">
                            <span>Peak rate (Fri/Sat, Sun before 6PM)</span>
                            <span><span class="preview-old">₱{{ number_format($rates['weekend'], 2) }}</span><span class="preview-new" id="prevPeak">—</span></span>
                        </div>
                        <div class="hint" style="margin-top:8px;">
                            Based on this villa's current package rates. A pricing rule for a specific date overrides
                            the base rate first — the discount then applies to whatever that rate turns out to be.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── When it applies ─────────────────────────────────── --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon"><i class="bi bi-calendar-range"></i></div>
                    <h3>When it applies</h3>
                </div>
                <div class="form-card-body">
                    <div class="two-col mb-3">
                        @php
                            $today = now()->format('Y-m-d');
                            // Ang isang tumatakbo nang promo ay may nakaraang
                            // start_date sa likas na paraan — hindi dapat
                            // ipakita ng browser na invalid iyon habang
                            // inaayos lang ni admin ang ibang field.
                            $startMin = $promo->start_date && $promo->start_date->lt(now()->startOfDay())
                                ? $promo->start_date->format('Y-m-d')
                                : $today;
                        @endphp
                        <div>
                            <label class="form-label">Starts</label>
                            <input type="date" name="start_date" min="{{ $startMin }}"
                                class="form-control @error('start_date') is-invalid @enderror"
                                value="{{ old('start_date', $promo->start_date?->format('Y-m-d')) }}">
                            <span class="hint">Leave blank to start immediately. Can't be set to a past date.</span>
                            @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Ends</label>
                            <input type="date" name="expiry_date" min="{{ $today }}"
                                class="form-control @error('expiry_date') is-invalid @enderror"
                                value="{{ old('expiry_date', $promo->expiry_date?->format('Y-m-d')) }}">
                            <span class="hint">Inclusive — a promo ending Aug 31 still covers an Aug 31 check-in. Blank = no end date.</span>
                            @error('expiry_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="two-col">
                        <div>
                            <label class="form-label">Slot <span class="req">*</span></label>
                            <select name="applies_to" class="form-select @error('applies_to') is-invalid @enderror" required>
                                <option value="all" {{ old('applies_to', $promo->applies_to) === 'all' ? 'selected' : '' }}>Both slots</option>
                                <option value="day" {{ old('applies_to', $promo->applies_to) === 'day' ? 'selected' : '' }}>{{ \App\Models\Booking::SLOTS['day']['label'] }} only</option>
                                <option value="night" {{ old('applies_to', $promo->applies_to) === 'night' ? 'selected' : '' }}>{{ \App\Models\Booking::SLOTS['night']['label'] }} only</option>
                            </select>
                            @error('applies_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Booking limit</label>
                            <input type="number" min="1" name="usage_limit"
                                class="form-control @error('usage_limit') is-invalid @enderror"
                                value="{{ old('usage_limit', $promo->usage_limit) }}" placeholder="Unlimited">
                            <span class="hint">
                                Stop applying after this many bookings.
                                @if ($isEdit) Used so far: <strong>{{ $promo->used_count }}</strong>. @endif
                            </span>
                            @error('usage_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <span class="hint" style="margin-top:12px;">
                        The window is matched against the guest's <strong>check-in date</strong>, not the date they book —
                        so a promo dated for September discounts September stays, whenever they were booked.
                        A promo whose window hasn't started yet is <strong>still advertised on the landing page</strong>
                        (as “For stays Sep 01 – Sep 30”), so guests can book ahead for it.
                        If two promos overlap, the one giving the larger peso discount wins — they never stack.
                    </span>
                </div>
            </div>

            {{-- ── Visibility & announcement ───────────────────────── --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon"><i class="bi bi-broadcast"></i></div>
                    <h3>Visibility</h3>
                </div>
                <div class="form-card-body">
                    <div class="check-row">
                        <input type="checkbox" name="is_active" id="isActive" value="1"
                            {{ old('is_active', $promo->is_active ?? 1) ? 'checked' : '' }}>
                        <div>
                            <label class="check-label" for="isActive">Active</label>
                            <div class="check-hint">Off means the discount stops applying to new bookings straight away.</div>
                        </div>
                    </div>

                    <div class="check-row">
                        <input type="checkbox" name="is_public" id="isPublic" value="1"
                            {{ old('is_public', $promo->is_public ?? 1) ? 'checked' : '' }}>
                        <div>
                            <label class="check-label" for="isPublic">Show on the landing page</label>
                            <div class="check-hint">
                                This is how guests who aren't logged in find out — most first-time bookers have no account
                                yet, so nothing else reaches them. Turn it off for a quiet discount that still applies
                                automatically but isn't advertised.
                            </div>
                        </div>
                    </div>

                    @if ($promo->notified_at)
                        <div class="check-row">
                            <i class="bi bi-check-circle" style="color:#15803d;font-size:16px;margin-top:1px;"></i>
                            <div>
                                <span class="check-label">Already announced</span>
                                <div class="check-hint">
                                    Sent to customers {{ $promo->notified_at->diffForHumans() }}. Each promo is announced
                                    once — editing it won't send another round of notifications.
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="check-row">
                            <input type="checkbox" name="notify_customers" id="notifyCustomers" value="1"
                                {{ old('notify_customers') ? 'checked' : '' }}>
                            <div>
                                <label class="check-label" for="notifyCustomers">
                                    Announce to all {{ $customerCount }} customer{{ $customerCount === 1 ? '' : 's' }}
                                </label>
                                <div class="check-hint">
                                    Sends a one-time in-app notification to every active guest account. No email is sent —
                                    the mail quota is reserved for 2FA codes, booking confirmations and password resets.
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div style="display:flex;align-items:center;">
                <button type="submit" class="btn-submit">
                    <i class="bi bi-check-lg me-1"></i> {{ $isEdit ? 'Save Promo' : 'Create Promo' }}
                </button>
                <a href="{{ route('admin.promotions.index') }}" class="btn-cancel-link">Cancel</a>
            </div>
        </form>
    </div>

@endsection

@section('modals')
    <script>
        (function () {
            const typeEl    = document.getElementById('promoType');
            const valueEl   = document.getElementById('promoValue');
            const hintEl    = document.getElementById('valueHint');
            const regularEl = document.getElementById('prevRegular');
            const peakEl    = document.getElementById('prevPeak');

            const rates = {
                base:    parseFloat(document.querySelector('.preview-card').dataset.base),
                weekend: parseFloat(document.querySelector('.preview-card').dataset.weekend),
            };

            const peso = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Kaparehong lohika ng Discount::calculateDiscount() — porsyento
            // na naka-clamp sa 100, fixed na hindi lalampas sa halaga mismo.
            function priceAfter(rate) {
                const v = parseFloat(valueEl.value);
                if (isNaN(v) || v <= 0) return null;

                const off = typeEl.value === 'percentage'
                    ? rate * (Math.min(100, v) / 100)
                    : Math.min(v, rate);

                return Math.max(0, rate - off);
            }

            function render() {
                hintEl.textContent = typeEl.value === 'percentage'
                    ? 'Percentage off the villa base rate (max 100).'
                    : 'Pesos off the villa base rate.';

                valueEl.max = typeEl.value === 'percentage' ? 100 : '';

                const regular = priceAfter(rates.base);
                const peak    = priceAfter(rates.weekend);

                regularEl.textContent = regular === null ? '—' : peso(regular);
                peakEl.textContent    = peak === null ? '—' : peso(peak);
            }

            typeEl.addEventListener('change', render);
            valueEl.addEventListener('input', render);
            render();
        })();
    </script>
@endsection
