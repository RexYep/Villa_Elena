@extends('layouts.staff')

@section('title', 'Walk-in Booking — Villa Elena Staff')
@section('page-title', 'Walk-in Booking')
@section('page-subtitle', 'Create a new booking for a walk-in guest')

@push('styles')
    <style>
        .main {
            max-width: 900px;
        }

        /* Form */
        .section-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .section-head {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .section-head h3 {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 600;
        }

        .section-body {
            padding: 22px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
            letter-spacing: .2px;
        }

        .form-control,
        .form-select {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            width: 100%;
            background: #fff;
            transition: border-color .2s;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(44, 36, 22, .06);
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .three-col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        .mb-14 {
            margin-bottom: 14px;
        }

        .is-invalid {
            border-color: #dc2626 !important;
        }

        .invalid-feedback {
            font-size: 13px;
            color: #dc2626;
            margin-top: 3px;
        }

        /* Guest toggle */
        .guest-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
        }

        .toggle-btn {
            flex: 1;
            padding: 10px;
            border-radius: 9px;
            border: 1.5px solid var(--border);
            background: #fff;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            color: var(--muted);
            transition: all .2s;
            text-align: center;
        }

        .toggle-btn.active {
            background: var(--navy);
            color: #fff;
            border-color: var(--navy);
        }

        /* Price preview */
        .price-preview {
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 16px;
            margin-top: 14px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 5px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .price-row:last-child {
            border-bottom: none;
        }

        .price-row.total {
            font-weight: 700;
            font-size: 15px;
            border-top: 2px solid var(--border);
            padding-top: 10px;
            margin-top: 4px;
        }

        .price-row.balance {
            color: #dc2626;
            font-weight: 600;
        }

        .price-row.paid {
            color: #16a34a;
        }

        /* Buttons */
        .btn-submit {
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 13px 28px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
        }

        .btn-submit:hover {
            background: var(--gold);
            color: var(--navy);
        }

        .btn-submit:disabled {
            background: #cbd5e1;
            color: #64748b;
            cursor: not-allowed;
        }

        .btn-back {
            background: #fff;
            color: var(--muted);
            border: 1.5px solid var(--border);
            border-radius: 9px;
            padding: 13px 24px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
        }

        .btn-back:hover {
            border-color: var(--navy);
            color: var(--navy);
        }

        .optional-tag {
            color: var(--muted);
            font-weight: 400;
            font-size: 13px;
        }

        .new-guest-password-note {
            background: #fef9c3;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            color: #a16207;
            margin-top: 10px;
        }

        @media (max-width: 560px) {

            .two-col,
            .three-col {
                grid-template-columns: 1fr;
            }

            .guest-toggle {
                flex-direction: column;
            }
        }
    </style>
@endpush

@section('content')

    @if ($errors->any())
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
                <div class="section-icon tag-blue"><i class="bi bi-person"></i></div>
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
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('user_id') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->full_name }} —
                                {{ $customer->email ?? ($customer->phone ?? 'walang email/phone naka-record') }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- New Guest --}}
                <div id="newGuestFields" style="display:none;">
                    <div class="mb-14">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name"
                            class="form-control {{ $errors->has('full_name') ? 'is-invalid' : '' }}"
                            value="{{ old('full_name') }}" placeholder="Juan Dela Cruz">
                        @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-14">
                        <label class="form-label">Create a login account for this guest?</label>
                        <div class="guest-toggle" style="margin-bottom:0;">
                            <button type="button" class="toggle-btn" id="btnNoAccount" onclick="setCreateAccount('no')">
                                <i class="bi bi-person-lines-fill me-1"></i> No — Guest Record Only
                            </button>
                            <button type="button" class="toggle-btn" id="btnYesAccount" onclick="setCreateAccount('yes')">
                                <i class="bi bi-person-plus me-1"></i> Yes — Create Account
                            </button>
                        </div>
                        <input type="hidden" name="create_account" id="createAccount" value="no">
                        @error('create_account')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="two-col mb-14">
                        <div>
                            <label class="form-label">Email <span class="optional-tag"
                                    id="emailOptionalTag">(optional)</span></label>
                            <input type="email" name="email" id="newGuestEmail"
                                class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                value="{{ old('email') }}" placeholder="juan@email.com">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label">Phone <span class="optional-tag">(optional)</span></label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}"
                                placeholder="09XX XXX XXXX">
                        </div>
                    </div>

                    <div class="new-guest-password-note" id="noAccountNote">
                        <i class="bi bi-info-circle me-1"></i>
                        A guest record will be created for booking/payment history, but this guest will not be able to log
                        in
                        — no login account will be created and no email will be sent.
                    </div>
                    <div class="new-guest-password-note" id="yesAccountNote" style="display:none;">
                        <i class="bi bi-info-circle me-1"></i>
                        A guest account will be created with a randomly generated password —
                        the guest will receive a password-reset email so they can set their own password.
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
                    @if ($availableProperties->count() > 0)
                        @php($villa = $availableProperties->first())
                        <input type="hidden" name="property_id" value="{{ old('property_id', $villa->id) }}">
                        <div class="form-control"
                            style="background:#f8fafc;display:flex;align-items:center;justify-content:space-between;"
                            data-base="{{ $villa->base_price }}"
                            data-weekend="{{ $villa->weekend_price ?? $villa->base_price }}"
                            data-max="{{ $villa->max_capacity }}" id="propertySelect">
                            <span><i class="bi bi-house-heart-fill me-2"
                                    style="color:var(--gold);"></i>{{ $villa->property_name }}</span>
                            <span class="text-muted-theme" style="font-size: 14px;">Max {{ $villa->max_capacity }}
                                guests</span>
                        </div>
                    @else
                        <div class="alert alert-danger">No Villa is currently available.</div>
                    @endif
                    @error('property_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-14">
                    <label class="form-label">Check-in Date</label>
                    <input type="date" name="check_in_date" id="checkinDate"
                        class="form-control {{ $errors->has('check_in_date') ? 'is-invalid' : '' }}"
                        value="{{ old('check_in_date', $prefill['date'] ?? date('Y-m-d')) }}" min="{{ date('Y-m-d') }}"
                        onchange="updatePrice()">
                    @error('check_in_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-14">
                    <label class="form-label">Slot</label>
                    @php($selectedSlot = old('slot', $prefill['slot'] ?? 'day'))
                    <div class="two-col">
                        <div class="form-check">
                            <input type="radio" name="slot" value="day" id="slot_day" class="form-check-input"
                                {{ $selectedSlot === 'day' ? 'checked' : '' }} onchange="updatePrice()">
                            <label for="slot_day" class="form-check-label">Day (8:00 AM – 5:00 PM)</label>
                        </div>
                        <div class="form-check">
                            <input type="radio" name="slot" value="night" id="slot_night"
                                class="form-check-input" {{ $selectedSlot === 'night' ? 'checked' : '' }}
                                onchange="updatePrice()">
                            <label for="slot_night" class="form-check-label">Night (7:00 PM – 6:00 AM)</label>
                        </div>
                    </div>
                    @error('slot')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @if (($prefill['date'] ?? null) && !old('check_in_date'))
                        <div
                            style="background:var(--tag-green-bg);color:var(--tag-green-fg);border-radius:8px;padding:9px 13px;font-size: 14px;margin-top:10px;">
                            <i class="bi bi-check-circle me-1"></i>
                            Pre-filled from the Availability page — this slot was free when you picked it.
                        </div>
                    @endif
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
                    <div class="text-muted-theme section-label" style="font-size: 14px;margin-bottom:10px;">Price Breakdown
                    </div>
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
                <div class="section-icon tag-amber"><i class="bi bi-cash-stack"></i></div>
                <h3>Payment <span class="optional-tag" style="font-size:13px;font-family:'DM Sans',sans-serif;">(optional
                        — can be recorded later)</span></h3>
            </div>
            <div class="section-body">
                <div class="two-col mb-14">
                    <div>
                        <label class="form-label">Amount Received</label>
                        <input type="number" name="payment_amount" id="paymentAmount"
                            class="form-control {{ $errors->has('payment_amount') ? 'is-invalid' : '' }}"
                            value="{{ old('payment_amount', 0) }}" min="0" step="0.01" placeholder="0.00"
                            oninput="updatePaymentPreview()">
                        @error('payment_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method"
                            class="form-select {{ $errors->has('payment_method') ? 'is-invalid' : '' }}">
                            <option value="">-- Select --</option>
                            <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="qrph" {{ old('payment_method') == 'qrph' ? 'selected' : '' }}>QR Ph (GCash /
                                Maya / bank app)</option>
                        </select>
                        @error('payment_method')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="mb-14">
                    <label class="form-label">Payment Type <span class="optional-tag"></span></label>
                    <input type="hidden" name="payment_type" id="paymentTypeHidden"
                        value="{{ old('payment_type', 'partial') }}">
                    <div class="form-control text-muted-theme" id="paymentTypeDisplay" style="background:#f8fafc;">
                        No payment received yet
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
@endsection

@push('scripts')
    <script>
        // ── Guest type toggle ──────────────────────────────────────────
        function setGuestType(type) {
            document.getElementById('guestType').value = type;
            document.getElementById('existingGuestFields').style.display = type === 'existing' ? 'block' : 'none';
            document.getElementById('newGuestFields').style.display = type === 'new' ? 'block' : 'none';
            document.getElementById('btnExisting').classList.toggle('active', type === 'existing');
            document.getElementById('btnNew').classList.toggle('active', type === 'new');
        }

        // Restore on validation error
        @if (old('guest_type') === 'new')
            setGuestType('new');
        @endif

        // ── Create-account toggle (bagong guest lang) ───────────────────
        function setCreateAccount(choice) {
            document.getElementById('createAccount').value = choice;
            document.getElementById('btnNoAccount').classList.toggle('active', choice === 'no');
            document.getElementById('btnYesAccount').classList.toggle('active', choice === 'yes');

            const emailInput = document.getElementById('newGuestEmail');
            const emailTag = document.getElementById('emailOptionalTag');
            const noNote = document.getElementById('noAccountNote');
            const yesNote = document.getElementById('yesAccountNote');

            if (choice === 'yes') {
                emailInput.required = true;
                emailTag.textContent = '(required)';
                noNote.style.display = 'none';
                yesNote.style.display = 'block';
            } else {
                emailInput.required = false;
                emailTag.textContent = '(optional)';
                noNote.style.display = 'block';
                yesNote.style.display = 'none';
            }
        }

        // Default sa "Hindi" (guest record lang, walang account) — pati na rin
        // sa pag-restore pagkatapos ng validation error.
        setCreateAccount(@json(old('create_account', 'no')));

        // ── Price calculation ──────────────────────────────────────────
        // Ang presyo ay galing sa SERVER (staff.walkin.quote), hindi na
        // kinukuwenta rito. Dati ay may kopya ng Property::getPackagePrice()
        // ang JavaScript na ito, na hindi nakakakita ng pricing-rule
        // overrides ni ng seasonal promo — at dahil ang overpayment guard
        // sa ibaba ay sumusukat laban sa `calculatedTotal`, ang isang
        // maling numero rito ay nagpapahintulot ng bayad na tatanggihan
        // naman ng server. Isang pinagmumulan na lang ngayon.
        let calculatedTotal = 0;
        let quoteAbortController = null;

        const QUOTE_URL = "{{ route('staff.walkin.quote') }}";
        const PROPERTY_ID = "{{ $availableProperties->first()->id ?? '' }}";

        const peso = n => '₱' + n.toLocaleString('en-PH', {
            minimumFractionDigits: 2
        });

        function updatePrice() {
            const propDiv = document.getElementById('propertySelect');
            const ci = document.getElementById('checkinDate').value;
            const slotInput = document.querySelector('input[name="slot"]:checked');
            const preview = document.getElementById('pricePreview');

            if (!propDiv || !PROPERTY_ID || !ci || !slotInput) {
                preview.style.display = 'none';
                return;
            }

            // Update max guests — hindi ito nakadepende sa presyo.
            document.getElementById('numGuests').max = parseInt(propDiv.dataset.max);

            if (quoteAbortController) quoteAbortController.abort();
            quoteAbortController = new AbortController();

            const params = new URLSearchParams({
                property_id: PROPERTY_ID,
                checkin: ci,
                slot: slotInput.value
            });

            fetch(`${QUOTE_URL}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    signal: quoteAbortController.signal
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.valid) {
                        preview.style.display = 'none';
                        return;
                    }
                    renderQuote(data);
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    // Hindi puwedeng manghula ng presyo kapag hindi umabot
                    // ang server — mas mabuting walang ipakita kaysa sa
                    // isang numerong baka mali.
                    preview.style.display = 'none';
                    calculatedTotal = 0;
                });
        }

        function renderQuote(data) {
            const preview = document.getElementById('pricePreview');

            const promoRow = data.discount > 0 ? `
    <div class="price-row" style="color:#15803d;font-weight:600;">
        <span><i class="bi bi-tag-fill" style="font-size: 13px;"></i> ${data.promo_label} (${data.promo_value})</span>
        <span>−${peso(data.discount)}</span>
    </div>` : '';

            preview.innerHTML =
                `<div class="text-muted-theme section-label" style="font-size: 14px;margin-bottom:10px;">Price Breakdown</div>
    <div id="nightBreakdown">
    <div class="price-row">
        <span class="text-muted-theme">${data.day_label} check-in${data.is_peak ? ' <span style="color:#b8943f;">★</span>' : ''}</span>
        <span>${peso(data.base)}</span>
    </div>
    <div class="price-row text-muted-theme" style="font-size: 13px;">
        <span>Flat package rate (${data.hours.toFixed(1)} oras)</span>
    </div>
    ${promoRow}
    </div>
    <div class="price-row total">
        <span>Total Amount</span>
        <span id="totalAmount">${peso(data.total)}</span>
    </div>`;

            calculatedTotal = data.total;
            preview.style.display = 'block';

            const submitBtn = document.querySelector('.btn-submit');
            if (submitBtn) submitBtn.disabled = false;

            updatePaymentPreview();
        }

        function updatePaymentPreview() {
            const paidInput = document.getElementById('paymentAmount');
            const paid = parseFloat(paidInput.value) || 0;
            const summary = document.getElementById('paymentSummary');
            const typeDisplay = document.getElementById('paymentTypeDisplay');
            const typeHidden = document.getElementById('paymentTypeHidden');
            const submitBtn = document.querySelector('.btn-submit');

            // Overpayment guard (dapat tumugma sa backend validation) — hindi
            // puwedeng lumagpas ang natanggap na bayad sa kabuuang halaga.
            if (calculatedTotal > 0 && paid > calculatedTotal) {
                paidInput.classList.add('is-invalid');
                typeDisplay.textContent =
                    `⚠️ Exceeds the total (₱${calculatedTotal.toLocaleString('en-PH',{minimumFractionDigits:2})}). If there is change, just type the net amount received..`;
                typeDisplay.style.color = '#dc2626';
                if (submitBtn) submitBtn.disabled = true;
                summary.style.display = 'none';
                return;
            }
            paidInput.classList.remove('is-invalid');
            if (submitBtn) submitBtn.disabled = false;

            if (paid <= 0 || calculatedTotal <= 0) {
                summary.style.display = 'none';
                typeDisplay.textContent = 'No payment received yet';
                typeDisplay.style.color = 'var(--muted)';
                typeHidden.value = 'partial';
                return;
            }

            // Auto payment type: buo ang bayad = full_payment, hindi buo = partial.
            // Kinukuha ang desisyon dito, hindi na kailangang manual pumili si
            // staff — iwas human error.
            if (paid >= calculatedTotal) {
                typeHidden.value = 'full_payment';
                typeDisplay.textContent = '✅ Full Payment';
                typeDisplay.style.color = '#16a34a';
            } else {
                typeHidden.value = 'partial';
                typeDisplay.textContent = '💰 Partial Payment';
                typeDisplay.style.color = '#a16207';
            }

            const balance = Math.max(0, calculatedTotal - paid);
            document.getElementById('psTotalAmount').textContent = '₱' + calculatedTotal.toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('psPaidAmount').textContent = '₱' + paid.toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('psBalanceAmount').textContent = '₱' + balance.toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            summary.style.display = 'block';
        }

        document.getElementById('checkinDate').addEventListener('change', updatePrice);

        // Run on load — may default check-in date + slot na naka-preset,
        // para agad makita ni staff ang price preview.
        updatePrice();
    </script>
@endpush

