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
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 600;
        }

        .form-card-body {
            padding: 24px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
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
            font-family: 'Playfair Display', serif;
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
            font-family: 'Playfair Display', serif;
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
            color: var(--gold);
        }

        /* Submit */
        .btn-submit {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Jost', sans-serif;
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
            background: var(--gold);
            color: var(--stone);
        }

        .btn-submit:disabled,
        .btn-submit:disabled:hover {
            background: var(--border);
            color: var(--muted);
            cursor: not-allowed;
        }

        /* Shown only after the guest actually tries to submit without
           ticking. A disabled button explains nothing — on a phone the tap
           just does nothing at all, with no way to tell why. */
        .policy-required-note {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            margin: 8px 0 2px;
            padding: 9px 11px;
            border-radius: 9px;
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1.45;
        }

        .terms-note {
            font-size: 13px;
            color: var(--muted);
            text-align: center;
            margin-top: 8px;
            line-height: 1.5;
        }

        /* ── Booking policies: consent row + modal ── */
        .policy-consent {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 14px 0 4px;
            padding: 13px 14px;
            background: #ffffff;
            border: 1.5px solid rgba(184, 148, 63, .45);
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(44, 36, 22, .04);
            cursor: pointer;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }

        .policy-consent:hover {
            border-color: var(--gold);
            box-shadow: 0 3px 10px rgba(184, 148, 63, .12);
        }

        .policy-consent.is-checked {
            background: rgba(184, 148, 63, .07);
            border-color: var(--gold);
        }

        .policy-consent.is-invalid {
            border-color: var(--tag-red-fg);
            background: var(--tag-red-bg);
            box-shadow: none;
        }

        .policy-consent input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 1px;
            flex-shrink: 0;
            accent-color: var(--gold);
            cursor: pointer;
        }

        .policy-consent-text {
            font-size: 13px;
            line-height: 1.55;
            color: var(--stone);
            flex: 1;
        }

        .policy-consent-text label {
            cursor: pointer;
            margin: 0;
            font-weight: 500;
        }

        .policy-link {
            background: rgba(184, 148, 63, .16);
            border: none;
            border-bottom: 1.5px solid var(--gold);
            border-radius: 4px;
            padding: 2px 7px;
            font: inherit;
            font-weight: 700;
            color: var(--stone);
            cursor: pointer;
            transition: background .2s, color .2s;
            display: inline-block;
        }

        .policy-link:hover,
        .policy-link:focus-visible {
            background: var(--gold);
            color: #fff;
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
            font-size: 10.5px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 600;
            margin-bottom: 7px;
        }

        .policy-modal-head h2 {
            font-family: 'Playfair Display', serif;
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
            color: var(--gold);
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
            font-family: 'Jost', sans-serif;
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

            .policy-consent {
                padding: 14px 15px;
                gap: 12px;
                margin: 16px 0 6px;
                border: 2px solid rgba(184, 148, 63, .55);
                background: #fdfbf7;
                border-radius: 12px;
            }

            .policy-consent input[type="checkbox"] {
                width: 20px;
                height: 20px;
                margin-top: 1px;
            }

            .policy-consent-text {
                font-size: 13.5px;
            }
        }

        @media (max-width: 480px) {
            .policy-consent {
                padding: 14px 12px;
                gap: 10px;
            }

            .policy-consent input[type="checkbox"] {
                width: 22px;
                height: 22px;
            }

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
                        <div class="icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-calendar3"></i></div>
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
                                <label class="form-label">Duration</label>
                                <input type="text" class="form-control field-readonly"
                                    value="{{ $nights }} night{{ $nights != 1 ? 's' : '' }}" readonly>
                            </div>
                            <div>
                                <label class="form-label">Guests</label>
                                <input type="text" class="form-control field-readonly"
                                    value="{{ $request->guests }} guest{{ $request->guests > 1 ? 's' : '' }}" readonly>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Special Requests <span class="text-muted-theme"
                                    style="font-weight:400;">(optional)</span></label>
                            <textarea name="special_requests" class="form-control" rows="3"
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
                        <div class="summary-dates">
                            <i class="bi bi-calendar3" style="font-size: 13px;"></i>
                            {{ $checkin->format('M d') }} → {{ $checkout->format('M d, Y') }}
                        </div>

                        {{-- Night Breakdown --}}
                        <div class="night-breakdown">
                            @foreach ($nightBreakdown as $night)
                                <div class="night-row {{ $night['weekend'] ? 'weekend' : '' }}">
                                    <span>{{ $night['date'] }} {{ $night['weekend'] ? '★' : '' }}</span>
                                    <span>₱{{ number_format($night['price'], 0) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div style="height:1px;background:var(--border);margin:12px 0;"></div>

                        <div class="price-row">
                            <span class="text-muted-theme">{{ $nights }} night{{ $nights != 1 ? 's' : '' }}</span>
                            <span>₱{{ number_format($baseAmount, 2) }}</span>
                        </div>
                        @if ($discountAmount > 0)
                            <div class="price-row promo">
                                <span>
                                    <i class="bi bi-tag-fill" style="font-size: 13px;"></i>
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

                        {{-- Kailangang basahin muna ang policies bago makapagbayad.
                             Ang "booking policies" ay isang button, hindi label —
                             kung nakabalot ito sa <label>, ang pag-click sa link ay
                             magta-tick din ng checkbox, na siya mismong hindi natin
                             gusto: dapat sadyain ng guest ang pag-tick. --}}
                        <div class="policy-consent {{ $errors->has('policies_accepted') ? 'is-invalid' : '' }}"
                            id="policyConsent">
                            <input type="checkbox" id="policiesAccepted" name="policies_accepted" value="1" required
                                {{ old('policies_accepted') ? 'checked' : '' }}>
                            <span class="policy-consent-text">
                                <label for="policiesAccepted">I have read the</label>
                                <button type="button" class="policy-link" data-bs-toggle="modal"
                                    data-bs-target="#policiesModal">booking policies</button>
                            </span>
                        </div>

                        <div class="policy-required-note" id="policyRequiredNote" hidden>
                            <i class="bi bi-exclamation-circle-fill" style="margin-top:1px;"></i>
                            <span>Please tick the box above to confirm you've read the booking
                                policies — then you can proceed to payment.</span>
                        </div>

                        <button type="submit" class="btn-submit" id="submitBooking">
                            <i class="bi bi-credit-card"></i> Proceed to Payment
                        </button>
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

                    <div class="policy-item">
                        <div class="icon tag-green"><i class="bi bi-box-arrow-in-right"></i></div>
                        <div>
                            <h4>Check-in time</h4>
                            <p><strong>{{ $checkin->format('g:i A') }}</strong> on
                                {{ $checkin->format('l, M d, Y') }}</p>
                        </div>
                    </div>

                    <div class="policy-item">
                        <div class="icon tag-blue"><i class="bi bi-box-arrow-right"></i></div>
                        <div>
                            <h4>Check-out time</h4>
                            <p><strong>{{ $checkout->format('g:i A') }}</strong> on
                                {{ $checkout->format('l, M d, Y') }}</p>
                        </div>
                    </div>

                    <div class="policy-item">
                        <div class="icon tag-red"><i class="bi bi-x-circle"></i></div>
                        <div>
                            <h4>Cancellation</h4>
                            <p>
                                <strong>Full refund</strong> if cancelled at least 7 days before check-in, or within
                                24 hours of booking. <strong>50% refund</strong> if cancelled 3–6 days before.
                                <strong>No refund</strong> within 3 days of check-in.
                            </p>
                        </div>
                    </div>

                    <div class="policy-item">
                        <div class="icon tag-purple"><i class="bi bi-arrow-repeat"></i></div>
                        <div>
                            <h4>Rescheduling</h4>
                            <p>
                                You may reschedule up to <strong>{{ \App\Models\Booking::MAX_RESCHEDULES }} times</strong>,
                                and only up to <strong>{{ \App\Models\Booking::RESCHEDULE_CUTOFF_DAYS }} days before
                                    check-in</strong>. Price differences are billed or refunded automatically.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="policy-modal-foot">
                    <a href="{{ route('portal.terms') }}" target="_blank" rel="noopener">
                        <i class="bi bi-file-text"></i> Read the full Terms of Service
                    </a>
                    <button type="button" class="policy-agree" id="policyAgree" data-bs-dismiss="modal">
                        <i class="bi bi-check2"></i> I've read these
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        /* Hindi maaaring pindutin ang "Proceed to Payment" hangga't hindi
           naka-tick ang policies checkbox.

           Sa JS ginagawa ang pag-disable, hindi sa markup: kung mabigong
           tumakbo ang script na ito, gumagana pa rin ang form — sasaluhin
           pa rin ng `required` sa checkbox at ng `accepted` rule sa
           PortalController::submitBooking() ang hindi nakatiking guest.
           Ang server ang tunay na nagpapatupad; UX affordance lang ito. */
        (function () {
            const check = document.getElementById('policiesAccepted');
            const submit = document.getElementById('submitBooking');
            const consent = document.getElementById('policyConsent');
            const agree = document.getElementById('policyAgree');

            if (!check || !submit) return;

            const note = document.getElementById('policyRequiredNote');
            const form = submit.closest('form');

            // The button is deliberately NOT disabled any more. A disabled
            // button is a dead end: it answers a tap with nothing at all,
            // and on a phone — where the consent row and the button don't
            // always sit on screen together — there is no way to work out
            // what is missing. Keep the button live and let the attempt
            // produce an explanation instead. The server still enforces
            // this (`required` here, `accepted` in submitBooking()); this
            // was only ever a UX affordance.
            function sync() {
                if (check.checked) {
                    consent?.classList.remove('is-invalid');
                    consent?.classList.add('is-checked');
                    if (note) note.hidden = true;
                } else {
                    consent?.classList.remove('is-checked');
                }
            }

            check.addEventListener('change', sync);

            function demandConsent() {
                consent?.classList.add('is-invalid');
                if (note) note.hidden = false;
                // Bring the guest to the thing they have to act on, rather
                // than leaving them looking at an unchanged screen.
                consent?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                check.focus({ preventScroll: true });
            }

            // `required` means the browser runs constraint validation first,
            // so the form's own 'submit' event never fires while the box is
            // unticked — the native bubble ("Please check this box…") is
            // anchored to the checkbox instead. On a phone that anchor can
            // be well off-screen, so the tap looks like it did nothing at
            // all. Suppress the native bubble and show our own message,
            // which scrolls the guest to the box it is talking about.
            check.addEventListener('invalid', (e) => {
                e.preventDefault();
                demandConsent();
            });

            // Fallback for the case where constraint validation is not what
            // stopped us (e.g. `required` is ever removed from the input).
            form?.addEventListener('submit', (e) => {
                if (check.checked) return;
                e.preventDefault();
                demandConsent();
            });

            // Clicking anywhere on the consent box toggles the checkbox for easy mobile tap,
            // while clicking the modal link button opens the modal without prematurely toggling.
            consent?.addEventListener('click', (e) => {
                if (e.target.closest('.policy-link') || e.target === check || e.target.closest('label')) return;
                check.checked = !check.checked;
                sync();
            });

            // Ang "I've read these" sa modal ang siya nang nagta-tick —
            // ang mismong pag-dismiss ay hawak ng data-bs-dismiss, kaya
            // hindi tayo umaasa sa Bootstrap JS API dito (na-load pa lang
            // iyon bilang module pagkatapos ng inline script na ito).
            agree?.addEventListener('click', () => {
                check.checked = true;
                sync();
            });

            sync();
        })();
    </script>
@endpush

