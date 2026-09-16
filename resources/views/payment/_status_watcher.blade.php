{{--
    Payment status watcher — ipinapasok ng checkout at ng success page.

    ANG PROBLEMANG NILULUTAS NITO
    Asynchronous ang QR Ph. Nakikita ng guest ang QR sa isang device at
    ini-scan ito sa iba, kaya ang success callback ay maaaring HINDI
    KAILANMAN tumakbo — ang webhook ang tunay na tagatala ng bayad, at
    maaaring dumating iyon makalipas ang ilang minuto. Bago ito, ang
    nakikita ng guest sa mga sandaling iyon ay isang page na nagsasabing
    hindi pa sila bayad, at wala silang paraan para malaman kung tapos
    na — maliban sa pag-refresh nang paulit-ulit.

    DALAWA ANG PARAAN, SADYA
    1. Polling — ito ang BACKBONE. Nagtatanong sa payment.status, na
       nagbabasa mismo sa database. Gumagana ito kahit walang Pusher
       (BROADCAST_CONNECTION=log ang default sa .env.example), kahit
       patay ang websocket, at kahit sarado pa ang tab noong dumating
       ang bayad.
    2. Pusher — ACCELERATOR lang. Kapag may dumating na event,
       nagtatanong agad sa halip na hintayin ang susunod na tick, kaya
       halos kaagad ang update. HINDI pinagkakatiwalaan ang laman ng
       event: ang database pa rin ang sumasagot. Kaya ligtas kung
       maantala, madoble, o tuluyang mawala ito.

    Ang kabaligtaran — Pusher lang — ay nangangahulugang tahimik na
    nasisira ang page tuwing may problema sa websocket, at pera ang
    ipinapakita nito.

    INILALABAS NA EVENTS (sa `document`):
      villa:payment-state     — bawat matagumpay na tanong, may sariwang
                                figures sa `event.detail`
      villa:payment-received  — MINSAN lang, kapag tumaas ang amount_paid
                                mula sa halagang na-render sa page
--}}
@once
    @push('scripts')
        @if (config('broadcasting.connections.pusher.key'))
            <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
        @endif
        <script>
            (function () {
                const STATUS_URL = @json(route('payment.status', $booking));
                const BOOKING_ID = @json($booking->id);
                const PUSHER_KEY = @json(config('broadcasting.connections.pusher.key'));
                // Ang config default ay hindi sumasaklaw sa NULL — umiiral
                // ang key, walang laman ang PUSHER_APP_CLUSTER. Kaya dito
                // ang huling salo.
                const PUSHER_CLUSTER = @json(config('broadcasting.connections.pusher.options.cluster')) || 'ap1';
                const AUTH_ENDPOINT = @json(url('/broadcasting/auth'));

                // Ang halagang na-render sa page. Ang pagtaas mula rito
                // ang ibig sabihin ng "may dumating na bagong bayad" —
                // hindi ang "amount_paid > 0", dahil maaaring nagbabayad
                // ng natitirang balanse ang guest at may naunang deposit
                // na matagal nang naitala.
                const BASELINE_PAID = Number(@json((float) $booking->amount_paid));

                // Pabagal nang pabagal habang tumatagal. Sa unang mga
                // minuto nangyayari ang halos lahat ng QR Ph settlement,
                // kaya doon mabilis; pagkatapos, wala nang saysay ang
                // pagtatanong kada apat na segundo.
                const SCHEDULE = [
                    { until: 2 * 60 * 1000, every: 4000 },
                    { until: 10 * 60 * 1000, every: 10000 },
                    { until: 20 * 60 * 1000, every: 30000 },
                ];

                // Settle phase — pagkatapos unang makita ang bayad.
                //
                // Hindi iisang atomic na hakbang ang recordPaymongoPayment():
                // naitatala ang bayad at nire-recompute ang amount_paid
                // NAUNA sa confirmOnFirstPayment(), at may ilang segundong
                // email at notification sa pagitan. Napatunayan sa browser:
                // tumama ang isang poll sa pagitan nito, nabasa ang
                // `amount_paid` na bago pero `status` na 'pending' pa, at
                // tumigil ang watcher — kaya nagsabi ang header ng "confirmed"
                // habang "Pending" ang Booking Status sa ilalim nito.
                //
                // Ang pagtaas ng amount_paid ay nangangahulugang tapos na ang
                // recompute (iyon ang nagsusulat nito), kaya ang TANGING
                // natitirang hindi tiyak ay ang status. Nagtatanong pa rin
                // hanggang umalis sa 'pending', o hanggang mapagod ang
                // bilang — may booking na nananatiling pending nang
                // lehitimo (tumanggi ang confirmOnFirstPayment() dahil
                // nakuha na ang slot), at hindi ito dapat tanungin magpakailanman.
                const SETTLE_EVERY = 3000;
                const SETTLE_POLLS = 4;

                const startedAt = Date.now();
                let timer = null;
                let inFlight = false;
                let announced = false;
                let stopped = false;
                let settleLeft = 0;

                function currentInterval() {
                    const elapsed = Date.now() - startedAt;
                    for (const step of SCHEDULE) {
                        if (elapsed < step.until) return step.every;
                    }
                    return null; // lampas na sa huling hakbang: tigil na
                }

                function stop() {
                    stopped = true;
                    if (timer) clearTimeout(timer);
                    timer = null;
                }

                function schedule() {
                    if (stopped) return;

                    const every = currentInterval();
                    if (every === null) return stop();

                    if (timer) clearTimeout(timer);
                    timer = setTimeout(poll, every);
                }

                // `force` ay para sa Pusher nudge: maaaring dumating ang event
                // NAPAKATAPOS tumigil ang polling (ipinapadala ito sa dulo ng
                // recordPaymongoPayment(), matapos ang email), at iyon ang
                // sandaling pinal na ang estado. Kung hindi ito sasagutin,
                // ang pinakamapagkakatiwalaang abiso ay binabalewala.
                function poll(force) {
                    if ((stopped && !force) || inFlight) return;

                    // Walang saysay ang pagtatanong habang nasa ibang tab
                    // ang guest — wala namang makakakita ng update. Muli
                    // itong magtatanong AGAD pagbalik nila sa tab.
                    if (document.hidden) return schedule();

                    inFlight = true;

                    fetch(STATUS_URL, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    })
                        .then(function (res) {
                            if (!res.ok) throw new Error('status ' + res.status);
                            return res.json();
                        })
                        .then(function (data) {
                            document.dispatchEvent(
                                new CustomEvent('villa:payment-state', { detail: data })
                            );

                            if (!announced && Number(data.amount_paid) > BASELINE_PAID) {
                                announced = true;
                                settleLeft = SETTLE_POLLS;
                                document.dispatchEvent(
                                    new CustomEvent('villa:payment-received', { detail: data })
                                );
                            }

                            if (announced) {
                                if (data.booking_status !== 'pending' || settleLeft <= 0) {
                                    stop();
                                    return;
                                }
                                settleLeft--;
                                if (timer) clearTimeout(timer);
                                timer = setTimeout(function () { poll(true); }, SETTLE_EVERY);
                                return;
                            }

                            schedule();
                        })
                        .catch(function () {
                            // Isang sablay na tanong ay hindi dahilan para
                            // sumuko — pansamantalang pagkawala ng signal
                            // ang pinakakaraniwang sanhi, at iyon mismo ang
                            // sitwasyong kailangan itong umulit.
                            schedule();
                        })
                        .finally(function () {
                            inFlight = false;
                        });
                }

                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden && !stopped) poll();
                });

                schedule();

                // ── Accelerator ─────────────────────────────────────
                if (PUSHER_KEY && typeof Pusher !== 'undefined') {
                    try {
                        const pusher = new Pusher(PUSHER_KEY, {
                            cluster: PUSHER_CLUSTER,
                            channelAuthorization: {
                                endpoint: AUTH_ENDPOINT,
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                },
                            },
                        });

                        pusher
                            .subscribe('private-booking-payment.' + BOOKING_ID)
                            .bind('payment.received', function (data) {
                                // Ang refund ay bayad ding naitatala, pero
                                // hindi ito ang hinihintay ng guest dito.
                                if (data && data.is_refund) return;

                                // Hindi binabasa ang halaga mula sa event.
                                // Ang tanging sinasabi nito ay "may bago
                                // nang mababasa" — ang database ang sasagot.
                                poll(true);
                            });
                    } catch (e) {
                        // Ang polling ang backbone; buhay pa rin ang page
                        // kahit tuluyang hindi umandar ang websocket.
                        console.warn('Realtime payment updates unavailable; falling back to polling.', e);
                    }
                }
            })();
        </script>
    @endpush
@endonce
