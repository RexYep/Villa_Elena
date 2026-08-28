@extends('layouts.portal')

@section('title', 'Privacy Policy — ' . $resortName)

@push('styles')
    @include('portal.legal._styles')
@endpush

@section('content')
    <div class="legal-body">
        <a href="{{ route('home') }}" class="legal-back"><i class="bi bi-arrow-left"></i> Back to home</a>

        <div class="legal-hero">
            <div class="legal-eyebrow">Legal</div>
            <h1 class="legal-title">Privacy Policy</h1>
            <p class="legal-sub">
                What {{ $resortName }} collects when you browse, book, pay, or message us — why we
                collect it, who processes it on our behalf, and the rights you have over it under
                the Philippine Data Privacy Act.
            </p>
            <div class="legal-meta">
                <span><i class="bi bi-clock-history"></i> Last updated {{ $lastUpdated->format('F j, Y') }}</span>
                <span><i class="bi bi-geo-alt"></i> {{ $resortAddress }}</span>
                <span><i class="bi bi-shield-check"></i> RA 10173 (Data Privacy Act of 2012)</span>
            </div>
        </div>

        <div class="legal-wrap">
            {{-- Sidebar --}}
            <aside class="legal-toc">
                <div class="toc-title">On this page</div>
                <div class="toc-links">
                    <a class="toc-link" href="#who">1. Who we are</a>
                    <a class="toc-link" href="#collect">2. What we collect</a>
                    <a class="toc-link" href="#use">3. How we use it</a>
                    <a class="toc-link" href="#basis">4. Our legal basis</a>
                    <a class="toc-link" href="#share">5. Who we share it with</a>
                    <a class="toc-link" href="#cookies">6. Cookies</a>
                    <a class="toc-link" href="#retention">7. How long we keep it</a>
                    <a class="toc-link" href="#rights">8. Your rights</a>
                    <a class="toc-link" href="#security">9. How we protect it</a>
                    <a class="toc-link" href="#children">10. Children</a>
                    <a class="toc-link" href="#changes">11. Changes</a>
                </div>
                <div class="toc-switch">
                    <a href="{{ route('portal.terms') }}">Terms of Service <i class="bi bi-arrow-right"></i></a>
                </div>
            </aside>

            {{-- Document --}}
            <article class="legal-doc">
                <section id="who">
                    <h2><span class="num">01</span>Who we are</h2>
                    <p>
                        <strong>{{ $resortName }}</strong>, located at {{ $resortAddress }}, operates this
                        website and booking system and is the personal information controller for the data
                        described below.
                    </p>
                    <p>
                        For any privacy question or request, write to
                        <a href="mailto:{{ $resortEmail }}">{{ $resortEmail }}</a> or call
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $resortPhone) }}">{{ $resortPhone }}</a>.
                    </p>
                </section>

                <section id="collect">
                    <h2><span class="num">02</span>What we collect</h2>

                    <h3>Account details</h3>
                    <p>
                        Your full name, email address, mobile number, address, and — where we need to
                        verify your identity at check-in — your ID type and ID number. You may also upload
                        a profile photo. Your password is stored only as a cryptographic hash; we cannot
                        read it.
                    </p>

                    <h3>Booking details</h3>
                    <p>
                        Your check-in date and slot, number of guests, special requests, booking reference,
                        booking status, and the history of changes to it (cancellations, reschedules,
                        check-in and check-out times).
                    </p>

                    <h3>Payment details</h3>
                    <p>
                        The amount, date, method (QR Ph or cash), type, and reference numbers of each
                        payment. Online payments are completed on <strong>PayMongo's</strong> secure page —
                        <strong>we never receive or store your card number, e-wallet PIN, OTP, or online
                        banking credentials.</strong>
                    </p>

                    <h3>Refund payout details</h3>
                    <p>
                        If you are owed a refund, we ask for the destination account: institution name,
                        the institution's bank identifier code, account number, and account name. This is
                        collected only when a refund is due, and is kept as the record of where the money
                        was sent.
                    </p>

                    <h3>What you write to us</h3>
                    <p>
                        Contact-form messages, review text and ratings, cancellation reasons, and messages
                        you type into the chat assistant.
                    </p>

                    <h3>Technical and operational records</h3>
                    <p>
                        Server logs (IP address, browser and device information, pages requested, time
                        stamps), sign-in activity including your last login, and internal staff logs
                        recording which staff member acted on your booking and when.
                    </p>
                </section>

                <section id="use">
                    <h2><span class="num">03</span>How we use it</h2>
                    <ul>
                        <li>To create and manage your account, and to verify your email address.</li>
                        <li>To take, confirm, reschedule, and cancel bookings, and to prepare the villa for
                            your arrival.</li>
                        <li>To collect payment, record it against your booking, and issue refunds you are
                            entitled to.</li>
                        <li>To send you transactional messages — booking confirmations, payment receipts,
                            reminders, cancellation and refund notices, password resets, and two-factor
                            codes. These are service messages, not marketing.</li>
                        <li>To show you in-app announcements such as seasonal promos while you are signed
                            in.</li>
                        <li>To screen reviews before they are published, and to reply to them.</li>
                        <li>To answer your questions through the contact form and the chat assistant.</li>
                        <li>To keep the system secure — rate-limiting sign-in attempts, detecting abuse,
                            and investigating incidents.</li>
                        <li>To understand our own business through occupancy, revenue, and demand
                            reporting. This reporting works on aggregate figures, not on individual
                            profiles.</li>
                        <li>To keep the financial and guest records the law requires us to keep.</li>
                    </ul>
                    <div class="legal-note">
                        <p><strong>We do not sell your personal data,</strong> and we do not share it with
                            advertisers or data brokers.</p>
                    </div>
                </section>

                <section id="basis">
                    <h2><span class="num">04</span>Our legal basis</h2>
                    <p>Under the Data Privacy Act of 2012 (RA 10173) we rely on:</p>
                    <ul>
                        <li><strong>Performance of a contract</strong> — everything needed to give you the
                            booking you paid for.</li>
                        <li><strong>Your consent</strong> — optional details such as a profile photo,
                            special requests, and the content of a review you choose to publish.</li>
                        <li><strong>Legitimate interests</strong> — securing the system, preventing fraud
                            and abuse, and reporting on our own operations.</li>
                        <li><strong>Legal obligation</strong> — tax, accounting, and guest-record
                            requirements.</li>
                    </ul>
                </section>

                <section id="share">
                    <h2><span class="num">05</span>Who we share it with</h2>
                    <p>
                        Our own staff see only what their role needs: front-desk staff see bookings,
                        check-ins, and payments; administrators additionally manage refunds, reports, and
                        accounts. Outside the resort, we use these service providers:
                    </p>
                    <div class="legal-table-wrap">
                        <table class="legal-table">
                            <thead>
                                <tr>
                                    <th>Provider</th>
                                    <th>What it does</th>
                                    <th>What it receives</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>PayMongo</strong></td>
                                    <td>Online payments and refund transfers</td>
                                    <td>Your name, email, booking reference, amount, and — for a refund —
                                        the destination account you gave us</td>
                                </tr>
                                <tr>
                                    <td><strong>Brevo</strong></td>
                                    <td>Sends our transactional email</td>
                                    <td>Your name, email address, and the content of that email</td>
                                </tr>
                                <tr>
                                    <td><strong>Pusher</strong></td>
                                    <td>Delivers real-time notifications while you are signed in</td>
                                    <td>The notification text and the account it belongs to</td>
                                </tr>
                                <tr>
                                    <td><strong>Cloudinary</strong></td>
                                    <td>Stores and serves images</td>
                                    <td>Photos uploaded to the system, including your profile photo</td>
                                </tr>
                                <tr>
                                    <td><strong>Groq</strong></td>
                                    <td>Runs the chat assistant and screens reviews</td>
                                    <td>The message you type into the chat, or the review text being
                                        screened. Please don't type sensitive personal details into the
                                        chat</td>
                                </tr>
                                <tr>
                                    <td><strong>Render &amp; Aiven</strong></td>
                                    <td>Application hosting and the database</td>
                                    <td>All stored data, as our infrastructure providers</td>
                                </tr>
                                <tr>
                                    <td><strong>Google Fonts</strong></td>
                                    <td>Serves the fonts used on this site</td>
                                    <td>Your IP address, as part of the ordinary request for a font file</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        These providers process data on our instructions. Some are located outside the
                        Philippines, so your data may be processed abroad under contractual safeguards. We
                        also disclose information where a valid legal order, tax requirement, or the
                        defence of a legal claim requires it.
                    </p>
                </section>

                <section id="cookies">
                    <h2><span class="num">06</span>Cookies</h2>
                    <p>
                        This site uses <strong>strictly necessary cookies only</strong>. We run no
                        analytics, advertising, or cross-site tracking cookies, and nothing here builds an
                        advertising profile of you — which is why you are not asked to accept a cookie
                        banner.
                    </p>
                    <div class="legal-table-wrap">
                        <table class="legal-table">
                            <thead>
                                <tr>
                                    <th>Cookie</th>
                                    <th>Purpose</th>
                                    <th>Lifetime</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>{{ config('session.cookie') }}</code></td>
                                    <td>Keeps you signed in and remembers your session between pages</td>
                                    <td>{{ config('session.lifetime') }} minutes of inactivity</td>
                                </tr>
                                <tr>
                                    <td><code>XSRF-TOKEN</code></td>
                                    <td>Security token that stops another site from submitting forms as
                                        you</td>
                                    <td>Same as the session</td>
                                </tr>
                                <tr>
                                    <td><code>remember_web_*</code></td>
                                    <td>Set only if you tick <em>Remember me</em> at sign-in</td>
                                    <td>Until you sign out or it expires</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        You can block or delete these in your browser settings, but signing in and booking
                        will not work without them. Pages hosted by others — for example PayMongo's payment
                        page — set their own cookies under their own policies.
                    </p>
                </section>

                <section id="retention">
                    <h2><span class="num">07</span>How long we keep it</h2>
                    <ul>
                        <li><strong>Account details</strong> — while your account exists, and afterwards
                            only where a booking or financial record requires it.</li>
                        <li><strong>Booking and payment records</strong> — kept as long as accounting, tax,
                            and audit rules require, because they are proof of a transaction.</li>
                        <li><strong>Refund payout details</strong> — kept with the refund record as proof
                            of where the money was sent.</li>
                        <li><strong>Published reviews</strong> — kept while published; you may ask us to
                            take yours down.</li>
                        <li><strong>Contact-form messages</strong> — kept while the enquiry is being
                            handled and for a reasonable period afterwards.</li>
                        <li><strong>Chat assistant conversations</strong> — <strong>not stored in our
                            database.</strong> The conversation lives in your browser tab only and is gone
                            once you close or refresh it.</li>
                        <li><strong>Server and staff activity logs</strong> — kept for a limited period for
                            security and troubleshooting.</li>
                    </ul>
                </section>

                <section id="rights">
                    <h2><span class="num">08</span>Your rights</h2>
                    <p>Under the Data Privacy Act you have the right to:</p>
                    <ul>
                        <li>Be <strong>informed</strong> about how your data is processed — this page.</li>
                        <li><strong>Access</strong> your data, and get a copy of it.</li>
                        <li><strong>Correct</strong> inaccurate details. You can edit most of them yourself
                            from your profile page.</li>
                        <li><strong>Object</strong> to processing, and to have data
                            <strong>erased or blocked</strong> where it is no longer necessary or was
                            processed unlawfully.</li>
                        <li><strong>Data portability</strong> — receive your data in a commonly used
                            electronic format.</li>
                        <li><strong>File a complaint</strong> with the National Privacy Commission, and to
                            claim damages for a violation.</li>
                    </ul>
                    <p>
                        To exercise any of these, email
                        <a href="mailto:{{ $resortEmail }}">{{ $resortEmail }}</a> from the address on your
                        account. We may ask you to confirm your identity first. If we cannot delete
                        something because the law requires us to keep it, we will tell you which record and
                        why.
                    </p>
                </section>

                <section id="security">
                    <h2><span class="num">09</span>How we protect it</h2>
                    <ul>
                        <li>Passwords are stored as one-way hashes, never in readable form.</li>
                        <li>Optional <strong>two-factor authentication</strong> sends a one-time code to
                            your email at sign-in.</li>
                        <li>Sign-in, registration, and password-reset attempts are rate-limited.</li>
                        <li>Access is role-based — staff and administrators only reach the areas their
                            role allows.</li>
                        <li>Traffic to the site and to our database is encrypted in transit.</li>
                        <li>Card and e-wallet credentials never touch our servers; they are handled by
                            PayMongo.</li>
                    </ul>
                    <p>
                        No system is perfectly secure. If a breach affects your personal data in a way that
                        may cause you real harm, we will notify you and the National Privacy Commission as
                        the law requires.
                    </p>
                </section>

                <section id="children">
                    <h2><span class="num">10</span>Children</h2>
                    <p>
                        Accounts and bookings are for adults. Children are of course welcome as guests at
                        the villa, but we do not knowingly collect personal data directly from a child. If
                        you believe a child has created an account, contact us and we will remove it.
                    </p>
                </section>

                <section id="changes">
                    <h2><span class="num">11</span>Changes</h2>
                    <p>
                        If we change how we handle personal data, we update this page and the date at the
                        top of it. Significant changes will also be announced in the app the next time you
                        sign in.
                    </p>
                </section>

                <div class="legal-contact">
                    <h2>Privacy questions or requests</h2>
                    <p>Reach out and we will answer — including requests to access, correct, or delete your data.</p>
                    <div class="legal-contact-rows">
                        <span><i class="bi bi-envelope"></i> <a href="mailto:{{ $resortEmail }}">{{ $resortEmail }}</a></span>
                        <span><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $resortPhone) }}">{{ $resortPhone }}</a></span>
                        <span><i class="bi bi-geo-alt"></i> {{ $resortAddress }}</span>
                    </div>
                </div>

                <div class="legal-doc-switch">
                    <a href="{{ route('portal.terms') }}"><i class="bi bi-file-text"></i> Terms of Service</a>
                    <a href="{{ route('home') }}#contact"><i class="bi bi-chat-dots"></i> Contact us</a>
                </div>
            </article>
        </div>
    </div>
@endsection

@push('scripts')
    @include('portal.legal._scripts')
@endpush
