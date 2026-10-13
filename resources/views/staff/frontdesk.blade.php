@extends('layouts.staff')

@section('title', 'Frontdesk — Villa Elena Staff')
@section('page-title', 'Frontdesk Operations')
@section('page-subtitle', today()->format('l, F j, Y'))

@section('sidebar-extra')

@endsection

{{-- This page's stylesheet: its own Vite entry, linked here and nowhere
     else, after the staff bundle (see the note at the top of
     resources/css/staff/frontdesk.css). --}}
@push('styles')
    @vite(['resources/css/staff/frontdesk.css'])
@endpush

@section('topbar-right')
    <div class="text-end">
        <div class="live-clock" id="liveClock"></div>
        <div class="live-date">Philippine Standard Time</div>
    </div>
@endsection

@section('content')

    @include('staff.partials.flash', ['autohide' => true])

    {{-- A refused payment comes back in its own error bag, and the script at
         the bottom reopens the Record Payment form with it. This copy is the
         fallback for when that form cannot be reopened (the booking's row is
         no longer on the page); the script removes it once the form is up. --}}
    @php $paymentRetry = $errors->payment->any() ? session('payment_retry') : null; @endphp
    @if ($errors->payment->any())
        <div class="alert alert-danger" role="alert" id="paymentErrorAlert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <span class="alert-text">{{ $errors->payment->first() }}</span>
            <button type="button" class="alert-close" data-alert-close aria-label="Dismiss message">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    {{-- Stats — kusang nag-a-update kasama ang Today list (v7.19). Ang
         partial ang ini-render muli ng server; walang kopya ng lohika sa JS. --}}
    <div class="stats-wrap">
        <div class="stats-row" id="fdStatsLive">
            @include('staff.partials._frontdesk_stats')
        </div>
    </div>

    {{-- Villa status + next two slots. Live: kinukuha muli sa staff.frontdesk.today --}}
    <div id="fdVillaLive">
        @include('staff.partials._villa_strip')
    </div>

    {{-- Pinaka-madaliang housekeeping item: ulat ng guest, o task na
         overdue/urgent/malapit na deadline (FrontDeskController::mostUrgentHousekeeping) --}}
    <div id="fdUrgentLive">
        @include('staff.partials._urgent_housekeeping')
    </div>

    {{-- Tabs. A real tablist: each tab names the panel it controls and says
         whether it is selected, only the selected one is in the Tab order,
         and the arrow keys move between them (the script's switchTab()). --}}
    <div class="tab-bar" role="tablist" aria-label="Frontdesk lists">
        <button type="button" class="tab-btn active" role="tab" id="tabbtn-today" data-tab="today"
            aria-controls="tab-today" aria-selected="true">
            <i class="bi bi-list-check" aria-hidden="true"></i> Today
            <span class="cnt" id="fdTodayTabCount">{{ $todayItems->count() }}</span>
        </button>
        <button type="button" class="tab-btn" role="tab" id="tabbtn-pending" data-tab="pending"
            aria-controls="tab-pending" aria-selected="false" tabindex="-1">
            <i class="bi bi-clock" aria-hidden="true"></i> Awaiting Payment
            <span class="cnt" id="fdPendingTabCount">{{ $pendingBookings->count() }}</span>
        </button>
        <button type="button" class="tab-btn" role="tab" id="tabbtn-housekeeping" data-tab="housekeeping"
            aria-controls="tab-housekeeping" aria-selected="false" tabindex="-1">
            <i class="bi bi-brush" aria-hidden="true"></i> Housekeeping
            <span class="cnt" id="fdHousekeepingTabCount">{{ $stats['pending_tasks'] }}</span>
        </button>
    </div>

    {{-- Tab: Today — arrivals at departures sa IISANG listahan, sunod sa
         oras. Dati'y hiwalay na "Check-ins" at "Check-outs" tab: dalawang
         slot lang kada araw ang villa, kaya tig-isang row lang ang dalawa
         at kailangan pang buksan ang pareho para makita ang buong araw.
         Pareho pa rin ang mga id/class ng form (`checkinForm_{id}`,
         `checkout-form-{id}`) — ito ang hinahanap ng staff realtime
         partial para i-disable ang naunahan nang aksiyon. --}}
    <div class="tab-content active" id="tab-today" role="tabpanel" aria-labelledby="tabbtn-today">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-list-check me-2 text-green"></i>Today's Arrivals &amp; Departures</h3>
                <span class="text-muted-theme fs-14">{{ today()->format('M d, Y') }}</span>
            </div>
            {{-- Kusang nag-a-update: auto check-in/out ng scheduler, bayad na
                 dumaan sa webhook, o aksiyon ng ibang staff. Ang server ang
                 nagre-render ng parehong partial (staff.frontdesk.today). --}}
            <div class="card-body" id="fdTodayLive">
                @include('staff.partials._today_list')
            </div>
        </div>
    </div>

    {{-- Tab: Awaiting Payment --}}
    <div class="tab-content" id="tab-pending" role="tabpanel" aria-labelledby="tabbtn-pending">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-clock me-2 text-amber"></i>Awaiting Payment</h3>
                {{-- Says what the heading does not: how long an unpaid
                     booking keeps its slot. --}}
                <span class="text-muted-theme fs-14">Unpaid holds are released after {{ $holdMinutes }} minutes</span>
            </div>
            <div class="card-body" id="fdPendingLive">
                @include('staff.partials._pending_list')
            </div>
        </div>
    </div>

    {{-- Tab: Housekeeping — mga ulat ng problema at mga task mula sa admin (v7.11) --}}
    <div class="tab-content" id="tab-housekeeping" role="tabpanel" aria-labelledby="tabbtn-housekeeping">
        <div class="hk-toolbar">
            <p>Tasks are sent by the admin. Guests can report problems from their account while checked in.</p>
            <button type="button" class="btn-report" data-open-report>
                <i class="bi bi-exclamation-triangle"></i> Report issue
            </button>
        </div>

        {{-- Live: kinukuha muli sa staff.frontdesk.housekeeping kapag may issues.changed --}}
        <div id="fdIssueReportsLive">
            @include('staff.partials._issue_reports')
        </div>

        {{-- Live: kinukuha muli sa staff.frontdesk.housekeeping --}}
        <div id="fdTasksLive">
            @include('staff.partials._admin_tasks')
        </div>
    </div>

    {{-- Ang dating "Properties" tab ay nagpapakita ng isang card kada
         property row — kasama ang tatlong info-only na `type=room` na
         record na wala nang pangalan, kaya blangkong card ang lumalabas.
         Napalitan na ito ng Villa strip sa itaas (real-time na estado)
         at ng Availability page (petsa/slot na availability). --}}

@endsection

@section('modals')
    {{-- The three forms. Shown and hidden with the `hidden` attribute by
         showModal() / hideModal() in the script below; a button with
         `data-close-modal` closes the one it is in. No inline style and no
         inline handler here: the look is in css/staff/frontdesk.css and
         the behaviour in that script. --}}

    <!-- Report Issue Modal (v7.11) -->
    @php $reportErrors = $errors->issueReport; @endphp
    <div id="reportModal" class="modal-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
        <div class="modal-box modal-report">
            <div class="modal-head">
                <div class="modal-title" id="reportModalTitle">Report an Issue</div>
                <button type="button" data-close-modal class="modal-close" aria-label="Close">✕</button>
            </div>
            <form method="POST" action="{{ route('staff.reports.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="report-label" id="reportCategoryLabel">
                        What kind of problem?</div>
                    <div class="category-pick" role="radiogroup" aria-labelledby="reportCategoryLabel">
                        @foreach (\App\Models\IssueReport::CATEGORIES as $key => $cat)
                            <label>
                                <input type="radio" name="category" value="{{ $key }}" required @checked(old('category') === $key)>
                                <i class="bi bi-{{ $cat['icon'] }}"></i>
                                {{ $cat['label'] }}
                            </label>
                        @endforeach
                    </div>
                    @if ($reportErrors->has('category'))
                        <div class="report-error">{{ $reportErrors->first('category') }}</div>
                    @endif

                    <label for="reportDescription" class="report-label-details">
                        Details <span class="fw-400 text-muted-theme">(required for "Other")</span></label>
                    <textarea name="description" id="reportDescription" rows="3" maxlength="{{ \App\Models\IssueReport::DESCRIPTION_MAX }}"
                        class="input-plain"
                        placeholder="e.g. Light in Room B keeps flickering">{{ old('description') }}</textarea>
                    @if ($reportErrors->has('description'))
                        <div class="report-error">{{ $reportErrors->first('description') }}</div>
                    @endif

                    <p class="report-note">
                        It's logged on this frontdesk and the admin is notified.
                    </p>
                    <button type="submit" class="btn-modal is-danger">
                        <i class="bi bi-send me-2"></i> Submit Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
        <div class="modal-box modal-payment">
            <!-- Modal Header -->
            <div class="modal-head">
                <div>
                    <div class="modal-title" id="paymentModalTitle">Record Payment</div>
                    <div class="modal-sub" id="modalBookingRef"></div>
                </div>
                <button type="button" data-close-modal class="modal-close" aria-label="Close">✕</button>
            </div>
            <!-- Modal Body -->
            <form id="paymentForm" method="POST">
                @csrf
                <div class="modal-body">
                    {{-- Why the last attempt was refused. Filled by
                         restorePaymentAttempt() when the form is reopened. --}}
                    <div id="paymentError" class="modal-error" role="alert" hidden></div>
                    <!-- Balance Info -->
                    <div class="modal-balance">
                        <span class="note-text">Balance Due</span>
                        <span class="modal-balance-val" id="modalBalance">₱0.00</span>
                    </div>
                    <!-- Amount -->
                    <div class="mb-14">
                        <label for="modalAmount"
                            class="field-label">Amount</label>
                        {{-- .input-plain, like the fields below it: its inline
                             border was --border, 1.3:1 against white. --}}
                        <input type="number" name="amount" id="modalAmount" required min="1" step="0.01"
                            class="input-plain" placeholder="Enter amount">
                    </div>
                    {{-- Method. There is no Type field: whether this is a
                         partial, full or balance payment follows from the amount
                         and what is owed, and the server works it out
                         (FrontDeskController::paymentTypeFor()). --}}
                    <div class="mb-14">
                        <label for="f_payment_method" class="field-label">Method</label>
                        <select id="f_payment_method" name="payment_method" required class="input-plain">
                            <option value="cash">Cash</option>
                            <option value="qrph">QR Ph (GCash / Maya / bank app)</option>
                        </select>
                    </div>
                    <!-- Notes -->
                    <div class="pay-notes">
                        <label for="f_notes" class="field-label">Notes
                            <span class="fw-400 text-muted-theme">(optional)</span></label>
                        <input id="f_notes" type="text" name="notes"
                            class="input-plain"
                            placeholder="e.g. Cash received at frontdesk">
                    </div>
                    <!-- Kailangan lang kapag may kamukhang bayad na naitala
                         ngayong araw para sa booking na ito — hinaharangan ang
                         pagtatala hangga't hindi ito nakatik. -->
                    {{-- Hidden until the server asks the question. Shown on
                         every payment, it was a tick box about a duplicate
                         that nobody had been told existed. --}}
                    <label id="paymentSeparateWrap" class="modal-check" hidden>
                        <input type="checkbox" name="confirm_duplicate" value="1">
                        <span>This is a <strong>separate</strong> payment — tick only if the guest really paid this
                            amount again today.</span>
                    </label>
                    <!-- Submit -->
                    <button type="submit" class="btn-modal is-primary">
                        <i class="bi bi-check-circle me-2"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Check-in Confirmation Modal (bago) — bumubukas ito lang kapag may
         natitirang balance ang booking na gustong i-check-in. Pinipilit
         nitong pumili si staff sa dalawang landas bago matuloy ang check-in. -->
    <div id="checkinConfirmModal" class="modal-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="checkinModalTitle">
        <div class="modal-box modal-checkin">
            <div class="modal-head">
                <div>
                    <div class="modal-title" id="checkinModalTitle">Outstanding Balance</div>
                    <div class="modal-sub">Guest: <span
                            id="ccGuestName"></span></div>
                </div>
                <button type="button" data-close-modal class="modal-close" aria-label="Close">✕</button>
            </div>
            <div class="modal-body">
                <div class="modal-balance is-due">
                    <span class="modal-balance-lbl">Balance Due</span>
                    <span class="modal-balance-val" id="ccBalance">₱0.00</span>
                </div>

                <p class="checkin-lead">
                    Choose what to do before proceeding with this guest's check-in:
                </p>

                <!-- Option A: Pay Now -->
                <button type="button" id="ccPayNowBtn" class="choice is-pay">
                    <div class="d-flex-gap-10">
                        <div class="choice-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <div class="choice-title">Pay Now</div>
                            <div class="choice-sub">Open the Payment form to record the
                                full balance</div>
                        </div>
                    </div>
                </button>

                <!-- Option B: Defer -->
                <div class="choice is-later">
                    <div class="d-flex-gap-10 mb-10">
                        <div class="choice-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div class="choice-title">Pay Later</div>
                            <div class="choice-sub">To be paid before check-out</div>
                        </div>
                    </div>
                    <label class="modal-check is-strong">
                        <input type="checkbox" id="ccDeferCheckbox">
                        <span>I confirm that I will allow this guest to check in now and that the outstanding balance will
                            be settled before check-out. This is recorded in the staff log.</span>
                    </label>
                    <button type="button" id="ccConfirmBtn" class="btn-defer" disabled>
                        <i class="bi bi-box-arrow-in-right me-1"></i> Confirm and Check In
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Live clock
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').textContent =
                now.toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Tab switching. Keeps the three things a tablist promises in step:
        // which panel shows, which tab says aria-selected, and which tab is
        // the one stop in the Tab order.
        const tabButtons = Array.from(document.querySelectorAll('.tab-bar [role="tab"]'));

        function switchTab(name, focus) {
            tabButtons.forEach(function(button) {
                const on = button.dataset.tab === name;
                button.classList.toggle('active', on);
                button.setAttribute('aria-selected', on ? 'true' : 'false');
                button.tabIndex = on ? 0 : -1;
                document.getElementById('tab-' + button.dataset.tab).classList.toggle('active', on);
                if (on && focus) button.focus();
            });
        }

        tabButtons.forEach(function(button, index) {
            button.addEventListener('click', function() {
                switchTab(button.dataset.tab);
            });
            button.addEventListener('keydown', function(e) {
                const to = {
                    ArrowRight: index + 1,
                    ArrowLeft: index - 1,
                    Home: 0,
                    End: tabButtons.length - 1
                }[e.key];
                if (to === undefined) return;
                e.preventDefault();
                const next = tabButtons[(to + tabButtons.length) % tabButtons.length];
                switchTab(next.dataset.tab, true);
            });
        });

        // A stat card that names one of the lists opens it. Delegated: the
        // stats row is replaced by the live refresh.
        document.addEventListener('click', function(e) {
            const go = e.target.closest('[data-goto-tab]');
            if (!go) return;
            switchTab(go.dataset.gotoTab, true);
            document.querySelector('.tab-bar').scrollIntoView({ block: 'start', behavior: 'smooth' });
        });

        // ── Modals ─────────────────────────────────────────────────────
        // One open and one close for the three forms, so each of them moves
        // the keyboard into the form, keeps it there, closes on Esc and
        // hands focus back to the button that opened it. They were shown by
        // setting `display` alone: focus stayed on the page behind, Tab
        // walked through that page, and Esc did nothing.
        let modalOpener = null;

        // Shown and hidden with the `hidden` attribute, so the markup needs no
        // inline display and "is one open?" is a property, not a style read.
        function openModal() {
            return Array.from(document.querySelectorAll('.modal-overlay')).find(m => !m.hidden) || null;
        }

        function showModal(id, focusSelector) {
            const overlay = document.getElementById(id);
            // Opening one form from another (Pay Now) keeps the first opener.
            if (!openModal()) modalOpener = document.activeElement;
            overlay.hidden = false;
            overlay.scrollTop = 0;
            const target = (focusSelector && overlay.querySelector(focusSelector)) || overlay.querySelector('.modal-close');
            if (target) target.focus();
        }

        function hideModal(id) {
            document.getElementById(id).hidden = true;
            // The opener may be gone: the live refresh replaces the list.
            if (!openModal() && modalOpener && document.contains(modalOpener)) modalOpener.focus();
        }

        function focusableIn(overlay) {
            return Array.from(overlay.querySelectorAll('button, [href], input, select, textarea'))
                .filter(el => !el.disabled && el.type !== 'hidden' && el.offsetParent !== null);
        }

        const MODAL_CLOSERS = {
            paymentModal: () => closePaymentModal(),
            checkinConfirmModal: () => closeCheckinConfirmModal(),
            reportModal: () => closeReportModal(),
        };

        // The buttons that open or close a form. They carry a data
        // attribute and no inline handler; one listener serves them all,
        // including any drawn later by the live refresh.
        document.addEventListener('click', function(e) {
            const close = e.target.closest('[data-close-modal]');
            if (close) {
                const overlay = close.closest('.modal-overlay');
                if (overlay) MODAL_CLOSERS[overlay.id]();
                return;
            }
            if (e.target.closest('[data-open-report]')) openReportModal();
        });

        document.addEventListener('keydown', function(e) {
            const overlay = openModal();
            // The confirm dialog is a native <dialog> above these: it has
            // its own Esc and its own focus handling.
            if (!overlay || document.getElementById('confirmDialog').open) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                MODAL_CLOSERS[overlay.id]();
                return;
            }

            if (e.key !== 'Tab') return;
            const items = focusableIn(overlay);
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (!overlay.contains(document.activeElement)) {
                e.preventDefault();
                first.focus();
            } else if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        // Payment Modal Functions
        const paymentFormUrlTemplate = '{{ route('staff.payment', ['booking' => '__BOOKING_ID__']) }}';

        function showPaymentError(text) {
            const el = document.getElementById('paymentError');
            el.textContent = text || '';
            el.hidden = !text;
        }

        function askSeparatePayment(ask) {
            const wrap = document.getElementById('paymentSeparateWrap');
            wrap.hidden = !ask;
            if (!ask) wrap.querySelector('input').checked = false;
        }

        // `bookingLabel` is the line under the title: "Guest name · VE-…".
        function openPaymentModal(bookingId, bookingLabel, balanceDue) {
            // A fresh attempt: nothing left over from another booking, no old
            // refusal, and no "separate payment" question until the server
            // asks it.
            document.getElementById('paymentForm').reset();
            showPaymentError('');
            askSeparatePayment(false);

            document.getElementById('modalBookingRef').textContent = bookingLabel;
            document.getElementById('modalBalance').textContent = '₱' + parseFloat(balanceDue).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('modalAmount').value = balanceDue > 0 ? balanceDue : '';
            document.getElementById('paymentForm').action = paymentFormUrlTemplate.replace('__BOOKING_ID__', bookingId);
            showModal('paymentModal', '#modalAmount');
        }

        function closePaymentModal() {
            hideModal('paymentModal');
        }

        // A row's Payment button. Delegated, because the Today list is
        // replaced by the live refresh and its buttons with it.
        document.addEventListener('click', function(e) {
            const button = e.target.closest('[data-pay-booking]');
            if (!button) return;
            openPaymentModal(button.dataset.payBooking, button.dataset.payLabel, parseFloat(button.dataset.payBalance) || 0);
        });

        // Close modal when clicking on backdrop
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) closePaymentModal();
        });

        // A refused payment reloads the page. Put the form back as it was
        // left, with the reason inside it, instead of a closed modal and a
        // message at the top of the page. Opens through the row's own
        // Payment button, so the reference and the balance are today's.
        // Returns false when that row is gone (nothing left to pay); the
        // message at the top of the page then stays as the only notice.
        function restorePaymentAttempt(attempt) {
            const button = document.querySelector('[data-pay-booking="' + attempt.booking + '"]');
            if (!button) return false;

            button.click();

            const form = document.getElementById('paymentForm');
            if (attempt.amount !== null) form.elements.amount.value = attempt.amount;
            if (attempt.method) form.elements.payment_method.value = attempt.method;
            form.elements.notes.value = attempt.notes || '';

            showPaymentError(attempt.error);
            askSeparatePayment(attempt.askSeparate);
            document.getElementById('paymentErrorAlert')?.remove();
            return true;
        }

        @if ($paymentRetry)
            restorePaymentAttempt({{ Illuminate\Support\Js::from([
                'booking' => (int) ($paymentRetry['booking'] ?? 0),
                'askSeparate' => (bool) ($paymentRetry['ask_separate'] ?? false),
                'error' => $errors->payment->first(),
                'amount' => old('amount'),
                'method' => old('payment_method'),
                'notes' => old('notes'),
            ]) }});
        @endif

        // ── Check-in Confirmation (balance handling) ────────────────────
        let pendingCheckinBookingId = null;

        // A Check In form is `form[data-checkin]`, with the guest and the
        // balance in data attributes (staff/partials/_today_list). Delegated,
        // because the Today list is replaced by the live refresh.
        document.addEventListener('submit', function(event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-checkin')) return;
            handleCheckInSubmit(event, form);
        });

        function handleCheckInSubmit(event, form) {
            const bookingId = form.dataset.checkin;
            const guestName = form.dataset.guest;
            const balanceDue = parseFloat(form.dataset.balance) || 0;

            if (balanceDue <= 0) {
                // Walang balance — isang tanong lang, tapos tuloy. Ang form ang
                // may hawak ng `data-confirmed` para hindi na muling magtanong
                // ang ikalawang submit.
                if (form.dataset.confirmed === '1') return;
                event.preventDefault();
                const submitter = event.submitter;
                confirmDialog('Check in ' + guestName + '?', {
                    confirmLabel: 'Check in',
                    tone: 'neutral'
                }).then(function(ok) {
                    if (!ok) return;
                    form.dataset.confirmed = '1';
                    form.requestSubmit(submitter || undefined);
                    delete form.dataset.confirmed;
                });
                return;
            }
            // May natitirang balance — hadlangan muna ang submit at patukuyin
            // si staff kung babayaran ngayon o ipagpapaliban (confirmed).
            event.preventDefault();
            openCheckinConfirmModal(bookingId, guestName, balanceDue);
        }

        function openCheckinConfirmModal(bookingId, guestName, balanceDue) {
            pendingCheckinBookingId = bookingId;
            document.getElementById('ccGuestName').textContent = guestName;
            document.getElementById('ccBalance').textContent = '₱' + parseFloat(balanceDue).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('ccDeferCheckbox').checked = false;
            // Its faded look while disabled is `.btn-defer:disabled` in the
            // stylesheet; this only sets the state.
            document.getElementById('ccConfirmBtn').disabled = true;
            showModal('checkinConfirmModal', '#ccPayNowBtn');
        }

        function closeCheckinConfirmModal() {
            hideModal('checkinConfirmModal');
            pendingCheckinBookingId = null;
        }

        document.getElementById('checkinConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeCheckinConfirmModal();
        });

        document.getElementById('ccDeferCheckbox').addEventListener('change', function() {
            document.getElementById('ccConfirmBtn').disabled = !this.checked;
        });

        document.getElementById('ccPayNowBtn').addEventListener('click', ccPayNow);
        document.getElementById('ccConfirmBtn').addEventListener('click', ccConfirmDeferred);

        function ccPayNow() {
            // Isara ang confirmation modal, buksan ang Payment modal na naka-prefill
            // sa buong balance. Pagkatapos ma-record ang bayad (₱0 na ang balance),
            // pindutin lang ulit ang "Check In" — awtomatiko na itong magpapatuloy
            // nang walang modal, dahil wala nang balance na natitira.
            //
            // Opens through the row's own Payment button, like a refused
            // payment does, so both ways in show the same guest, reference
            // and balance. This used to pass the guest's name where the
            // other path passes the booking reference.
            const button = document.querySelector('[data-pay-booking="' + pendingCheckinBookingId + '"]');
            closeCheckinConfirmModal();
            if (button) button.click();
        }

        function ccConfirmDeferred() {
            if (!pendingCheckinBookingId) return;
            const form = document.getElementById('checkinForm_' + pendingCheckinBookingId);
            if (!form) return;

            // Idagdag ang hidden input na nagsasabing "deferred" ang balance
            // arrangement — kailangan ito ng backend (FrontDeskController::checkIn)
            // bago payagan ang check-in kapag may natitirang balance.
            let hidden = form.querySelector('input[name="balance_arrangement"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'balance_arrangement';
                form.appendChild(hidden);
            }
            hidden.value = 'deferred';

            closeCheckinConfirmModal();
            form.submit();
        }

        // ── Report Issue modal (v7.11) ──────────────────────────────────
        function openReportModal() {
            showModal('reportModal', 'input[name="category"]:checked, input[name="category"]');
        }

        function closeReportModal() {
            hideModal('reportModal');
        }

        document.getElementById('reportModal').addEventListener('click', function(e) {
            if (e.target === this) closeReportModal();
        });

        // Bumalik sa Housekeeping tab pagkatapos ng Start/Done/Report —
        // kung hindi ay itinatapon ang staff pabalik sa Today tab.
        @if (session('tab') === 'housekeeping' || $errors->issueReport->any())
            switchTab('housekeeping');
        @endif
        @if ($errors->issueReport->any())
            openReportModal();
        @endif

        // ── Live Issue Reports (urgent banner + card + bilang) ─────────
        // Ang server ang nagre-render (staff.frontdesk.housekeeping, parehong
        // partial ng page) — walang kopya ng listahan sa JS. Signal:
        // `issues.changed` mula sa IssueReport hooks; salo: bawat 60s.
        //
        // The Tasks from Admin card rides the same refetch. A task has no
        // `issues.changed` of its own; what announces one is the
        // `frontdesk.updated` toast with a `task_…` action (sent, started,
        // done, cancelled), so that is its signal here.
        (function() {
            const LIVE_URL = @json(route('staff.frontdesk.housekeeping'));
            const urgentEl = document.getElementById('fdUrgentLive');
            const reportsEl = document.getElementById('fdIssueReportsLive');
            const tasksEl = document.getElementById('fdTasksLive');
            // Hinahanap sa bawat update, hindi iniimbak: ang stats row ay
            // pinapalitan ng Today refetch sa ibaba, kaya ang naunang
            // sanggunian sa `fdHousekeepingStat` ay matatanggal sa DOM at
            // titigil na sa pag-update ang bilang.
            const countEls = () => [document.getElementById('fdHousekeepingStat'), document.getElementById('fdHousekeepingTabCount')];
            let timer = null;
            let inFlight = false;

            function swap(el, html) {
                if (el && html !== undefined && el.innerHTML.trim() !== html.trim()) el.innerHTML = html;
            }

            function refresh() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    if (inFlight) return;
                    inFlight = true;
                    fetch(LIVE_URL, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        })
                        .then(res => { if (!res.ok) throw new Error(res.status); return res.json(); })
                        .then(data => {
                            swap(urgentEl, data.urgent_html);
                            swap(reportsEl, data.reports_html);
                            swap(tasksEl, data.tasks_html);
                            countEls().forEach(el => { if (el) el.textContent = data.count; });
                        })
                        .catch(() => {})
                        .finally(() => { inFlight = false; });
                }, 300);
            }

            function listen(channel) {
                if (channel && !channel.__fdIssues) {
                    channel.__fdIssues = true;
                    channel.bind('issues.changed', refresh);
                    channel.bind('frontdesk.updated', function(data) {
                        if (/^task_/.test((data && data.action) || '')) refresh();
                    });
                }
            }

            listen(window.rtStaffChannel);
            document.addEventListener('staff:realtime-ready', e => listen(e.detail.channel));

            setInterval(() => { if (!document.hidden) refresh(); }, 60000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        })();

        // ── Live Today list + stats (v7.19) ───────────────────────────
        // Ang mga pagbabagong walang gumagawa sa page na ito ang sinasalo
        // nito: ang `bookings:auto-checkinout` (auto check-in sa takdang
        // oras, auto check-out sa checkout), ang bayad na dumating sa
        // PayMongo webhook, at ang aksiyon ng ibang staff. Dati, kailangan
        // pang i-refresh ang page bago makita ang alinman sa mga ito.
        //
        // Signal: `availability.changed` (ipinapadala ng Booking hooks sa
        // bawat pagbabago ng status/petsa/oras — pati ng console command)
        // at `frontdesk.updated`. Salo: bawat 60s, dahil ang oras mismo
        // ang nagpapalit ng ilang row (hold na mag-e-expire, slot na
        // lumipas) at walang event para doon.
        (function() {
            const LIVE_URL = @json(route('staff.frontdesk.today'));
            const listEl = document.getElementById('fdTodayLive');
            const statsEl = document.getElementById('fdStatsLive');
            const todayCountEl = document.getElementById('fdTodayTabCount');
            const pendingCountEl = document.getElementById('fdPendingTabCount');
            // Everything else a booking change moves: the Awaiting Payment
            // rows, the villa strip, and the count beside Frontdesk in the
            // sidebar. With these in the refetch, nothing on the page is left
            // waiting for a manual reload.
            const pendingEl = document.getElementById('fdPendingLive');
            const villaEl = document.getElementById('fdVillaLive');
            const navBadgeEl = document.getElementById('fdNavBadge');
            let timer = null;
            let inFlight = false;

            function swap(el, html) {
                if (el && html !== undefined && el.innerHTML.trim() !== html.trim()) el.innerHTML = html;
            }

            function refresh() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    if (inFlight) return;
                    inFlight = true;
                    fetch(LIVE_URL, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        })
                        .then(res => { if (!res.ok) throw new Error(res.status); return res.json(); })
                        .then(data => {
                            swap(listEl, data.today_html);
                            swap(statsEl, data.stats_html);
                            swap(pendingEl, data.pending_html);
                            swap(villaEl, data.villa_html);
                            if (todayCountEl) todayCountEl.textContent = data.count;
                            if (pendingCountEl) pendingCountEl.textContent = data.pending_count;
                            if (navBadgeEl && data.badge_count !== undefined) {
                                navBadgeEl.textContent = data.badge_count;
                                navBadgeEl.hidden = !data.badge_count;
                            }
                        })
                        .catch(() => {})
                        .finally(() => { inFlight = false; });
                }, 300);
            }

            function listen(channel) {
                if (channel && !channel.__fdToday) {
                    channel.__fdToday = true;
                    channel.bind('availability.changed', refresh);
                    channel.bind('frontdesk.updated', refresh);
                }
            }

            listen(window.rtStaffChannel);
            document.addEventListener('staff:realtime-ready', e => listen(e.detail.channel));

            setInterval(() => { if (!document.hidden) refresh(); }, 60000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        })();
    </script>
@endpush

