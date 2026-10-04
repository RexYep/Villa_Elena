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

        /* 13px, not 11.5px — this was the smallest text in the admin panel
           (labels and hints elsewhere are 13px), and it is the text explaining
           what each switch actually does, including the one that sends a
           notification to every customer account. */
        .check-row .check-hint {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
            margin-top: 2px;
        }

        /* admin.css collapses every .two-col below 900px. This form is capped at
           760px and has no sidebar competing for width, so at 768px the wrapper
           is 713px wide and the pairs still stacked into one 663px column —
           the collapse buys nothing there.

           561px matches the booking and property forms, and is comfortably above
           what this one needs: the binding constraint is the native date input,
           whose intrinsic minimum is 157px, so a column has to clear ~165px.
           At 561px the columns are 234px. Below it single column is genuinely
           better here anyway — at 320px a forced pair gives 120px cells, and
           this form's per-field hints run three or four lines in a column that
           narrow. */
        @media (min-width: 561px) {
            .form-wrapper .two-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Label and prices share a row, but on a phone the price block wins:
           at 320px "Regular rate (Mon–Thu, Sun after 6PM)" was squeezed to 62px
           against a 139px price and wrapped into a 125px-tall column. */
        @media (max-width: 480px) {
            .preview-row {
                flex-direction: column;
                gap: 2px;
            }
        }

        /* The checkbox is 17px. Two of these decide whether the promo is live
           and whether it is advertised publicly, and the third sends a one-time
           notification to every customer — worth a finger-sized target. Keyed on
           pointer type so the desktop form keeps its compact controls. */
        @media (hover: none) and (pointer: coarse) {
            .check-row {
                padding: 14px 0;
            }

            .check-row input[type="checkbox"] {
                width: 22px;
                height: 22px;
                margin-top: 0;
            }

            .check-row .check-label {
                display: inline-block;
                min-height: 24px;
            }
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
        <form method="POST" id="promoForm"
            action="{{ $isEdit ? route('admin.promotions.update', $promo) : route('admin.promotions.store') }}">
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
                        <label for="f_label" class="form-label">Promo name <span class="req">*</span></label>
                        <input id="f_label" type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                            value="{{ old('label', $promo->label) }}" placeholder="e.g. Rainy Season Special" required>
                        <span class="hint">This is the headline guests see on the landing page and in their notification.</span>
                        @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        {{-- Lumalabas lang ito kapag naharang ng
                             Discount::duplicateProblem(). Hindi ito laging
                             nakikita: ang isang nakatikang kahon ay walang
                             pinipigilan, at dahil `old()` ang pinagmumulan,
                             nalilimas ito sa bawat bagong form. --}}
                        @if (! $isEdit && $errors->has('label'))
                            <div class="check-row" style="margin-top:10px;">
                                <input type="checkbox" name="confirm_duplicate" id="confirmDuplicate" value="1">
                                <div>
                                    <label class="check-label" for="confirmDuplicate">Create it anyway</label>
                                    <div class="check-hint">
                                        Only tick this if you really do want a second promo with the same name and value.
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="f_description" class="form-label">Short description</label>
                        <input id="f_description" type="text" name="description" class="form-control @error('description') is-invalid @enderror"
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
                            <label for="promoType" class="form-label">Type <span class="req">*</span></label>
                            <select name="type" id="promoType" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="percentage" {{ old('type', $promo->type) === 'percentage' ? 'selected' : '' }}>Percentage off</option>
                                <option value="fixed" {{ old('type', $promo->type) === 'fixed' ? 'selected' : '' }}>Fixed peso amount off</option>
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="promoValue" class="form-label">Value <span class="req">*</span></label>
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
                        <div class="hint mt-8">
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
                            <label for="f_start_date" class="form-label">Starts</label>
                            <input id="f_start_date" type="date" name="start_date" min="{{ $startMin }}"
                                class="form-control @error('start_date') is-invalid @enderror"
                                value="{{ old('start_date', $promo->start_date?->format('Y-m-d')) }}">
                            <span class="hint">Leave blank to start immediately. Can't be set to a past date.</span>
                            @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="f_expiry_date" class="form-label">Ends</label>
                            <input id="f_expiry_date" type="date" name="expiry_date" min="{{ $today }}"
                                class="form-control @error('expiry_date') is-invalid @enderror"
                                value="{{ old('expiry_date', $promo->expiry_date?->format('Y-m-d')) }}">
                            <span class="hint">Inclusive — a promo ending Aug 31 still covers an Aug 31 check-in. Blank = no end date.</span>
                            @error('expiry_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="two-col">
                        <div>
                            <label for="f_applies_to" class="form-label">Slot <span class="req">*</span></label>
                            <select id="f_applies_to" name="applies_to" class="form-select @error('applies_to') is-invalid @enderror" required>
                                {{-- Hinahango sa Booking::SLOTS: ang isang bagong slot ay lumilitaw
                                     dito nang mag-isa. Kasama ang mga slot na wala pang presyo —
                                     ang isang promo ay maaaring ihanda nang mas maaga pa sa
                                     paglulunsad ng slot, at ang applies_to ay walang kinalaman sa
                                     kung ano ang bookable ngayon. --}}
                                <option value="all" {{ old('applies_to', $promo->applies_to) === 'all' ? 'selected' : '' }}>All slots</option>
                                @foreach (\App\Models\Booking::SLOTS as $slotKey => $slotDef)
                                    <option value="{{ $slotKey }}" {{ old('applies_to', $promo->applies_to) === $slotKey ? 'selected' : '' }}>{{ $slotDef['label'] }} only</option>
                                @endforeach
                            </select>
                            @error('applies_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="f_usage_limit" class="form-label">Booking limit</label>
                            <input id="f_usage_limit" type="number" min="1" name="usage_limit"
                                class="form-control @error('usage_limit') is-invalid @enderror"
                                value="{{ old('usage_limit', $promo->usage_limit) }}" placeholder="Unlimited">
                            <span class="hint">
                                Stop applying after this many bookings.
                                @if ($isEdit) Used so far: <strong>{{ $promo->used_count }}</strong>. @endif
                            </span>
                            @error('usage_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- ── Sino ang makakakuha ──────────────────────────
                         Pangalawang aksis ng pagiging karapat-dapat, hiwalay
                         sa petsa at sa slot: ang diskuwento ng may-ari sa mga
                         REGULAR na customer. --}}
                    <div class="two-col" style="margin-top:14px;">
                        <div>
                            <label for="guestScope" class="form-label">Who gets this <span class="req">*</span></label>
                            <select name="guest_scope" id="guestScope"
                                class="form-select @error('guest_scope') is-invalid @enderror" required>
                                <option value="all"
                                    {{ old('guest_scope', $promo->guest_scope ?? 'all') === 'all' ? 'selected' : '' }}>
                                    All guests</option>
                                <option value="returning"
                                    {{ old('guest_scope', $promo->guest_scope ?? 'all') === 'returning' ? 'selected' : '' }}>
                                    Returning guests only</option>
                            </select>
                            @error('guest_scope') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div id="minStaysWrap">
                            <label for="minStays" class="form-label">Completed stays needed</label>
                            <input type="number" min="1" max="50" name="min_completed_bookings" id="minStays"
                                class="form-control @error('min_completed_bookings') is-invalid @enderror"
                                value="{{ old('min_completed_bookings', $promo->min_completed_bookings ?? 1) }}">
                            <span class="hint">
                                1 = anyone who has stayed here before. Only <strong>checked-out</strong> stays count.
                            </span>
                            @error('min_completed_bookings') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <span class="hint mt-12">
                        A <strong>Returning guests only</strong> promo is matched against the guest's completed stays —
                        bookings that reached <strong>checked out</strong>. Pending, confirmed, cancelled and
                        no-show bookings never count, so nobody can qualify by booking dates they don't pay for.
                        It is only applied once the guest is <strong>signed in</strong>; the landing page shows a
                        sign-in prompt instead of the amount, and walk-ins get it when the front desk picks the
                        existing guest rather than creating a new one.
                    </span>

                    <span class="hint mt-12">
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
                                    Sent to customers {{ $promo->notified_at->diffForHumans() }}. Fixing the name or the
                                    description won't notify anyone again. But if you change <strong>the discount, the
                                    dates, the slot, or who gets it</strong> — or switch the promo off — the guests who
                                    were told are sent a short update automatically, because the notification they
                                    already have would otherwise be promising the old terms.
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="check-row">
                            <input type="checkbox" name="notify_customers" id="notifyCustomers" value="1"
                                {{ old('notify_customers') ? 'checked' : '' }}>
                            <div>
                                {{-- Ang bilang ay ina-update ng JS habang nagbabago ang
                                     "Who gets this" at ang threshold, dahil IYON ang
                                     nagtatakda ng audience. Ang dating label ("all N
                                     customers") ay nananatiling tama lang para sa isang
                                     promong para sa lahat. --}}
                                <label class="check-label" for="notifyCustomers">
                                    Announce to <span id="notifyAudience">all {{ $customerCount }}
                                        customer{{ $customerCount === 1 ? '' : 's' }}</span>
                                </label>
                                <div class="check-hint">
                                    Sends a one-time in-app notification. No email is sent —
                                    the mail quota is reserved for 2FA codes, booking confirmations and password resets.
                                    <span id="notifyScopeHint"></span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div style="display:flex;align-items:center;">
                <button type="submit" class="btn-submit" id="promoSubmit">
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

            // Ang bilang ng natapos nang stay ay walang kahulugan sa isang
            // promong para sa lahat, kaya itinatago ito doon. Hindi ito
            // ini-disable: kailangang maipasa pa rin ang halaga kapag
            // "returning" ang napili, at ang nakatagong field ay nagbabalik
            // pa rin ng value sa submit.
            const scopeEl = document.getElementById('guestScope');
            const minWrap = document.getElementById('minStaysWrap');
            const minEl = document.getElementById('minStays');

            // Ang audience ng anunsyo ay PAREHO sa audience ng presyo
            // (tingnan ang PromotionController::announcementAudience()).
            // Ang bilang kada threshold ay galing sa server, kaya ang
            // label ay hindi puwedeng mag-iba sa aktwal na padadalhan.
            const ALL_CUSTOMERS = @json($customerCount);
            const RETURNING_COUNTS = @json($returningCounts);
            const audienceEl = document.getElementById('notifyAudience');
            const scopeHintEl = document.getElementById('notifyScopeHint');

            const plural = (n, word) => n + ' ' + word + (n === 1 ? '' : 's');

            function renderAudience() {
                if (! audienceEl) return;

                if (scopeEl.value !== 'returning') {
                    audienceEl.textContent = 'all ' + plural(ALL_CUSTOMERS, 'customer');
                    scopeHintEl.textContent = '';

                    return;
                }

                const min = Math.max(1, parseInt(minEl.value, 10) || 1);
                const n = RETURNING_COUNTS[min] ?? 0;

                audienceEl.textContent = n === 0
                    ? 'nobody yet'
                    : 'the ' + plural(n, 'guest') + ' who qualify';

                scopeHintEl.textContent = n === 0
                    ? ' No guest has ' + min + ' completed stay' + (min === 1 ? '' : 's') +
                      ' yet, so nobody would be notified — lower the requirement, or announce it later.'
                    : ' Only guests with ' + min + '+ completed stays are notified, because they are the only ones' +
                      ' this promo would actually discount.';
            }

            function renderScope() {
                const returning = scopeEl.value === 'returning';
                minWrap.style.display = returning ? '' : 'none';
                renderAudience();
            }

            scopeEl.addEventListener('change', renderScope);
            minEl.addEventListener('input', renderAudience);
            renderScope();

            // ── Isang pindot lang ───────────────────────────────────
            //
            // Ang pag-save ng promo ay maaaring magtagal (nagtatala ito
            // ng abiso kada guest), kaya mukhang nag-hang ang page at
            // napipindot muli — na gumagawa ng DALAWANG promo at
            // dalawang blast sa parehong mga guest (nangyari na ito:
            // promo #28 at #29).
            //
            // Affordance lang ito. Ang tunay na hadlang ay nasa server
            // (Discount::duplicateProblem()), dahil kayang laktawan ang
            // JS pero hindi ang controller — kaparehong dahilan kung
            // bakit ipinapatupad ng server ang `policies_accepted`.
            const formEl = document.getElementById('promoForm');
            const submitEl = document.getElementById('promoSubmit');

            formEl.addEventListener('submit', function () {
                // Nasa timeout dahil ang isang naka-disable na button ay
                // hindi naisasama sa pag-submit sa ilang browser; ipinapa-
                // dala muna ang form, saka isinasara ang pinto.
                window.setTimeout(function () {
                    submitEl.disabled = true;
                    submitEl.innerHTML =
                        '<i class="bi bi-hourglass-split me-1"></i> Saving — notifying guests…';
                }, 0);
            });
        })();
    </script>
@endsection
