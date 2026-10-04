@extends('layouts.staff')

@section('title', 'Walk-in Booking — Villa Elena Staff')
@section('page-title', 'Walk-in Booking')
@section('page-subtitle', 'Create a new booking for a walk-in guest')

@push('styles')
    <style>
        .main {
            max-width: 900px;
        }

        /* Wide screens: payment and the submit button sit beside the guest and
           stay details instead of below them, and stay in view while the
           longer left column scrolls. */
        @media (min-width: 1200px) {
            .main {
                max-width: 1280px;
            }

            .walkin-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.55fr) minmax(320px, 1fr);
                gap: 20px;
                align-items: start;
            }

            .walkin-side {
                position: sticky;
                top: calc(var(--topbar-h) + 28px);
            }

            .walkin-side .two-col {
                grid-template-columns: minmax(0, 1fr);
            }

            .walkin-side .walkin-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .walkin-side .walkin-actions > * {
                justify-content: center;
                text-align: center;
            }
        }

        .walkin-actions {
            display: flex;
            gap: 12px;
            align-items: center;
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
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
        }

        .section-body {
            padding: 22px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--stone);
            margin-bottom: 6px;
            display: block;
            letter-spacing: .2px;
        }

        .form-control,
        .form-select {
            border: 1.5px solid var(--border-strong);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-family: var(--font-body);
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
            font-family: var(--font-body);
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
            background: var(--cream);
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
            border-bottom: 1px solid var(--sand);
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
            background: var(--btn-primary);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 13px 28px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-body);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
        }

        .btn-submit:hover {
            background: var(--btn-primary-hover);
            color: #fff;
        }

        .availability-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            padding: 9px 13px;
            border-radius: 8px;
            margin-top: 10px;
            background: var(--sand);
            color: var(--muted);
        }

        .availability-status.is-available {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .availability-status.is-unavailable {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .btn-submit:disabled {
            background: var(--border);
            color: var(--muted);
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
            font-family: var(--font-body);
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

        <div class="walkin-grid">
        <div class="walkin-main">

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
                    {{-- May id dahil ginagamit ng live quote: nakakaapekto sa
                         presyo kung sino ang guest (promo para sa regular). --}}
                    <select name="user_id" id="guestSelect"
                        class="form-select {{ $errors->has('user_id') ? 'is-invalid' : '' }}">
                        <option value="">Select existing guest</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('user_id') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->full_name }} —
                                {{ $customer->email ?? ($customer->phone ?? 'No email or phone number on record.') }}
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
                        <label for="f_full_name" class="form-label">Full Name</label>
                        <input id="f_full_name" type="text" name="full_name"
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
                            <label for="newGuestEmail" class="form-label">Email <span class="optional-tag"
                                    id="emailOptionalTag">(optional)</span></label>
                            <input type="email" name="email" id="newGuestEmail"
                                class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                value="{{ old('email') }}" placeholder="juan@email.com">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="f_phone" class="form-label">Phone <span class="optional-tag">(optional)</span></label>
                            <input id="f_phone" type="text" name="phone" class="form-control" value="{{ old('phone') }}"
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
                <div class="section-icon tag-green"><i class="bi bi-calendar3"></i></div>
                <h3>Stay Details</h3>
            </div>
            <div class="section-body">
                <div class="mb-14">
                    <label class="form-label">Property</label>
                    @if ($availableProperties->count() > 0)
                        @php($villa = $availableProperties->first())
                        <input type="hidden" name="property_id" value="{{ old('property_id', $villa->id) }}">
                        <div class="form-control"
                            style="background:var(--cream);display:flex;align-items:center;justify-content:space-between;"
                            data-base="{{ $villa->base_price }}"
                            data-weekend="{{ $villa->weekend_price ?? $villa->base_price }}"
                            data-max="{{ $villa->max_capacity }}" id="propertySelect">
                            <span><i class="bi bi-house-heart-fill me-2 text-gold"
                                   ></i>{{ $villa->property_name }}</span>
                            <span class="text-muted-theme fs-14">Max {{ $villa->max_capacity }}
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
                    <label for="checkinDate" class="form-label">Check-in Date</label>
                    <input type="date" name="check_in_date" id="checkinDate"
                        class="form-control {{ $errors->has('check_in_date') ? 'is-invalid' : '' }}"
                        value="{{ old('check_in_date', $prefill['date'] ?? date('Y-m-d')) }}" min="{{ date('Y-m-d') }}">
                    @error('check_in_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-14">
                    <label class="form-label">Slot</label>
                    {{-- Sinasadyang one-line na anyo lang ng PHP directive ang
                         ginagamit sa file na ito: ang paghahalo ng block na anyo ay
                         ipinapares ng compiler sa naunang one-liner at nilalamon
                         ang lahat ng nasa pagitan (tingnan ang CLAUDE.md).

                         At huwag isusulat ang pangalan ng directive kahit sa
                         loob ng komentaryong ito — kinokompile ng Blade ang
                         bawat directive na makita nito saanman sa file, komento
                         man o hindi. Ang bersiyon ng komentong ito na may
                         nakasulat na pangalan ay nagbunga ng
                         "unexpected end of file, expecting endif" — at
                         PUMASA ito sa Blade::compileString(); ang tunay na
                         render lang ang nakahuli. --}}
                    @php($slotKeys = \App\Models\Booking::bookableSlotKeys())
                    @php($selectedSlot = old('slot', $prefill['slot'] ?? ($slotKeys[0] ?? null)))
                    @php($selectedSlot = in_array($selectedSlot, $slotKeys, true) ? $selectedSlot : ($slotKeys[0] ?? null))
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;">
                        @foreach ($slotKeys as $slotKey)
                            <div class="form-check">
                                <input type="radio" name="slot" value="{{ $slotKey }}" id="slot_{{ $slotKey }}"
                                    class="form-check-input" {{ $selectedSlot === $slotKey ? 'checked' : '' }}>
                                <label for="slot_{{ $slotKey }}" class="form-check-label">{{ \App\Models\Booking::SLOTS[$slotKey]['label'] }}</label>
                            </div>
                        @endforeach
                    </div>
                    {{-- Ipinapaliwanag kung bakit iisa lang ang pagpipilian sa
                         isang 22-oras na petsa. --}}
                    <div id="slotOnlyNote" class="form-text" hidden
                        style="margin-top:6px; color:#0f766e; font-size:13px;"></div>
                    @error('slot')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div id="availabilityStatus" class="availability-status" role="status" aria-live="polite">
                        <i class="bi bi-calendar-event"></i> <span>Pick a date and slot to check availability.</span>
                    </div>
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
                        <label for="numGuests" class="form-label">Number of Guests</label>
                        <select name="num_guests" id="numGuests"
                            class="form-select {{ $errors->has('num_guests') ? 'is-invalid' : '' }}" required>
                            @for ($g = 1; $g <= ($availableProperties->first()->max_capacity ?? 1); $g++)
                                <option value="{{ $g }}"
                                    {{ (int) old('num_guests', 1) === $g ? 'selected' : '' }}>
                                    {{ $g }} guest{{ $g > 1 ? 's' : '' }}
                                </option>
                            @endfor
                        </select>
                        @error('num_guests')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="f_special_requests" class="form-label">Special Requests <span class="optional-tag">(optional)</span></label>
                        <input id="f_special_requests" type="text" name="special_requests" class="form-control"
                            value="{{ old('special_requests') }}" placeholder="Early check-in, extra bed, etc.">
                    </div>
                </div>

                {{-- Price Preview --}}
                <div class="price-preview" id="pricePreview" style="display:none;">
                    <div class="text-muted-theme section-label" style="font-size: 14px;margin-bottom:10px;">Price
                        Breakdown
                    </div>
                    <div id="nightBreakdown"></div>
                    <div class="price-row total">
                        <span>Total Amount</span>
                        <span id="totalAmount">₱0.00</span>
                    </div>
                </div>
            </div>
        </div>

        </div>{{-- /.walkin-main --}}

        <div class="walkin-side">

        {{-- Payment --}}
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon tag-amber"><i class="bi bi-cash-stack"></i></div>
                <h3>Payment <span class="optional-tag" style="font-size:13px;font-family: var(--font-body);">(optional
                        — can be recorded later)</span></h3>
            </div>
            <div class="section-body">
                <div class="two-col mb-14">
                    <div>
                        <label for="paymentAmount" class="form-label">Amount Received</label>
                        <input type="number" name="payment_amount" id="paymentAmount"
                            class="form-control {{ $errors->has('payment_amount') ? 'is-invalid' : '' }}"
                            value="{{ old('payment_amount', 0) }}" min="0" step="0.01" placeholder="0.00"
                            oninput="updatePaymentPreview()">
                        @error('payment_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="f_payment_method" class="form-label">Payment Method</label>
                        <select id="f_payment_method" name="payment_method"
                            class="form-select {{ $errors->has('payment_method') ? 'is-invalid' : '' }}">
                            <option value="">Select</option>
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
                    <div class="form-control text-muted-theme" id="paymentTypeDisplay" style="background:var(--cream);">
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
        <div class="walkin-actions">
            <a href="{{ route('staff.frontdesk') }}" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Frontdesk
            </a>
            <button type="submit" class="btn-submit">
                <i class="bi bi-calendar-check"></i> Create Walk-in Booking
            </button>
        </div>

        </div>{{-- /.walkin-side --}}
        </div>{{-- /.walkin-grid --}}
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

            // Nagbabago ang presyo kapag nagpalit sa pagitan ng umiiral na
            // guest (na baka regular na customer) at bagong guest (na hindi
            // puwedeng maging regular). Nasa guard ito dahil tinatawag din
            // ang setGuestType() bago pa madepinisyon ang updatePrice().
            if (typeof updatePrice === 'function') updatePrice();
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
        // null = hindi pa nasuri / hindi nasuri (network error) — pinapayagan
        // ang submit kung null, dahil reserveSlot() ang tunay na check.
        let slotAvailable = null;
        let overpaid = false;

        // Iisang lugar na nagpapasya sa submit button — dating dalawang
        // function ang parehong nag-toggle nito, kaya ang isang ay kayang
        // i-enable muli ang button na idinisable ng isa.
        function syncSubmit() {
            const submitBtn = document.querySelector('.btn-submit');
            if (submitBtn) submitBtn.disabled = overpaid || slotAvailable === false;
        }

        function setAvailability(state, icon, text) {
            const el = document.getElementById('availabilityStatus');
            el.className = 'availability-status' + (state ? ' is-' + state : '');
            el.replaceChildren();
            const i = document.createElement('i');
            i.className = 'bi ' + icon;
            const span = document.createElement('span');
            span.textContent = text;
            el.append(i, ' ', span);
        }

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

            if (quoteAbortController) quoteAbortController.abort();
            quoteAbortController = new AbortController();

            // Naka-disable habang sinusuri, para hindi mai-submit ang isang
            // slot na hindi pa nakumpirmang bukas.
            slotAvailable = false;
            syncSubmit();
            setAvailability('', 'bi-hourglass-split', 'Checking availability…');

            const params = new URLSearchParams({
                property_id: PROPERTY_ID,
                checkin: ci,
                slot: slotInput.value
            });

            // Ang presyo ay nakadepende rin sa KUNG SINO: may mga promong
            // para lang sa mga regular na customer. Ipinapasa lang ito
            // kapag umiiral nang guest ang pinili — ang isang BAGONG guest
            // ay walang natapos pang stay, kaya list price talaga ang tama.
            const guestTypeEl = document.getElementById('guestType');
            const guestEl = document.getElementById('guestSelect');
            if (guestTypeEl && guestTypeEl.value === 'existing' && guestEl && guestEl.value) {
                params.set('user_id', guestEl.value);
            }

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
                        setAvailability('unavailable', 'bi-exclamation-circle', 'Select a valid date and slot.');
                        return;
                    }
                    slotAvailable = !!data.available;
                    setAvailability(
                        data.available ? 'available' : 'unavailable',
                        data.available ? 'bi-check-circle-fill' : 'bi-x-circle-fill',
                        data.message
                    );
                    renderQuote(data);
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    // Hindi puwedeng manghula ng presyo kapag hindi umabot
                    // ang server — mas mabuting walang ipakita kaysa sa
                    // isang numerong baka mali.
                    preview.style.display = 'none';
                    calculatedTotal = 0;
                    slotAvailable = null;
                    setAvailability('', 'bi-wifi-off', 'Couldn’t check availability. It will be verified on submit.');
                    syncSubmit();
                });
        }

        function renderQuote(data) {
            const preview = document.getElementById('pricePreview');

            // Ang `promo_scope` ay naroon lang kapag ang bawas ay dahil
            // REGULAR ang customer — kailangang masabi ito ni staff nang
            // tama sa guest, dahil hindi ito isang promong para sa lahat.
            const scopeNote = data.promo_scope ? ` — ${data.promo_scope}` : '';
            const promoRow = data.discount > 0 ? `
    <div class="price-row" style="color:#15803d;font-weight:600;">
        <span><i class="bi bi-tag-fill fs-13"></i> ${data.promo_label} (${data.promo_value})${scopeNote}</span>
        <span>−${peso(data.discount)}</span>
    </div>` : '';

            preview.innerHTML =
                `<div class="text-muted-theme section-label" style="font-size: 14px;margin-bottom:10px;">Price Breakdown</div>
    <div id="nightBreakdown">
    <div class="price-row">
        <span class="text-muted-theme">${data.day_label} check-in${data.is_peak ? ' <span style="color:#b8943f;">★</span>' : ''}</span>
        <span>${peso(data.base)}</span>
    </div>
    <div class="price-row text-muted-theme fs-13">
        <span>Flat package rate (${data.hours.toFixed(1)} hrs)</span>
    </div>
    ${promoRow}
    </div>
    <div class="price-row total">
        <span>Total Amount</span>
        <span id="totalAmount">${peso(data.total)}</span>
    </div>`;

            calculatedTotal = data.total;
            preview.style.display = 'block';

            updatePaymentPreview();
        }

        function updatePaymentPreview() {
            const paidInput = document.getElementById('paymentAmount');
            const paid = parseFloat(paidInput.value) || 0;
            const summary = document.getElementById('paymentSummary');
            const typeDisplay = document.getElementById('paymentTypeDisplay');
            const typeHidden = document.getElementById('paymentTypeHidden');

            // Overpayment guard (dapat tumugma sa backend validation) — hindi
            // puwedeng lumagpas ang natanggap na bayad sa kabuuang halaga.
            overpaid = calculatedTotal > 0 && paid > calculatedTotal;
            syncSubmit();
            if (overpaid) {
                paidInput.classList.add('is-invalid');
                typeDisplay.textContent =
                    `Exceeds the total (₱${calculatedTotal.toLocaleString('en-PH',{minimumFractionDigits:2})}). If there is change, just type the net amount received..`;
                typeDisplay.style.color = '#dc2626';
                summary.style.display = 'none';
                return;
            }
            paidInput.classList.remove('is-invalid');

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
                typeDisplay.textContent = 'Full Payment';
                typeDisplay.style.color = '#16a34a';
            } else {
                typeHidden.value = 'partial';
                typeDisplay.textContent = 'Partial Payment';
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

        // ── Aling slot ang inaalok sa piniling petsa ──────────────────
        //
        // Kaparehong panuntunan ng Booking::slotsOfferedOn(): window muna at
        // EKSKLUSIBO, at kung wala, ang mga hindi-windowed na slot. Dapat
        // eksaktong tumugma sa server — kung hindi, makakapili si staff ng
        // slot na tatanggihan ng SlotOfferedOnDate sa pag-submit.
        const WINDOWED_SLOTS = @json(\App\Models\Booking::windowedSlotKeys());
        const SLOT_WINDOW_DATES = @json(\App\Models\Booking::slotWindowDates());
        const SLOT_NAMES = @json(collect(\App\Models\Booking::SLOTS)->map(fn ($d) => $d['name'])->all());
        const ALL_SLOT_KEYS = @json(\App\Models\Booking::bookableSlotKeys());

        function slotsOfferedOn(dateStr) {
            for (const s of WINDOWED_SLOTS) {
                if ((SLOT_WINDOW_DATES[s] || []).includes(dateStr)) return [s];
            }
            return ALL_SLOT_KEYS.filter(k => !WINDOWED_SLOTS.includes(k));
        }

        function refreshSlotOptions() {
            const dateStr = document.getElementById('checkinDate').value;
            if (!dateStr) return;

            const offered = slotsOfferedOn(dateStr);
            let keptChecked = false;

            ALL_SLOT_KEYS.forEach(function (key) {
                const input = document.getElementById('slot_' + key);
                if (!input) return;
                const wrap = input.closest('.form-check');
                const on = offered.includes(key);

                if (wrap) wrap.hidden = !on;
                input.disabled = !on;
                if (input.checked && on) keptChecked = true;
                if (input.checked && !on) input.checked = false;
            });

            if (!keptChecked && offered.length) {
                const first = document.getElementById('slot_' + offered[0]);
                if (first) first.checked = true;
            }

            // Sabihin kay staff kung bakit nawala ang Day/Night.
            const note = document.getElementById('slotOnlyNote');
            if (note) {
                const only = offered.length === 1 && WINDOWED_SLOTS.includes(offered[0]);
                note.hidden = !only;
                if (only) {
                    note.textContent = 'This date is set up as a ' +
                        (SLOT_NAMES[offered[0]] || offered[0]) +
                        ' date, so the Day and Night slots are not offered on it.';
                }
            }
        }

        document.getElementById('checkinDate').addEventListener('change', function () {
            refreshSlotOptions();
            updatePrice();
        });
        document.querySelectorAll('input[name="slot"]').forEach(el => el.addEventListener('change', updatePrice));
        // Ang pagpili ng ibang guest ay maaaring magpalit ng presyo.
        document.getElementById('guestSelect')?.addEventListener('change', updatePrice);

        // Run on load — may default check-in date + slot na naka-preset,
        // para agad makita ni staff ang price preview.
        refreshSlotOptions();
        updatePrice();
    </script>
@endpush

