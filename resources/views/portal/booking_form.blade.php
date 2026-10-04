@extends('layouts.portal')

@section('title', 'Complete Booking — Villa Elena Resort')

@push('styles')
    <style>
        .main {
            max-width: 960px;
        }

        .field-readonly {
            background: #f9f5ee;
        }

        /* Steps */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 36px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .step.done .step-num {
            background: var(--gold);
            color: var(--stone);
        }

        .step.active .step-num {
            background: var(--stone);
            color: #fff;
        }

        .step.inactive .step-num {
            background: var(--border);
            color: var(--muted);
        }

        .step.active .step-label {
            font-weight: 600;
            color: var(--stone);
        }

        .step.inactive .step-label {
            color: var(--muted);
        }

        .step-line {
            flex: 1;
            height: 1px;
            background: var(--border);
            margin: 0 16px;
        }

        /* Layout */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 28px;
            align-items: start;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .form-card-head {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-card-head .icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .form-card-head h3 {
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 600;
        }

        .form-card-body {
            padding: 24px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--stone);
            margin-bottom: 6px;
            display: block;
            letter-spacing: .3px;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(44, 36, 22, .06);
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .mb-14 {
            margin-bottom: 14px;
        }

        /* Summary card */
        .summary-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            position: sticky;
            /* The spaces around "+" are REQUIRED inside calc(). Without them
               the whole declaration is invalid and the browser drops it, so
               `top` computed to `auto` and this card never actually stuck —
               silently, since invalid CSS reports nothing. Verified with
               CSS.supports('top','calc(72px+20px)') === false. */
            top: calc(var(--nav-h) + 20px);
        }

        .summary-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }

        .summary-img-placeholder {
            width: 100%;
            height: 160px;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--muted);
            opacity: .4;
        }

        .summary-body {
            padding: 20px;
        }

        .summary-name {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .summary-dates {
            font-size: 13px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 7px 0;
            border-bottom: 1px solid #f4efe6;
        }

        .price-row:last-child {
            border-bottom: none;
        }

        .price-row.total {
            font-weight: 700;
            font-size: 16px;
            border-top: 2px solid var(--border);
            padding-top: 12px;
            margin-top: 4px;
        }

        .price-row.promo {
            color: #15803d;
            font-weight: 600;
        }

        .price-row.promo .promo-chip {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .4px;
            padding: 2px 7px;
            border-radius: 999px;
            margin-left: 5px;
            white-space: nowrap;
        }

        .deposit-box {
            background: rgba(196, 103, 58, .07);
            border: 1px solid rgba(196, 103, 58, .2);
            border-radius: 10px;
            padding: 12px;
            margin: 12px 0;
            font-size: 14px;
            color: var(--terracotta);
            text-align: center;
        }

        .deposit-amount {
            font-size: 18px;
            font-weight: 700;
            font-family: var(--font-display);
            display: block;
            margin: 4px 0;
        }

        .night-breakdown {
            max-height: 140px;
            overflow-y: auto;
        }

        .night-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            padding: 4px 0;
            color: var(--muted);
        }

        .night-row.weekend {
            color: var(--gold-text);
        }

        /* Submit */
        .btn-submit {
            background: var(--btn-primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: var(--font-body);
            width: 100%;
            cursor: pointer;
            transition: all .2s;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--btn-primary-hover);
            color: #fff;
        }

        .btn-submit:disabled,
        .btn-submit:disabled:hover {
            background: var(--border);
            color: var(--muted);
            cursor: not-allowed;
        }


        .terms-note {
            font-size: 13px;
            color: var(--muted);
            text-align: center;
            margin-top: 8px;
            line-height: 1.5;
        }

        /* ── Booking policies: open button + modal ──
           DO NOT put the word "consent" back into this class or id. Brave
           ships Easylist-Cookie enabled by default ("Block cookie consent
           notices"), whose cosmetic filters hide elements whose class/id
           contains `consent` — they are aimed at cookie banners, and this
           row looked exactly like one. The result: in Brave the whole row
           vanished, taking the (then) required checkbox with it, so the
           booking could not be completed at all. The same applies to the
           button that replaced it. Chrome showed it fine, which is
           why it read as a responsiveness bug at first. The input's
           name="policies_accepted" is a server contract
           (PortalController::submitBooking) and is unaffected — filters
           match class/id, not name. */
        /* Ang butones na nagbubukas ng booking policies. Ito ang
           pangunahing aksyon ng card hangga't hindi pa pumapayag ang
           guest; pagkatapos ay tahimik na itong "tapos na" at ang
           Proceed to Payment na ang nangunguna. */
        .policy-open {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 16px;
            padding: 14px;
            border: 2px solid var(--btn-primary);
            border-radius: 10px;
            background: var(--btn-primary);
            color: #fff;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color .2s, border-color .2s, color .2s;
        }

        .policy-open:hover {
            background: var(--btn-primary-hover);
            border-color: var(--btn-primary-hover);
        }

        .policy-open.is-done {
            background: var(--tag-green-bg);
            border-color: var(--tag-green-fg);
            color: var(--tag-green-fg);
            font-size: 14px;
            padding: 11px 14px;
        }

        .policy-open.is-invalid {
            border-color: var(--tag-red-fg);
            box-shadow: 0 0 0 3px var(--tag-red-bg);
        }

        .policy-modal {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .policy-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 26px 28px 18px;
            border-bottom: 1px solid var(--border);
        }

        .policy-modal-eyebrow {
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold-text);
            font-weight: 600;
            margin-bottom: 7px;
        }

        .policy-modal-head h2 {
            font-family: var(--font-display);
            font-size: 23px;
            font-weight: 600;
            color: var(--stone);
            margin: 0;
        }

        .policy-modal-close {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border: none;
            border-radius: 50%;
            background: var(--sand);
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background .2s, color .2s;
        }

        .policy-modal-close:hover {
            background: var(--stone);
            color: #fff;
        }

        .policy-modal-body {
            padding: 8px 28px 20px;
        }

        .policy-item {
            display: flex;
            gap: 14px;
            padding: 15px 0;
            border-bottom: 1px dashed var(--border);
        }

        .policy-item:last-child {
            border-bottom: none;
        }

        .policy-item .icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .policy-item h4 {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--stone);
            margin: 0 0 4px;
        }

        .policy-item p {
            font-size: 13px;
            line-height: 1.7;
            color: var(--muted);
            margin: 0;
        }

        .policy-item strong {
            color: var(--stone);
            font-weight: 600;
        }

        .policy-modal-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 18px 28px 22px;
            border-top: 1px solid var(--border);
            background: var(--cream);
        }

        .policy-modal-foot a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 12.5px;
            color: var(--muted);
            text-decoration: none;
        }

        .policy-modal-foot a:hover {
            color: var(--gold-text);
        }

        .policy-agree {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 11px 20px;
            font-family: var(--font-body);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, color .2s;
        }

        .policy-agree:hover {
            background: var(--gold);
            color: var(--stone);
        }

        @media (max-width: 800px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
                margin-bottom: 75px;
            }

        }

        @media (max-width: 480px) {
            .policy-modal-head,
            .policy-modal-body,
            .policy-modal-foot {
                padding-left: 20px;
                padding-right: 20px;
            }

            .policy-modal-foot {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .policy-agree {
                justify-content: center;
            }

            .two-col {
                grid-template-columns: 1fr;
            }

            .steps {
                gap: 0;
            }

            .step-label {
                display: none;
            }

            .step-line {
                margin: 0 8px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="breadcrumb-row">
        <a href="{{ route('home') }}">Home</a> <span>›</span>
        <a href="{{ route('portal.property', $property) }}">{{ $property->property_name }}</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">Complete Booking</span>
    </div>

    {{-- Steps --}}
    <div class="steps">
        <div class="step done">
            <div class="step-num"><i class="bi bi-check"></i></div><span class="step-label">Select Dates</span>
        </div>
        <div class="step-line"></div>
        <div class="step active">
            <div class="step-num">2</div><span class="step-label">Your Details</span>
        </div>
        <div class="step-line"></div>
        <div class="step inactive">
            <div class="step-num">3</div><span class="step-label">Payment</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('portal.book.submit', $property) }}" id="bookingForm">
        @csrf
        <input type="hidden" name="checkin" value="{{ $checkin->toDateString() }}">
        <input type="hidden" name="slot" value="{{ $slot }}">
        <input type="hidden" name="guests" value="{{ $request->guests }}">

        <div class="form-grid">
            <div>
                {{-- Guest Info --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon tag-blue"><i class="bi bi-person"></i></div>
                        <h3>Guest Information</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control field-readonly"
                                    value="{{ Auth::user()->full_name }}" readonly>
                            </div>
                            <div>
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control field-readonly" value="{{ Auth::user()->email }}"
                                    readonly>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control field-readonly"
                                value="{{ Auth::user()->phone ?? '' }}" readonly>
                        </div>
                    </div>
                </div>

                {{-- Stay Details --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon tag-green"><i class="bi bi-calendar3"></i></div>
                        <h3>Stay Details</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Check-in</label>
                                <input type="text" class="form-control field-readonly"
                                    value="{{ $checkin->format('l, M d, Y g:i A') }}" readonly>
                            </div>
                            <div>
                                <label class="form-label">Check-out</label>
                                <input type="text" class="form-control field-readonly"
                                    value="{{ $checkout->format('l, M d, Y g:i A') }}" readonly>
                            </div>
                        </div>
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Slot</label>
                                <input type="text" class="form-control field-readonly"
                                    value="{{ \App\Models\Booking::SLOTS[$slot]['name'] }}" readonly>
                            </div>
                            <div>
                                <label class="form-label">Guests</label>
                                <input type="text" class="form-control field-readonly"
                                    value="{{ $request->guests }} guest{{ $request->guests > 1 ? 's' : '' }}" readonly>
                            </div>
                        </div>
                        <div>
                            <label for="f_special_requests" class="form-label">Special Requests <span class="text-muted-theme fw-400"
                                   >(optional)</span></label>
                            <textarea id="f_special_requests" name="special_requests" class="form-control" rows="3"
                                placeholder="Early check-in, dietary requirements, celebrations, etc.">{{ old('special_requests') }}</textarea>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Summary --}}
            <div>
                <div class="summary-card">
                    @if ($property->primaryImage)
                        <img src="{{ $property->primaryImage->url }}" class="summary-img" alt="">
                    @else
                        <div class="summary-img-placeholder"><i class="bi bi-house"></i></div>
                    @endif
                    <div class="summary-body">
                        <div class="summary-name">{{ $property->property_name }}</div>

                        <div style="height:1px;background:var(--border);margin:12px 0;"></div>

                        {{-- Isang slot = isang presyo. Dating may "night
                             breakdown" na iisang hilera lang, na sinusundan
                             ng parehong halaga bilang "1 night". --}}
                        <div class="price-row">
                            <span class="text-muted-theme">
                                {{ \App\Models\Booking::SLOTS[$slot]['name'] }} rate
                                @if ($nightBreakdown[0]['weekend'] ?? false)
                                    · peak
                                @endif
                            </span>
                            <span>₱{{ number_format($baseAmount, 2) }}</span>
                        </div>
                        @if ($discountAmount > 0)
                            <div class="price-row promo">
                                <span>
                                    <i class="bi bi-tag-fill fs-13"></i>
                                    {{ $promo->label }}
                                    <span class="promo-chip">{{ $promo->value_label }}</span>
                                </span>
                                <span>−₱{{ number_format($discountAmount, 2) }}</span>
                            </div>
                        @endif
                        <div class="price-row total">
                            <span>Total</span>
                            <span>₱{{ number_format($totalAmount, 2) }}</span>
                        </div>

                        <div class="deposit-box">
                            Minimum deposit required ({{ $depositPct }}%)
                            <span class="deposit-amount">₱{{ number_format($depositAmount, 2) }}</span>
                            Payable on the next step
                        </div>

                        {{-- Walang checkbox. Ang tanging daan papunta sa
                             bayad ay ang "I've read and agree" sa LOOB ng
                             popup — kaya hindi na maaaring pumayag ang guest
                             sa mga patakarang hindi lumabas sa screen niya
                             (ang dating checkbox ay natitiktikan nang hindi
                             binubuksan ang popup). Ang butones na iyon ang
                             naglalagay ng `1` sa nakatagong field; ang
                             `accepted` rule sa submitBooking() pa rin ang
                             tunay na harang. --}}
                        <input type="hidden" name="policies_accepted" id="policiesAccepted"
                            value="{{ old('policies_accepted') ? '1' : '' }}">

                        <button type="button"
                            class="policy-open {{ $errors->has('policies_accepted') ? 'is-invalid' : '' }}"
                            id="policyOpen" data-bs-toggle="modal" data-bs-target="#policiesModal">
                            <i class="bi bi-file-text" id="policyOpenIcon" aria-hidden="true"></i>
                            <span id="policyOpenLabel">Read the booking policies</span>
                        </button>
                        @error('policies_accepted')
                            <div class="field-error">{{ $message }}</div>
                        @enderror

                        <button type="submit" class="btn-submit" id="submitBooking"
                            aria-describedby="submitHint">
                            <i class="bi bi-credit-card"></i> Proceed to Payment
                        </button>
                        {{-- Kung bakit naka-disable ang butones. Itinatago ng
                             script sa ibaba kapag pumayag na ang guest. --}}
                        <div class="terms-note" id="submitHint">
                            Read the booking policies to continue.
                        </div>
                        <div class="terms-note">
                            You'll be taken to secure payment via PayMongo next.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- ══════════════════════════════════
     BOOKING POLICIES MODAL
     Ito ang dating "Booking Policies" card sa loob ng form. Nasa labas
     ito ng <form> para walang anumang control nito ang masamang mapasama
     sa isusumite — button lang lahat ng nasa loob, walang input.
══════════════════════════════════ --}}
    <div class="modal fade" id="policiesModal" tabindex="-1" aria-labelledby="policiesModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content policy-modal">
                <div class="policy-modal-head">
                    <div>
                        <div class="policy-modal-eyebrow">Before you pay</div>
                        <h2 id="policiesModalTitle">Booking Policies</h2>
                    </div>
                    <button type="button" class="policy-modal-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Kailangan ang `modal-body` ng Bootstrap dito, hindi lang ang
                     sarili nating klase: ang `modal-dialog-scrollable` sa itaas ay
                     sa `.modal-body` mismo naglalagay ng overflow-y. Kung wala ito,
                     lumalampas ang laman sa `.modal-content` (na naka-overflow:hidden
                     para sa rounded corners) at naputol ang footer — kaya hindi
                     maabot ang "I've read these". --}}
                <div class="modal-body policy-modal-body">
                    <div class="policy-item">
                        <div class="icon tag-amber"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <h4>Deposit required</h4>
                            <p>
                                A minimum <strong>{{ $depositPct }}% deposit
                                    (₱{{ number_format($depositAmount, 2) }})</strong> must be paid via PayMongo to
                                confirm your booking. Your reservation is confirmed automatically once the payment
                                succeeds.
                            </p>
                        </div>
                    </div>

                    {{-- Walang "Check-in time" / "Check-out time" dito: detalye
                         iyon ng booking na ito (nasa Stay Details na ng
                         pahina), hindi patakaran, at pinalalabo lang nila ang
                         tatlong totoong tuntunin. --}}
                    <div class="policy-item">
                        <div class="icon tag-red"><i class="bi bi-x-circle"></i></div>
                        <div>
                            <h4>Cancellation</h4>
                            <p><strong>{{ \App\Models\Booking::CANCELLATION_POLICY }}</strong></p>
                        </div>
                    </div>

                    <div class="policy-item">
                        <div class="icon tag-purple"><i class="bi bi-arrow-repeat"></i></div>
                        <div>
                            <h4>Rescheduling</h4>
                            <p>
                                You may reschedule up to <strong>{{ \App\Models\Booking::MAX_RESCHEDULES }} times</strong>,
                                and only up to <strong>{{ \App\Models\Booking::RESCHEDULE_CUTOFF_DAYS }} days before
                                    check-in</strong>. A higher price is added to your balance; a lower one is
                                not refunded.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="policy-modal-foot">
                    <a href="{{ route('portal.terms') }}" target="_blank" rel="noopener">
                        <i class="bi bi-file-text"></i> Read the full Terms of Service
                    </a>
                    <button type="button" class="policy-agree" id="policyAgree" data-bs-dismiss="modal">
                        <i class="bi bi-check2"></i> I've read and agree
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        /* Hindi maaaring pindutin ang "Proceed to Payment" hangga't hindi
           pumapayag ang guest sa loob ng policies popup.

           Sa JS ginagawa ang pag-disable, hindi sa markup: kung mabigong
           tumakbo ang script na ito, napipindot pa rin ang butones — at
           tatanggihan ng `accepted` rule sa
           PortalController::submitBooking() ang guest na hindi pumayag.
           Ang server ang tunay na nagpapatupad; UX affordance lang ito. */
        (function () {
            const accepted = document.getElementById('policiesAccepted');
            const submit = document.getElementById('submitBooking');
            const open = document.getElementById('policyOpen');
            const openLabel = document.getElementById('policyOpenLabel');
            const openIcon = document.getElementById('policyOpenIcon');
            const agree = document.getElementById('policyAgree');
            const hint = document.getElementById('submitHint');

            if (!accepted || !submit) return;

            function sync() {
                const done = accepted.value === '1';
                submit.disabled = !done;
                if (hint) hint.hidden = done;
                if (!open) return;

                open.classList.toggle('is-done', done);
                if (done) open.classList.remove('is-invalid');
                // Nabubuksan pa rin ang popup pagkatapos, para mabasa ulit.
                if (openLabel) {
                    openLabel.textContent = done ? 'Booking policies read — view again' :
                        'Read the booking policies';
                }
                if (openIcon) {
                    openIcon.className = done ? 'bi bi-check-circle-fill' : 'bi bi-file-text';
                }
            }

            // Ang "I've read and agree" sa popup ang TANGING nagtatakda nito —
            // ang mismong pag-dismiss ay hawak ng data-bs-dismiss, kaya
            // hindi tayo umaasa sa Bootstrap JS API dito (na-load pa lang
            // iyon bilang module pagkatapos ng inline script na ito).
            agree?.addEventListener('click', () => {
                accepted.value = '1';
                sync();
                // Ibalik ang focus sa susunod na hakbang, hindi sa butones
                // na kakatapos lang gamitin.
                setTimeout(() => submit.focus(), 0);
            });

            sync();
        })();
    </script>
@endpush

