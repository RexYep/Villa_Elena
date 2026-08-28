@extends('layouts.portal')

@section('title', 'Terms of Service — ' . $resortName)

@push('styles')
    @include('portal.legal._styles')
@endpush

@section('content')
    <div class="legal-body">
        <a href="{{ route('home') }}" class="legal-back"><i class="bi bi-arrow-left"></i> Back to home</a>

        <div class="legal-hero">
            <div class="legal-eyebrow">Legal</div>
            <h1 class="legal-title">Terms of Service</h1>
            <p class="legal-sub">
                These terms cover how {{ $resortName }} is booked, paid for, rescheduled and
                cancelled. They describe exactly what the booking system enforces — the same
                rules the website applies to your reservation.
            </p>
            <div class="legal-meta">
                <span><i class="bi bi-clock-history"></i> Last updated {{ $lastUpdated->format('F j, Y') }}</span>
                <span><i class="bi bi-geo-alt"></i> {{ $resortAddress }}</span>
                <span><i class="bi bi-shield-check"></i> Philippine law applies</span>
            </div>
        </div>

        <div class="legal-wrap">
            {{-- Sidebar --}}
            <aside class="legal-toc">
                <div class="toc-title">On this page</div>
                <div class="toc-links">
                    <a class="toc-link" href="#agreement">1. This agreement</a>
                    <a class="toc-link" href="#property">2. What you are booking</a>
                    <a class="toc-link" href="#accounts">3. Accounts</a>
                    <a class="toc-link" href="#slots">4. Booking slots</a>
                    <a class="toc-link" href="#rates">5. Rates</a>
                    <a class="toc-link" href="#promos">6. Promotions</a>
                    <a class="toc-link" href="#deposit">7. Deposit &amp; confirmation</a>
                    <a class="toc-link" href="#payments">8. Payments</a>
                    <a class="toc-link" href="#cancellation">9. Cancellation &amp; refunds</a>
                    <a class="toc-link" href="#reschedule">10. Rescheduling</a>
                    <a class="toc-link" href="#payouts">11. How refunds are paid</a>
                    <a class="toc-link" href="#stay">12. Your stay</a>
                    <a class="toc-link" href="#reviews">13. Reviews</a>
                    <a class="toc-link" href="#assistant">14. AI assistant</a>
                    <a class="toc-link" href="#liability">15. Liability</a>
                    <a class="toc-link" href="#changes">16. Changes &amp; governing law</a>
                </div>
                <div class="toc-switch">
                    <a href="{{ route('portal.privacy') }}">Privacy Policy <i class="bi bi-arrow-right"></i></a>
                </div>
            </aside>

            {{-- Document --}}
            <article class="legal-doc">
                <section id="agreement">
                    <h2><span class="num">01</span>This agreement</h2>
                    <p>
                        This website and booking system is operated by <strong>{{ $resortName }}</strong>,
                        located at {{ $resortAddress }}. By browsing this site, creating an account, or
                        making a reservation, you agree to these Terms of Service.
                    </p>
                    <p>
                        If you book on behalf of a group, you accept these terms for everyone in your
                        party and you are responsible for making sure they follow the house rules in
                        <a href="#stay">Section 12</a>.
                    </p>
                </section>

                <section id="property">
                    <h2><span class="num">02</span>What you are booking</h2>
                    <p>
                        Villa Elena is a <strong>private, whole-property rental</strong>. The villa has
                        6 rooms, but they are <strong>not booked individually</strong> — one reservation
                        rents the entire villa exclusively to one guest group at a time. You will never
                        share the property with another party during your slot.
                    </p>
                    <p>
                        Room photos and descriptions on this site are shown for reference only. They do
                        not represent separately bookable units.
                    </p>
                </section>

                <section id="accounts">
                    <h2><span class="num">03</span>Accounts</h2>
                    <ul>
                        <li>You need a registered account to book online, and your email address must be
                            <strong>verified</strong> before a booking can be submitted.</li>
                        <li>The details you provide (name, contact number, address, and ID details where
                            requested) must be accurate — they are used to identify you at check-in and to
                            process payments and refunds.</li>
                        <li>You are responsible for keeping your password confidential. You may enable
                            <strong>two-factor authentication</strong>, in which case a one-time code is
                            emailed to you at sign-in.</li>
                        <li>Walk-in guests may have an account created for them by our front desk staff
                            using the details they provide on-site.</li>
                        <li>We may suspend or deactivate an account that is used for fraudulent bookings,
                            abuse of staff, or repeated no-shows.</li>
                    </ul>
                </section>

                <section id="slots">
                    <h2><span class="num">04</span>Booking slots</h2>
                    <p>
                        Every reservation is <strong>one fixed slot</strong> — there is no free-choice
                        check-in or check-out time:
                    </p>
                    <div class="legal-table-wrap">
                        <table class="legal-table">
                            <thead>
                                <tr>
                                    <th>Slot</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($slots as $key => $slot)
                                    @php
                                        [$slotIn, $slotOut] = \App\Models\Booking::slotDateTimes($key, now()->toDateString());
                                    @endphp
                                    <tr>
                                        <td><strong>{{ ucfirst($key) }}</strong></td>
                                        <td>{{ $slotIn->format('g:i A') }}</td>
                                        <td>
                                            {{ $slotOut->format('g:i A') }}
                                            <span class="text-muted-theme">({{ $slot['overnight'] ? 'next day' : 'same day' }})</span>
                                        </td>
                                        <td>{{ $slotIn->diffInHours($slotOut) }} hours</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="legal-note">
                        <p>
                            The gap between slots is reserved for cleaning and preparation, so early
                            arrival before your check-in time cannot be accommodated. Extending a stay
                            beyond your slot is possible only while you are checked in, subject to
                            availability and approval, and is charged separately.
                        </p>
                    </div>
                </section>

                <section id="rates">
                    <h2><span class="num">05</span>Rates</h2>
                    <p>
                        Villa Elena is charged as a <strong>flat package rate per slot</strong>. The price
                        does not change with the number of guests — it depends only on when your slot
                        starts:
                    </p>
                    @if ($villa)
                        <div class="legal-table-wrap">
                            <table class="legal-table">
                                <thead>
                                    <tr>
                                        <th>Segment</th>
                                        <th>When your slot starts</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Regular</strong></td>
                                        <td>Monday to Thursday (any slot), and Sunday from 6:00 PM onwards</td>
                                        <td>₱{{ number_format($villa->base_price, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Peak</strong></td>
                                        <td>Friday and Saturday (any slot), and Sunday before 6:00 PM</td>
                                        <td>₱{{ number_format($villa->weekend_price, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <p>
                        Special dates such as holidays may carry a published override rate. Whatever rate
                        applies, the exact total is always shown to you on the booking form
                        <strong>before</strong> you are asked to pay, and that figure is what you are
                        charged. Rates may change at any time, but a change never affects a booking that
                        is already confirmed.
                    </p>
                </section>

                <section id="promos">
                    <h2><span class="num">06</span>Promotions</h2>
                    <ul>
                        <li>Seasonal promos are applied <strong>automatically</strong> — there is no code
                            to type. A promo applies when your <strong>check-in date</strong> falls inside
                            the promo period and it covers the slot you selected.</li>
                        <li>A promo advertised on our homepage for a future period does not discount an
                            earlier stay. Only the check-in date decides.</li>
                        <li><strong>Promos never stack.</strong> If more than one applies, the single
                            largest discount is used.</li>
                        <li>Discounts apply to the villa rate only — never to extra charges added during
                            your stay.</li>
                        <li>We may add, change, or withdraw a promo at any time. A promo already applied
                            to a confirmed booking stays applied.</li>
                    </ul>
                </section>

                <section id="deposit">
                    <h2><span class="num">07</span>Deposit &amp; confirmation</h2>
                    <p>
                        A booking is created as <strong>pending</strong> and is only
                        <strong>confirmed</strong> once your first payment succeeds. To confirm, you must
                        pay at least the required deposit of
                        <strong>{{ rtrim(rtrim(number_format($depositPct, 2), '0'), '.') }}%</strong> of
                        your total (computed after any promo discount).
                    </p>
                    <div class="legal-note">
                        <p>
                            <strong>Unpaid bookings are released.</strong> If no payment is received within
                            <strong>{{ $holdMinutes }} minutes</strong> of creating a booking, it is
                            automatically cancelled and the slot is offered to other guests again. Nothing
                            is charged when this happens.
                        </p>
                    </div>
                    <p>
                        Any remaining balance is payable before or at check-in. Check-in may be held until
                        an outstanding balance is settled.
                    </p>
                </section>

                <section id="payments">
                    <h2><span class="num">08</span>Payments</h2>
                    <ul>
                        <li><strong>Online — QR Ph via PayMongo.</strong> You scan a QR code and pay from
                            GCash, Maya, or any participating bank app. Payment happens on PayMongo's
                            secure page, not on ours.</li>
                        <li><strong>Cash</strong> is accepted on-site at the front desk, mainly for
                            walk-in bookings and remaining balances.</li>
                        <li>We never see or store your card number, wallet PIN, OTP, or online banking
                            credentials.</li>
                        <li>QR Ph payments are confirmed asynchronously. If you close the page after
                            paying, your payment is still recorded — your booking and email notification
                            will reflect it once the payment provider confirms it.</li>
                        <li>Every payment appears in your account under <em>My Bookings</em>, with its
                            reference number.</li>
                    </ul>
                </section>

                <section id="cancellation">
                    <h2><span class="num">09</span>Cancellation &amp; refunds</h2>
                    <p>You may cancel a pending or confirmed booking from your account. The refund is
                        calculated on the amount you have actually paid:</p>
                    <div class="legal-table-wrap">
                        <table class="legal-table">
                            <thead>
                                <tr>
                                    <th>When you cancel</th>
                                    <th>Refund</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Within 24 hours of booking</strong> — whatever the check-in date</td>
                                    <td><strong>100%</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>7 days or more</strong> before check-in</td>
                                    <td><strong>100%</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>3 to 6 days</strong> before check-in</td>
                                    <td><strong>50%</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Less than 3 days</strong> before check-in, or a no-show</td>
                                    <td><strong>No refund</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        If <em>we</em> have to cancel your confirmed booking — for maintenance, a utility
                        failure, weather, or any other reason on our side — you receive a
                        <strong>full refund</strong> of everything you paid, or a free rebooking to
                        another available slot, whichever you prefer.
                    </p>
                </section>

                <section id="reschedule">
                    <h2><span class="num">10</span>Rescheduling</h2>
                    <ul>
                        <li>You may reschedule a booking up to <strong>{{ $maxReschedules }} times</strong>.</li>
                        <li>Rescheduling must be done at least
                            <strong>{{ $rescheduleCutoff }} days before check-in</strong>. Past that point
                            the booking can no longer be moved — the cancellation table in
                            <a href="#cancellation">Section 9</a> applies instead.</li>
                        <li>The new date and slot must be available.</li>
                        <li>If the new slot costs more, the difference is added to your balance. If it
                            costs less, the difference is refunded under
                            <a href="#payouts">Section 11</a>.</li>
                        <li>Promo eligibility is recalculated for the new check-in date — a promo may be
                            gained or lost by moving your stay.</li>
                    </ul>
                </section>

                <section id="payouts">
                    <h2><span class="num">11</span>How refunds are paid</h2>
                    <p>
                        A QR Ph payment cannot be reversed back to its source. An approved refund is
                        therefore sent as a <strong>separate transfer</strong> to a bank or e-wallet
                        account that you nominate, or handed to you in cash if you were checked in.
                    </p>
                    <ul>
                        <li>After a refund is approved, we ask you for the destination account —
                            institution, account number, and account name.</li>
                        <li>The account name must match the account exactly. There is no way for us to
                            verify a name in advance; an incorrect detail is only discovered when the
                            receiving institution rejects the transfer.</li>
                        <li>Transfers are sent over InstaPay and normally arrive within minutes, but the
                            receiving bank or wallet controls final crediting and may decline a transfer
                            (for example, for a blocked, dormant, or limit-exceeded account).</li>
                        <li>If a transfer is rejected, we retry it and, if it still fails, arrange another
                            payout method with you. <strong>A refund is only complete once the money has
                            actually been received</strong>, and your booking record shows that status.</li>
                        <li>We do not charge you a fee to send a refund. Any fee charged by your own bank
                            or wallet provider is outside our control.</li>
                    </ul>
                </section>

                <section id="stay">
                    <h2><span class="num">12</span>Your stay</h2>
                    <ul>
                        <li>Please present a valid ID at check-in. The lead guest must be the account
                            holder named on the booking.</li>
                        <li>Bring only the number of guests declared on your booking. Undeclared guests
                            may be refused entry or charged.</li>
                        <li>The villa, its furnishings, and its amenities must be returned in the
                            condition they were received. You are responsible for loss or damage caused by
                            your party, including damage from misuse of the pool, kitchen, or entertainment
                            equipment.</li>
                        <li>Illegal activity, harassment of staff or neighbours, and any use that
                            endangers other people or the property will end the stay immediately, with no
                            refund.</li>
                        <li>Check-in and check-out are recorded by the system at the slot times shown in
                            <a href="#slots">Section 4</a>. Staying past your check-out time may be charged
                            as an extension.</li>
                        <li>Personal belongings are your responsibility. Please check the villa before you
                            leave.</li>
                    </ul>
                </section>

                <section id="reviews">
                    <h2><span class="num">13</span>Reviews</h2>
                    <p>
                        You may write one review per booking, after you have checked out. Reviews are
                        screened automatically and by our staff before publication, and are published
                        together with your account name and the month of your stay.
                    </p>
                    <p>
                        We may decline to publish, or later remove, a review that contains abusive
                        language, personal data about other people, or content unrelated to an actual
                        stay. We may also post a public reply. We do not edit the wording of a published
                        review.
                    </p>
                </section>

                <section id="assistant">
                    <h2><span class="num">14</span>AI assistant</h2>
                    <p>
                        The chat assistant on this site answers questions about rates, slots, promos, and
                        the resort in general. Its replies are generated automatically and are
                        <strong>informational only</strong> — they are not a reservation, a quotation, or
                        a guarantee that a date is free.
                    </p>
                    <p>
                        Only the availability and total shown in the booking form itself, and the
                        confirmation you receive after payment, are binding.
                    </p>
                </section>

                <section id="liability">
                    <h2><span class="num">15</span>Liability</h2>
                    <p>
                        We work to keep this site and its booking information accurate and available, but
                        we cannot guarantee uninterrupted service, and obvious typographical or pricing
                        errors do not bind us — if one occurs we will contact you and either honour the
                        correct price or cancel with a full refund.
                    </p>
                    <p>
                        To the extent permitted by law, our total liability arising from a booking is
                        limited to the amount you paid for that booking. We are not liable for indirect
                        losses such as lost travel costs or lost time. Nothing in these terms removes
                        rights you have under Philippine consumer protection law.
                    </p>
                </section>

                <section id="changes">
                    <h2><span class="num">16</span>Changes &amp; governing law</h2>
                    <p>
                        We may update these terms. The current version is always published on this page
                        with its update date, and the version in effect when you booked governs that
                        booking.
                    </p>
                    <p>
                        These terms are governed by the laws of the <strong>Republic of the
                        Philippines</strong>, and any dispute that cannot be settled between us falls
                        under the jurisdiction of the courts of Laguna.
                    </p>
                </section>

                <div class="legal-contact">
                    <h2>Questions about these terms?</h2>
                    <p>Talk to us before you book — we would rather explain a rule than surprise you with it.</p>
                    <div class="legal-contact-rows">
                        <span><i class="bi bi-envelope"></i> <a href="mailto:{{ $resortEmail }}">{{ $resortEmail }}</a></span>
                        <span><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $resortPhone) }}">{{ $resortPhone }}</a></span>
                        <span><i class="bi bi-geo-alt"></i> {{ $resortAddress }}</span>
                    </div>
                </div>

                <div class="legal-doc-switch">
                    <a href="{{ route('portal.privacy') }}"><i class="bi bi-shield-lock"></i> Privacy Policy</a>
                    <a href="{{ route('home') }}#contact"><i class="bi bi-chat-dots"></i> Contact us</a>
                </div>
            </article>
        </div>
    </div>
@endsection

@push('scripts')
    @include('portal.legal._scripts')
@endpush
