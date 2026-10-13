@extends('layouts.staff')

@section('title', 'Walk-in Booking — Villa Elena Staff')
@section('page-title', 'Walk-in Booking')
@section('page-subtitle', 'Create a new booking for a walk-in guest')

{{-- This page's stylesheet: its own Vite entry, linked here and nowhere
     else, after the staff bundle (see the note at the top of
     resources/css/staff/walkin.css). --}}
@push('styles')
    @vite(['resources/css/staff/walkin.css'])
@endpush

@section('content')

    @include('staff.partials.flash')

    <form method="POST" action="{{ route('staff.walkin.store') }}" id="walkinForm" data-submit-once>
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
                <div class="guest-toggle" role="group" aria-label="Guest type">
                    <button type="button" class="toggle-btn active" id="btnExisting" aria-pressed="true" data-guest-type="existing">
                        <i class="bi bi-person-check me-1"></i> Existing Guest
                    </button>
                    <button type="button" class="toggle-btn" id="btnNew" aria-pressed="false" data-guest-type="new">
                        <i class="bi bi-person-plus me-1"></i> New Guest
                    </button>
                </div>
                <input type="hidden" name="guest_type" id="guestType" value="existing">

                {{-- Existing Guest --}}
                <div id="existingGuestFields">
                    <label for="guestSelect" class="form-label">Select Guest</label>
                    {{-- Narrows the list below as it is typed in. The list is
                         every customer on record, and a plain dropdown of them
                         has no search of its own. `data-search` carries the
                         phone number too, which the option's text may not
                         show. The script is in this file (filterGuests). --}}
                    <input type="search" id="guestSearch" class="form-control guest-search" autocomplete="off"
                        placeholder="Type a name, email or phone to narrow the list" aria-label="Search guests"
                        aria-controls="guestSelect" aria-describedby="guestSearchCount">
                    {{-- May id dahil ginagamit ng live quote: nakakaapekto sa
                         presyo kung sino ang guest (promo para sa regular). --}}
                    <select name="user_id" id="guestSelect"
                        class="form-select {{ $errors->has('user_id') ? 'is-invalid' : '' }}">
                        <option value="">Select existing guest</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('user_id') == $customer->id ? 'selected' : '' }}
                                data-search="{{ mb_strtolower($customer->full_name . ' ' . $customer->email . ' ' . $customer->phone) }}">
                                {{ $customer->full_name }} —
                                {{ $customer->email ?? ($customer->phone ?? 'No email or phone number on record.') }}
                            </option>
                        @endforeach
                    </select>
                    <div class="guest-count" id="guestSearchCount" role="status"></div>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- New Guest --}}
                <div id="newGuestFields" hidden>
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
                        <div class="form-label" id="createAccountLabel">Create a login account for this guest?</div>
                        <div class="guest-toggle mb-0" role="group" aria-labelledby="createAccountLabel">
                            <button type="button" class="toggle-btn" id="btnNoAccount" aria-pressed="false" data-create-account="no">
                                <i class="bi bi-person-lines-fill me-1"></i> No — Guest Record Only
                            </button>
                            <button type="button" class="toggle-btn" id="btnYesAccount" aria-pressed="false" data-create-account="yes">
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
                    <div class="new-guest-password-note" id="yesAccountNote" hidden>
                        <i class="bi bi-info-circle me-1"></i>
                        A login account will be created, and the guest will be emailed a link to set their own
                        password.
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
                    <div class="form-label" id="villaLabel">Villa</div>
                    @if ($availableProperties->count() > 0)
                        @php($villa = $availableProperties->first())
                        <input type="hidden" name="property_id" value="{{ old('property_id', $villa->id) }}">
                        {{-- A fixed value, not a field: there is one villa. Its
                             caption above is a plain element tied to it by
                             aria-labelledby, since a label needs a control. --}}
                        <div class="form-control villa-fixed" role="group" aria-labelledby="villaLabel"
                            data-base="{{ $villa->base_price }}"
                            data-weekend="{{ $villa->weekend_price ?? $villa->base_price }}"
                            data-max="{{ $villa->max_capacity }}" id="propertySelect">
                            <span><i class="bi bi-house-heart-fill me-2 text-gold"
                                   ></i>{{ $villa->property_name }}</span>
                            <span class="text-muted-theme fs-14">Max {{ $villa->max_capacity }}
                                guests</span>
                        </div>
                    @else
                        <div class="alert alert-danger">The villa is not open for booking right now.</div>
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
                    <div class="form-label" id="slotLabel">Slot</div>
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
                    <div class="slot-options" role="radiogroup" aria-labelledby="slotLabel">
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
                    <div id="slotOnlyNote" class="form-text slot-only-note" hidden></div>
                    @error('slot')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div id="availabilityStatus" class="availability-status" role="status" aria-live="polite">
                        <i class="bi bi-calendar-event"></i> <span>Pick a date and slot to check availability.</span>
                    </div>
                    @if (($prefill['date'] ?? null) && !old('check_in_date'))
                        <div class="prefill-note">
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
                <div class="price-preview" id="pricePreview" hidden>
                    <div class="text-muted-theme section-label price-title">Price
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
                <h3>Payment <span class="optional-tag">(optional
                        — can be recorded later)</span></h3>
            </div>
            <div class="section-body">
                <div class="two-col mb-14">
                    <div>
                        <label for="paymentAmount" class="form-label">Amount Received</label>
                        <input type="number" name="payment_amount" id="paymentAmount"
                            class="form-control {{ $errors->has('payment_amount') ? 'is-invalid' : '' }}"
                            value="{{ old('payment_amount') }}" min="0" step="0.01" placeholder="0.00"
                            inputmode="decimal">
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
                    <div class="form-label" id="paymentTypeLabel">Payment Type</div>
                    <input type="hidden" name="payment_type" id="paymentTypeHidden"
                        value="{{ old('payment_type', 'partial') }}">
                    {{-- Plain text, not a boxed field: it is worked out from
                         the amount, and in a field's box it read as something
                         to type into. --}}
                    <div class="pay-type" id="paymentTypeDisplay" role="status" aria-labelledby="paymentTypeLabel">
                        No payment received yet
                    </div>
                </div>

                {{-- Payment summary --}}
                <div id="paymentSummary" class="price-preview" hidden>
                    <div class="price-row total"><span>Total</span><span id="psTotalAmount">₱0.00</span></div>
                    <div class="price-row paid"><span>Amount Paid</span><span id="psPaidAmount">₱0.00</span></div>
                    <div class="price-row balance"><span>Balance Due</span><span id="psBalanceAmount">₱0.00</span></div>
                </div>
            </div>
        </div>

        {{-- Why the button below is disabled, beside the button. The slot's
             status line is in the other card, which on a phone is some 680px
             above this one. Filled by syncSubmit(). --}}
        <div class="submit-reason" id="submitReason" role="status" hidden></div>

        {{-- Actions --}}
        <div class="walkin-actions">
            <a href="{{ route('staff.frontdesk') }}" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Frontdesk
            </a>
            <button type="submit" class="btn-submit" data-busy-label="Creating booking…">
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
            // Shown and hidden with the `hidden` attribute, here and for
            // every other part of this form that comes and goes.
            document.getElementById('existingGuestFields').hidden = type !== 'existing';
            document.getElementById('newGuestFields').hidden = type !== 'new';
            document.getElementById('btnExisting').classList.toggle('active', type === 'existing');
            document.getElementById('btnNew').classList.toggle('active', type === 'new');
            // The dark fill is the only sign of which one is chosen; say it too.
            document.getElementById('btnExisting').setAttribute('aria-pressed', type === 'existing');
            document.getElementById('btnNew').setAttribute('aria-pressed', type === 'new');

            // Nagbabago ang presyo kapag nagpalit sa pagitan ng umiiral na
            // guest (na baka regular na customer) at bagong guest (na hindi
            // puwedeng maging regular).
            updatePrice();
        }

        // The two pairs of toggle buttons name their choice in a data
        // attribute.
        document.querySelectorAll('[data-guest-type]').forEach(function(button) {
            button.addEventListener('click', function() {
                setGuestType(button.dataset.guestType);
            });
        });
        document.querySelectorAll('[data-create-account]').forEach(function(button) {
            button.addEventListener('click', function() {
                setCreateAccount(button.dataset.createAccount);
            });
        });

        // ── Create-account toggle (bagong guest lang) ───────────────────
        function setCreateAccount(choice) {
            document.getElementById('createAccount').value = choice;
            document.getElementById('btnNoAccount').classList.toggle('active', choice === 'no');
            document.getElementById('btnYesAccount').classList.toggle('active', choice === 'yes');
            document.getElementById('btnNoAccount').setAttribute('aria-pressed', choice === 'no');
            document.getElementById('btnYesAccount').setAttribute('aria-pressed', choice === 'yes');

            const emailInput = document.getElementById('newGuestEmail');
            const emailTag = document.getElementById('emailOptionalTag');
            const noNote = document.getElementById('noAccountNote');
            const yesNote = document.getElementById('yesAccountNote');

            emailInput.required = choice === 'yes';
            emailTag.textContent = choice === 'yes' ? '(required)' : '(optional)';
            noNote.hidden = choice === 'yes';
            yesNote.hidden = choice !== 'yes';
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
        // It also says WHY, beside the button: the two reasons live in two
        // other cards, and a disabled button with no reason in sight reads
        // as a broken one.
        let availabilityText = '';

        function syncSubmit() {
            const submitBtn = document.querySelector('.btn-submit');
            if (submitBtn) submitBtn.disabled = overpaid || slotAvailable === false;

            const reason = document.getElementById('submitReason');
            if (!reason) return;
            const text = overpaid
                ? 'The amount received is more than the total.'
                : (slotAvailable === false ? availabilityText : '');
            reason.textContent = text;
            reason.hidden = !text;
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

            // The same sentence is the button's reason while the slot is
            // not confirmed open.
            availabilityText = text;
            syncSubmit();
        }

        // ── Guest search ───────────────────────────────────────────────
        // Narrows the Select Guest list to the options whose name, email or
        // phone contains what was typed. The options are taken out of the
        // list and put back, not hidden: a hidden <option> is still shown
        // by some phone browsers. One match is selected for the staff
        // member; none says so and points at New Guest.
        (function() {
            const search = document.getElementById('guestSearch');
            const select = document.getElementById('guestSelect');
            const count = document.getElementById('guestSearchCount');
            if (!search || !select) return;

            const everyone = Array.from(select.options).filter(o => o.value !== '');

            function filterGuests() {
                const term = search.value.trim().toLowerCase();
                const chosen = select.value;
                const matches = term ? everyone.filter(o => (o.dataset.search || '').includes(term)) : everyone;

                everyone.forEach(o => o.remove());
                matches.forEach(o => select.append(o));

                if (term && matches.length === 1) {
                    select.value = matches[0].value;
                } else {
                    select.value = matches.some(o => o.value === chosen) ? chosen : '';
                }

                if (!term) {
                    count.textContent = '';
                } else if (!matches.length) {
                    count.textContent = 'No guest matches. Check the spelling, or choose New Guest above.';
                } else if (matches.length === 1) {
                    count.textContent = '1 guest matches, and is selected.';
                } else {
                    count.textContent = matches.length + ' of ' + everyone.length + ' guests match. Pick one from the list.';
                }

                // Who the guest is can change the price.
                if (select.value !== chosen && typeof updatePrice === 'function') updatePrice();
            }

            search.addEventListener('input', filterGuests);
        })();

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
                preview.hidden = true;
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
                        preview.hidden = true;
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
                    preview.hidden = true;
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
    <div class="price-row promo">
        <span><i class="bi bi-tag-fill fs-13"></i> ${data.promo_label} (${data.promo_value})${scopeNote}</span>
        <span>−${peso(data.discount)}</span>
    </div>` : '';

            preview.innerHTML =
                `<div class="text-muted-theme section-label price-title">Price Breakdown</div>
    <div id="nightBreakdown">
    <div class="price-row">
        <span class="text-muted-theme">${data.day_label} check-in${data.is_peak ? ' · <span class="peak-rate">peak rate</span>' : ''}</span>
        <span>${peso(data.base)}</span>
    </div>
    <div class="price-row text-muted-theme fs-13">
        <span>Flat package rate (${Number(data.hours.toFixed(1))} hrs)</span>
    </div>
    ${promoRow}
    </div>
    <div class="price-row total">
        <span>Total Amount</span>
        <span id="totalAmount">${peso(data.total)}</span>
    </div>`;

            calculatedTotal = data.total;
            preview.hidden = false;

            updatePaymentPreview();
        }

        // The colour of the payment-type line: `.pay-type.is-over`, `.is-full`
        // or `.is-partial` in css/staff/walkin.css, or none for the plain one.
        function setPayTone(el, tone) {
            el.className = 'pay-type' + (tone ? ' is-' + tone : '');
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
                    `More than the total (₱${calculatedTotal.toLocaleString('en-PH',{minimumFractionDigits:2})}). If you gave change, enter only what the resort kept.`;
                setPayTone(typeDisplay, 'over');
                summary.hidden = true;
                return;
            }
            paidInput.classList.remove('is-invalid');

            if (paid <= 0 || calculatedTotal <= 0) {
                summary.hidden = true;
                typeDisplay.textContent = 'No payment received yet';
                setPayTone(typeDisplay, '');
                typeHidden.value = 'partial';
                return;
            }

            // Auto payment type: buo ang bayad = full_payment, hindi buo = partial.
            // Kinukuha ang desisyon dito, hindi na kailangang manual pumili si
            // staff — iwas human error.
            if (paid >= calculatedTotal) {
                typeHidden.value = 'full_payment';
                typeDisplay.textContent = 'Full Payment';
                setPayTone(typeDisplay, 'full');
            } else {
                typeHidden.value = 'partial';
                typeDisplay.textContent = 'Partial Payment';
                setPayTone(typeDisplay, 'partial');
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
            summary.hidden = false;
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
        document.getElementById('paymentAmount').addEventListener('input', updatePaymentPreview);
        // Ang pagpili ng ibang guest ay maaaring magpalit ng presyo.
        document.getElementById('guestSelect')?.addEventListener('change', updatePrice);

        // Run on load — may default check-in date + slot na naka-preset,
        // para agad makita ni staff ang price preview.
        refreshSlotOptions();

        // After a failed submit with New Guest chosen, show that form again.
        // Last in the script on purpose: setGuestType() ends in
        // updatePrice(), which reads constants declared above this line.
        // Called from the top of the script it threw on the first of them
        // and nothing below it ran, so the reloaded form had no price, no
        // availability check, and Create Account went back to "No".
        @if (old('guest_type') === 'new')
            setGuestType('new');
        @else
            updatePrice();
        @endif
    </script>
@endpush

