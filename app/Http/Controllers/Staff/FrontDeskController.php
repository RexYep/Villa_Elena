<?php

namespace App\Http\Controllers\Staff;

use App\Events\FrontdeskUpdated;
use App\Events\PropertyAvailabilityChanged;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Property;
use App\Models\StaffLog;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;
use App\Helpers\BookingMailHelper;
use Illuminate\Support\Facades\Auth;


class FrontDeskController extends Controller
{
    // ── Main Frontdesk View ────────────────────────────────────────
    public function index()
    {
        $today = today();

        $checkIns = Booking::where('check_in_date', $today)
            ->where('status', 'confirmed')
            ->with(['property', 'user'])
            ->orderBy('created_at')
            ->get();

        $checkOuts = Booking::where('check_out_date', $today)
            ->where('status', 'checked_in')
            ->with(['property', 'user'])
            ->orderBy('check_out_date')
            ->get();

        $currentGuests = Booking::where('status', 'checked_in')
            ->with(['property', 'user'])
            ->orderBy('check_out_date')
            ->get();

        $pendingBookings = Booking::where('status', 'pending')
            ->with(['property', 'user'])
            ->latest()
            ->take(10)
            ->get();

        $pendingTasks = $this->attachCleaningDeadlines(
            HousekeepingTask::where('status', 'pending')
                ->with(['property', 'booking'])
                ->orderBy('scheduled_date')
                ->get()
        );

        $inProgressTasks = $this->attachCleaningDeadlines(
            HousekeepingTask::where('status', 'in_progress')
                ->with(['property', 'booking'])
                ->get()
        );

        // Ang pinaka-madaliang task — ito ang ipinapakita sa banner sa
        // itaas ng page. Naiipon dati ang mga task dahil kailangan pang
        // pumunta sa hiwalay na tab para makita at maisara ang mga ito;
        // 11 sa 13 ang naging overdue, 6 ang naipit sa "in progress"
        // dahil binabaligtad sila ng auto-checkout pero walang pumipindot
        // ng "Complete". Sa banner, hindi na ito mapapalampas.
        $urgentTask = collect($pendingTasks)
            ->merge($inProgressTasks)
            ->filter(fn ($t) => $t->ready_by !== null)
            ->sortBy('ready_by')
            ->first();

        // Villa lang ang ipinapakita sa frontdesk. Ang mga `type=room`
        // na record ay info-only na simula v4.0 (hindi na hiwalay na
        // bookable, at wala na ngang pangalan) — lumalabas dati bilang
        // mga blangkong card dito, at binibilang pa sa "Available" na
        // stat, kaya mukhang may 3 pang bakanteng unit gayong isa lang
        // talaga ang pwedeng i-book at kuha na ito.
        $villa = Property::with(['currentBooking.user'])
            ->where('type', 'villa')
            ->first();

        // Villa para sa walk-in form — Villa lang, hindi yung mga
        // individual Room A-F (info-only records na sila simula v4.0,
        // hindi na hiwalay na bookable). Bug fix: hindi dapat
        // `status = 'available'` ang gamit dito — ang `status` column
        // ay REAL-TIME occupancy lang (naka-flip kapag may naka-check-in
        // NGAYON), hindi date-specific availability. Kapag may naka-
        // check-in kahit ngayon lang, mawawala ang Villa sa buong form
        // kahit anong PETSA/SLOT ang gustong i-book ng staff — hindi na
        // naaabot pa ang tamang date/slot check (`Booking::hasConflict()`
        // sa `storeWalkin()`). Ang totoong dapat i-exclude dito ay
        // `maintenance` lang (deliberate block ng admin), hindi
        // `occupied` (pansamantalang estado lang, hindi hadlang sa
        // pag-book ng ibang petsa/slot).
        $availableProperties = Property::where('status', '!=', 'maintenance')
            ->where('type', 'villa')
            ->orderBy('property_name')
            ->get();

        // Susunod na dalawang slot mula ngayon — ito ang pinakamadalas
        // na tanong sa frontdesk ("libre pa ba mamaya/bukas?"), kaya
        // nasa stats row na mismo imbes na kailangan pang pumunta sa
        // Availability page.
        $nextSlots = $villa ? $this->buildSlotGrid($villa, today(), 2) : [];

        $stats = [
            'check_ins_today'  => $checkIns->count(),
            'check_outs_today' => $checkOuts->count(),
            'pending_tasks'    => $pendingTasks->count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
        ];

        return view('staff.frontdesk', compact(
            'checkIns', 'checkOuts', 'currentGuests', 'pendingBookings',
            'pendingTasks', 'inProgressTasks', 'urgentTask', 'villa',
            'availableProperties', 'stats', 'nextSlots'
        ));
    }

    /**
     * Nilalagyan ang bawat housekeeping task ng TOTOONG deadline nito:
     * ang oras ng susunod na check-in pagkatapos ng checkout na siyang
     * pinagmulan ng task.
     *
     * Petsa lang ang ipinapakita dati ("Scheduled: Aug 16"), na halos
     * walang silbi sa modelong ito — dalawang oras lang ang pagitan ng
     * checkout at ng susunod na check-in (5PM→7PM at 6AM→8AM). Ang
     * mahalagang tanong ay hindi "anong araw" kundi "ilang oras pa bago
     * dumating ang susunod na bisita" — at dahil isang villa lang ito,
     * walang ibang unit na mapaglilipatan kung hindi handa sa oras.
     */
    private function attachCleaningDeadlines($tasks)
    {
        if ($tasks->isEmpty()) {
            return $tasks;
        }

        // Isang query lang para sa lahat ng paparating na booking, sa
        // halip na isa kada task.
        $upcoming = Booking::whereIn('property_id', $tasks->pluck('property_id')->unique()->all())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '>=', today()->subDays(60))
            ->orderBy('check_in_date')
            ->orderBy('check_in_time')
            ->get();

        foreach ($tasks as $task) {
            // Sukatan: ang checkout ng booking na nagpasimula ng task.
            // Kung wala (manual na task), ang due_date na lang.
            $after = $task->booking
                ? $task->booking->checkOutDateTime()
                : ($task->due_date ? \Carbon\Carbon::parse($task->due_date)->endOfDay() : null);

            $next = $after
                ? $upcoming->first(fn ($b) =>
                    $b->property_id === $task->property_id
                    && $b->id !== $task->booking_id
                    && $b->checkInDateTime()->gte($after))
                : null;

            $task->ready_by   = $next?->checkInDateTime();
            $task->next_guest = $next?->user->full_name ?? null;
        }

        return $tasks;
    }

    // ── Availability (slot grid) ───────────────────────────────────
    // Sinasagot ang tanong na hindi kayang sagutin ng admin calendar:
    // "libre ba ang Villa sa petsang ito — Day ba o Night?". Ang admin
    // calendar ay naka-base sa check_in_date → check_out_date, kaya
    // bulag ito sa slot; kailangan pang i-click ang bawat booking bago
    // malaman kung aling kalahati ng araw ang kuha.
    public function availability(Request $request)
    {
        $villa = Property::where('type', 'villa')->first();

        abort_if(! $villa, 404, 'No villa record found.');

        $days = 14;

        // Naka-clamp sa ngayon pababa — walang saysay sa frontdesk ang
        // makakita ng availability sa nakaraan.
        $start = $request->filled('start')
            ? \Carbon\Carbon::parse($request->start)->startOfDay()
            : today();

        if ($start->lt(today())) {
            $start = today();
        }

        $grid = $this->buildSlotGrid($villa, $start, $days);

        return view('staff.availability', [
            'villa'    => $villa,
            'grid'     => $grid,
            'start'    => $start,
            'end'      => $start->copy()->addDays($days - 1),
            'days'     => $days,
            'prevDate' => $start->copy()->subDays($days)->lt(today())
                ? null
                : $start->copy()->subDays($days)->format('Y-m-d'),
            'nextDate' => $start->copy()->addDays($days)->format('Y-m-d'),
        ]);
    }

    /**
     * Bumubuo ng slot-by-slot availability grid para sa Villa.
     *
     * Sinasadyang dumaan sa `Booking::hasConflict()` bawat slot sa halip
     * na magsulat ng sariling overlap query — iisang panuntunan lang ang
     * susundin ng ipinapakita dito at ng aktwal na pag-book. Kung
     * magbabago ang conflict rules (hal. expired pending holds), susunod
     * agad ang grid nang hindi kailangang baguhin dito.
     */
    private function buildSlotGrid(Property $villa, \Carbon\Carbon $start, int $days): array
    {
        // Isang query lang para sa lahat ng block sa saklaw — hindi
        // kasama sa hasConflict() ang AvailabilityBlock, kaya hiwalay
        // itong sinasala dito.
        $rangeEnd = $start->copy()->addDays($days);

        $blocks = AvailabilityBlock::where('property_id', $villa->id)
            ->where('start_date', '<=', $rangeEnd)
            ->where('end_date', '>=', $start)
            ->get();

        $grid = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $slots = [];

            foreach (array_keys(Booking::SLOTS) as $slotKey) {
                [$checkin, $checkout] = Booking::slotDateTimes($slotKey, $date->format('Y-m-d'));

                $block = $blocks->first(fn ($b) =>
                    $date->betweenIncluded($b->start_date, $b->end_date));

                if ($block) {
                    $state  = 'blocked';
                    $label  = ucfirst(str_replace('_', ' ', $block->reason));
                    $guest  = null;
                } elseif ($checkin->isPast()) {
                    // Lumagpas na ang check-in oras ng slot — hindi na ito
                    // maibe-book pa, kahit walang kumuha.
                    $state = 'past';
                    $label = 'Passed';
                    $guest = null;
                } elseif (Booking::hasConflict($villa->id, $checkin, $checkout)) {
                    $taken = Booking::where('property_id', $villa->id)
                        ->whereNotIn('status', ['cancelled', 'no_show'])
                        ->where('check_out_date', '>=', $checkin->copy()->subDay())
                        ->where('check_in_date', '<=', $checkout->copy()->addDay())
                        ->with('user')
                        ->get()
                        ->first(fn ($b) =>
                            $checkin->lt($b->checkOutDateTime()) && $checkout->gt($b->checkInDateTime()));

                    $state = 'booked';
                    $label = $taken->booking_ref ?? 'Booked';
                    $guest = $taken->user->full_name ?? 'Guest';
                } else {
                    $state = 'free';
                    $label = 'Available';
                    $guest = null;
                }

                // Ipinapakita ang presyong AKTWAL na sisingilin (may bawas
                // na kung may tumatamang promo), hindi ang list price —
                // ito ang sinasabi ni staff sa guest sa telepono/counter,
                // kaya dapat tugma ito sa lalabas sa walk-in form.
                $quote = $state === 'free' ? $villa->quoteFor($checkin, $slotKey) : null;

                $slots[$slotKey] = [
                    'state'    => $state,
                    'label'    => $label,
                    'guest'    => $guest,
                    'price'    => $quote['total'] ?? null,
                    'base'     => $quote['base'] ?? null,
                    'promo'    => $quote['promo']->label ?? null,
                    'check_in' => $checkin,
                ];
            }

            $grid[] = [
                'date'     => $date,
                'is_today' => $date->isToday(),
                'slots'    => $slots,
            ];
        }

        return $grid;
    }

    // ── Walk-in Booking Form Page ──────────────────────────────────
    /**
     * Server-side na presyo para sa walk-in form (AJAX).
     *
     * Dating kinukuwenta ito sa BROWSER mula sa `data-base`/`data-weekend`
     * ng property — isang pangalawang kopya ng Property::getPackagePrice()
     * na nakasulat sa JavaScript. Kayang-kaya niyang lumihis: hindi nito
     * nakikita ang pricing-rule overrides (inaamin ito ng dating komento
     * doon), at simula nang may seasonal promo, hindi rin nito nakikita
     * ang bawas. Iyon ay hindi lang maling numero sa screen — ang
     * overpayment guard sa JS ay sumusukat laban sa halagang iyon, kaya
     * tatanggapin niya ang isang bayad na tatanggihan naman ng server.
     *
     * Wala rito ang `$checkin->isPast()` na tanggihan na ginagawa ng
     * Portal\PortalController::pricePreview(): puwede pa ring mag-check-in
     * ang isang walk-in sa kalagitnaan ng slot (hal. 10AM para sa Day
     * slot na nagsimula 8AM) — ang CHECK-OUT na ang hangganan doon,
     * gaya ng ipinapatupad ng storeWalkin().
     */
    public function priceQuote(Request $request)
    {
        $validator = validator($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'checkin'     => 'required|date',
            'slot'        => 'required|in:' . implode(',', array_keys(Booking::SLOTS)),
        ]);

        if ($validator->fails()) {
            return response()->json(['valid' => false], 422);
        }

        $property = Property::findOrFail($request->property_id);
        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->checkin);

        $quote = $property->quoteFor($checkin, $request->slot);
        $isPeak = in_array($checkin->dayOfWeek, [5, 6]) || ($checkin->dayOfWeek === 0 && $checkin->format('H:i') < '18:00');

        return response()->json([
            'valid'       => true,
            'is_peak'     => $isPeak,
            'hours'       => round($checkin->diffInHours($checkout), 1),
            'day_label'   => $checkin->format('D, M j'),
            'base'        => $quote['base'],
            'discount'    => $quote['discount'],
            'total'       => $quote['total'],
            'promo_label' => $quote['promo']?->label,
            'promo_value' => $quote['promo']?->value_label,
        ]);
    }

    public function walkinForm(Request $request)
    {
        // Villa lang, hindi yung mga individual Room A-F (info-only
        // records na sila simula v4.0, hindi na hiwalay na bookable).
        // Bug fix: `!= maintenance` hindi `= available` — see index()
        // comment sa itaas para sa buong paliwanag.
        $availableProperties = Property::where('status', '!=', 'maintenance')
            ->where('type', 'villa')
            ->orderBy('property_name')
            ->get();

        $customers = User::where('role', 'customer')
            ->orderBy('full_name')
            ->get();

        // Prefill mula sa Availability page — kapag pinindot ni staff
        // ang isang libreng slot doon, dumadating dito ang petsa/slot na
        // iyon para hindi na kailangang i-type ulit (at para hindi
        // mapalitan ng aksidente ng ibang slot na kuha na pala).
        $prefill = [
            'date' => $request->filled('date')
                ? \Carbon\Carbon::parse($request->date)->format('Y-m-d')
                : null,
            'slot' => in_array($request->slot, array_keys(Booking::SLOTS))
                ? $request->slot
                : null,
        ];

        return view('staff.walkin', compact('availableProperties', 'customers', 'prefill'));
    }

    // ── Store Walk-in Booking ──────────────────────────────────────
    public function storeWalkin(Request $request)
    {
        $request->validate([
            'guest_type'       => 'required|in:existing,new',
            'user_id'          => 'required_if:guest_type,existing|nullable|exists:users,id',
            'full_name'        => 'required_if:guest_type,new|nullable|string|max:255',
            // create_account: kung gagawa ba ng totoong login account ang
            // bagong guest, o guest record lang (walang login access).
            // Email required lang kapag talagang gagawa ng account —
            // kailangan doon ang email para makapadala ng password-reset
            // link. Hindi ito nakakaapekto sa online registration
            // (AuthController::register()), na hiwalay pa ring
            // nag-e-enforce ng sarili nitong `required` na rule.
            'create_account'   => 'required_if:guest_type,new|nullable|in:yes,no',
            'email'            => 'required_if:create_account,yes|nullable|email|unique:users,email',
            'phone'            => 'nullable|string|max:20',
            // property_id: dapat Villa lang ang matatanggap, kahit
            // ma-bypass ang dropdown restriction sa frontend.
            'property_id'      => 'required|exists:properties,id,type,villa',
            'check_in_date'    => 'required|date|after_or_equal:today',
            'slot'             => 'required|in:' . implode(',', array_keys(Booking::SLOTS)),
            'num_guests'       => 'required|integer|min:1',
            'special_requests' => 'nullable|string|max:500',
            'payment_amount'   => 'nullable|numeric|min:0',
            'payment_method'   => 'nullable|in:cash,qrph',
            'payment_type'     => 'nullable|in:partial,full_payment',
        ]);

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->check_in_date);

        // Walk-in: naroon na mismo ang guest, kaya hindi ito tulad ng
        // online booking na dapat i-block sa sandaling lumagpas ang
        // check-in time — normal lang na medyo huli ang pagdating (hal.
        // dumating ng 9AM para sa "Day" slot na nagsimula 8AM, may
        // natitira pa namang oras hanggang 5PM). Ang dapat lang i-block
        // ay kapag lumagpas na rin ang CHECK-OUT time ng slot mismo —
        // ibig sabihin wala nang natitirang oras sa buong package.
        if ($checkout->isPast()) {
            return back()
                ->withErrors(['check_in_date' => 'The entire ' . Booking::SLOTS[$request->slot]['label'] . ' slot has already passed for today (check-out time has elapsed). Please select a different date or slot.'])
                ->withInput();
        }

        // Kunin ang property + presyo BAGO pumasok sa transaction, para
        // ma-validate na natin ang payment_amount laban sa totoong total
        // bago pa gumawa ng kahit anong record.
        $property = Property::findOrFail($request->property_id);

        // Awtomatiko ring tumatama ang seasonal promo sa walk-in — hindi
        // ito bagay na pinipili ni staff. Ang `total` ang batayan ng
        // lahat ng pagsusuri sa bayad sa ibaba; ang `base` ay itinatala
        // lang bilang listahang presyo bago ang bawas.
        $quote          = $property->quoteFor($checkin, $request->slot);
        $baseAmount     = $quote['base'];
        $discountAmount = $quote['discount'];
        $totalAmount    = $quote['total'];
        $promo          = $quote['promo'];
        $amountPaid     = (float) ($request->payment_amount ?? 0);

        // Overpayment guard — hindi puwedeng lumagpas sa total ang
        // ire-record na "amount received". Kung may sukli, i-record na
        // lang ang netong natanggap (hal. binayaran ₱5,000, sukli ₱1,000
        // → i-type na lang ₱4,000), hindi ang buong ₱5,000.
        if ($amountPaid > $totalAmount) {
            return back()
                ->withErrors(['payment_amount' => 'Ang natanggap na bayad (₱' . number_format($amountPaid, 2) . ') ay lampas sa kabuuang halaga (₱' . number_format($totalAmount, 2) . '). Kung may sukli, i-type na lang ang netong natanggap.'])
                ->withInput();
        }

        // Kung may inilagay na halaga pero walang napiling paraan ng
        // bayad, hindi nabubuo ang Payment record sa ibaba — pero
        // naitatala pa rin sa booking ang amount_paid. Nagreresulta ito
        // ng booking na sinasabing bayad na pero walang kahit isang
        // payment row: hindi kasama sa revenue reports, at hindi tugma
        // ang dalawang pinagmumulan ng katotohanan. (May isang totoong
        // booking na ganito ang nangyari — VE-OLRWMOGX.)
        if ($amountPaid > 0 && ! $request->payment_method) {
            return back()
                ->withErrors(['payment_method' => 'Pumili ng paraan ng bayad — kailangan ito para maitala nang maayos ang ₱' . number_format($amountPaid, 2) . ' na natanggap.'])
                ->withInput();
        }

        // Availability check — dating wala nito, kaya posibleng
        // ma-double-book ang buong Villa.
        //
        // Dating may sariling DB::transaction + lockForUpdate() dito ang
        // walk-in — pero mga `bookings` row ang nilo-lock nito, at
        // umaasa sa gap-lock na gawi ng InnoDB para pigilan ang isang
        // row na WALA PA. Mas masahol: ito lang ang path na kumukuha ng
        // lock, at walang saysay ang lock na iisa lang ang humahawak —
        // kayang lampasan ito ng online booking o ng admin create.
        // Iisa na ngayon ang pinagdadaanan ng lahat, at ang nilo-lock ay
        // ang tunay na `properties` row. Tingnan ang Booking::reserveSlot().
        $booking = Booking::reserveSlot($request->property_id, $checkin, $checkout, function () use ($request, $checkin, $checkout, $property, $baseAmount, $discountAmount, $totalAmount, $promo, $amountPaid) {

            // Create new guest if needed
            if ($request->guest_type === 'new') {
                // Laging may bagong "guest record" (User row, role=customer)
                // na ginagawa dito — kailangan ito para sa booking/payment/
                // review history, kahit hindi gustong gumawa ng totoong
                // login account ang guest. Random/unguessable pa rin ang
                // password kahit anong choice, dahil NOT NULL ang column —
                // walang epekto ito sa sinuman dahil hindi ito ibinibigay
                // sa guest, at wala pang password-reset na ipapadala kung
                // hindi naman gusto ng account access.
                $tempPassword = \Illuminate\Support\Str::random(20);

                $user = User::create([
                    'full_name' => $request->full_name,
                    'email'     => $request->create_account === 'yes' ? $request->email : null,
                    'phone'     => $request->phone,
                    'password'  => bcrypt($tempPassword),
                    'role'      => 'customer',
                    'status'    => 1,
                ]);

                // Password-reset email ipinapadala lang kung talagang
                // pumili ang guest na gumawa ng login account — kung
                // "guest record lang" ang pinili, walang dapat asahan
                // na email/undangan ng access.
                if ($request->create_account === 'yes') {
                    // Don't let a mail hiccup block the walk-in booking
                    // itself (this runs inside the transaction below) — the
                    // guest can still use "forgot password" later if this
                    // email doesn't land.
                    try {
                        \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to send walk-in password reset link: ' . $e->getMessage());
                    }
                }
            } else {
                $user = User::findOrFail($request->user_id);
            }

            $nights = max(1, $checkin->diffInDays($checkout));

            $balanceDue = $totalAmount - $amountPaid;

            $paymentStatus = 'unpaid';
            if ($amountPaid >= $totalAmount) $paymentStatus = 'paid';
            elseif ($amountPaid > 0)         $paymentStatus = 'partial';

            // Auto-determine payment type — kung binayaran nang buo,
            // "full_payment"; kung hindi, "partial". Hindi na umaasa sa
            // manual na pinili ni staff, para maiwasan ang human error.
            $paymentType = $amountPaid >= $totalAmount ? 'full_payment' : 'partial';

            $booking = Booking::create([
                'user_id'          => $user->id,
                'property_id'      => $property->id,
                'check_in_date'    => $checkin->format('Y-m-d'),
                'check_in_time'    => $checkin->format('H:i:s'),
                'check_out_date'   => $checkout->format('Y-m-d'),
                'check_out_time'   => $checkout->format('H:i:s'),
                'num_nights'       => $nights,
                'num_guests'       => $request->num_guests,
                'base_amount'      => $baseAmount,
                'extras_amount'    => 0,
                'discount_amount'  => $discountAmount,
                'discount_id'      => $promo?->id,
                'total_amount'     => $totalAmount,
                'amount_paid'      => $amountPaid,
                'balance_due'      => max(0, $balanceDue),
                'status'           => 'confirmed',
                'payment_status'   => $paymentStatus,
                'source'           => 'walk_in',
                'special_requests' => $request->special_requests,
            ]);

            $promo?->increment('used_count');

            NotificationHelper::walkInBooking($booking->load(['user', 'property']), Auth::user()->full_name);

            // Record payment if any amount was paid
            if ($amountPaid > 0 && $request->payment_method) {
                Payment::create([
                    'booking_id'     => $booking->id,
                    'amount'         => $amountPaid,
                    'payment_method' => $request->payment_method,
                    'payment_type'   => $paymentType,
                    'status'         => 'success',
                    'payment_date'   => today(),
                    'received_by'    => Auth::id(),
                    'notes'          => 'Walk-in payment recorded by ' . Auth::user()->full_name,
                ]);
            }

            Notification::create([
                'user_id' => $user->id,
                'type'    => 'in_app',
                'title'   => 'Booking Confirmed!',
                'message' => "Your walk-in booking {$booking->booking_ref} for {$property->property_name} has been confirmed. Check-in: {$checkin->format('M d, Y g:i A')}.",
                'link'    => route('customer.bookings.show', $booking, false),
                'is_read' => 0,
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            StaffLog::record('walkin_booking', 'bookings', $booking->id,
                "Walk-in booking {$booking->booking_ref} created by " . Auth::user()->full_name . " for guest {$user->full_name}");

            return $booking;
        });

        if (!$booking) {
            return back()
                ->withErrors(['check_in_date' => 'Naka-book na ang Villa sa napiling petsa/slot. Pumili ng ibang slot.'])
                ->withInput();
        }

        // Realtime broadcast lang ito (admin dashboard toast) — hindi ito
        // dapat maka-block sa buong request. Nagawa na at naka-commit na
        // ang booking sa puntong ito; kung mag-fail ang Pusher (hal. mali
        // ang credentials, timeout), dapat mag-log lang tayo at ituloy pa
        // rin ang redirect papunta sa success message, hindi mag-500.
        try {
            event(new FrontdeskUpdated(
                'walkin',
                "Walk-in booking {$booking->booking_ref} created for {$booking->user->full_name} ({$booking->property->property_name}).",
                bookingId: $booking->id,
                actor: Auth::user()->full_name,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast FrontdeskUpdated (walkin): ' . $e->getMessage());
        }

        try {
            event(new PropertyAvailabilityChanged(
                $booking->property_id,
                'blocked',
                $booking->check_in_date->format('Y-m-d'),
                $booking->check_out_date->format('Y-m-d'),
                checkInTime: $booking->check_in_time ? \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') : null,
                checkOutTime: $booking->check_out_time ? \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') : null,
                bookingId: $booking->id,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast PropertyAvailabilityChanged (walkin): ' . $e->getMessage());
        }

        return redirect()->route('staff.frontdesk')
            ->with('success', "✅ Walk-in booking {$booking->booking_ref} created for {$booking->user->full_name}. " .
                ($booking->amount_paid > 0 ? "Payment of ₱" . number_format($booking->amount_paid, 2) . " recorded." : ""));
    }

    // ── Record Payment for Existing Booking ───────────────────────
    public function recordPayment(Request $request, Booking $booking)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,qrph',
            'payment_type'   => 'required|in:full_payment,partial,balance',
            'notes'          => 'nullable|string|max:300',
        ]);

        Payment::create([
            'booking_id'     => $booking->id,
            'amount'         => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_type'   => $request->payment_type,
            'status'         => 'success',
            'payment_date'   => today(),
            'received_by'    => Auth::id(),
            'notes'          => $request->notes ?? 'Recorded by ' . Auth::user()->full_name,
        ]);

        $booking->recalculateFinancials();
        // Kung hindi ito tatawagin, mananatiling 'pending' ang booking
        // kahit may natanggap nang pera — at kakanselahin ito ng stale
        // pending sweeper.
        $wasPending = $booking->confirmOnFirstPayment();

        // Dati, ang front desk ay walang ipinapadalang email — kaya ang
        // guest na nagbayad ng balanse sa counter ay walang resibo.
        BookingMailHelper::paymentRecorded($booking, (float) $request->amount, $wasPending);

        StaffLog::record('payment_recorded', 'bookings', $booking->id,
            "Payment ₱{$request->amount} recorded for booking {$booking->booking_ref} by " . Auth::user()->full_name);

        try {
            event(new FrontdeskUpdated(
                'payment',
                "₱" . number_format($request->amount, 2) . " recorded for {$booking->booking_ref} by " . Auth::user()->full_name . ".",
                bookingId: $booking->id,
                actor: Auth::user()->full_name,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast FrontdeskUpdated (payment): ' . $e->getMessage());
        }

        return back()->with('success', "✅ Payment of ₱" . number_format($request->amount, 2) . " recorded for {$booking->booking_ref}.");
    }

    // ── Check In ──────────────────────────────────────────────────
    public function checkIn(Request $request, Booking $booking)
    {
        if ($booking->status !== 'confirmed') {
            return back()->with('error', 'Booking must be confirmed before check-in.');
        }

        // Bug fix: dating pwede i-click ang Check In button kahit hindi
        // pa ang naka-schedule na araw/oras ng guest. Ngayon, hindi na
        // ito papayagan hangga't hindi pa dumarating ang eksaktong
        // check-in datetime (ang automated na "bookings:auto-checkinout"
        // command na ang bahalang mag-check-in nang eksakto sa oras).
        if ($booking->checkInDateTime()->isFuture()) {
            return back()->with('error', 'It is not yet time for check-in for this booking — it is scheduled for ' . $booking->checkInDateTime()->format('M d, Y g:i A') . '. It will be automatically checked in at the correct time.');
        }

        // Kung may natitirang balance (hal. 50% deposit lang ang binayad),
        // hindi na basta-basta papayagan ang check-in nang walang malinaw
        // na desisyon si staff — kailangan munang pumili: bayaran ngayon
        // (via Payment modal, na magpapababa sa balance_due papuntang 0
        // bago pa man i-submit ang check-in form) o i-confirm explicitly
        // ang deferral (babayaran na lang bago mag-check-out).
        if ($booking->balance_due > 0) {
            $request->validate([
                'balance_arrangement' => 'required|in:deferred',
            ], [
                'balance_arrangement.required' => 'There is an outstanding balance that needs to be confirmed before checking in.',
            ]);
        }

        $booking->update(['status' => 'checked_in', 'actual_check_in' => now()]);
        $booking->property->update(['status' => 'occupied']);
        NotificationHelper::guestCheckedIn($booking->load(['user','property']));

        HousekeepingTask::create([
            'property_id'    => $booking->property_id,
            'booking_id'     => $booking->id,
            'task_type'      => 'checkout_clean',
            'due_date'       => $booking->check_out_date,
            'scheduled_date' => $booking->check_out_date,
            'status'         => 'pending',
            'notes'          => "Post-checkout cleaning for booking {$booking->booking_ref}",
        ]);

        Notification::create([
            'user_id' => $booking->user_id,
            'type'    => 'in_app',
            'title'   => 'Welcome to Villa Elena!',
            'message' => "You have successfully checked in to {$booking->property->property_name}. Enjoy your stay! Check-out: {$booking->check_out_date->format('F d, Y')}.",
            'link'    => route('customer.bookings.show', $booking, false),
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        // Audit trail — kung deferred ang balance, malinaw na nakalagay
        // dito kung sino sa staff ang nagkumpirma ng arrangement na ito.
        $balanceNote = $booking->balance_due > 0
            ? " There is an outstanding balance of ₱" . number_format($booking->balance_due, 2) . " — confirmed by " . Auth::user()->full_name . " to be paid before check-out."
            : "";

        StaffLog::record('check_in', 'bookings', $booking->id,
            "Checked in {$booking->user->full_name} for {$booking->booking_ref}." . $balanceNote);

        try {
            event(new FrontdeskUpdated(
                'checkin',
                "{$booking->user->full_name} checked in to {$booking->property->property_name} by " . Auth::user()->full_name . ".",
                bookingId: $booking->id,
                actor: Auth::user()->full_name,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast FrontdeskUpdated (checkin): ' . $e->getMessage());
        }

        $successMsg = "✅ {$booking->user->full_name} checked in to {$booking->property->property_name}.";
        if ($booking->balance_due > 0) {
            $successMsg .= " ⚠️ There is an outstanding balance of ₱" . number_format($booking->balance_due, 2) . " — must be paid before check-out.";
        }

        return back()->with('success', $successMsg);
    }

    // ── Check Out ─────────────────────────────────────────────────
    public function checkOut(Booking $booking)
    {
        if ($booking->status !== 'checked_in') {
            return back()->with('error', 'Guest must be checked in first.');
        }

        // Bug fix: dating pwede i-click ang Check Out button kahit hindi
        // pa dumarating ang naka-schedule na check-out oras ng guest.
        // Parehong guard na katulad ng ginagamit sa manual check-in —
        // hindi dapat ma-check-out ang isang booking bago pa man dumating
        // ang eksaktong oras nito.
        if ($booking->checkOutDateTime()->isFuture()) {
            return back()->with('error', 'It is not yet time for check-out for this booking — it is scheduled for ' . $booking->checkOutDateTime()->format('M d, Y g:i A') . '.');
        }

        $booking->update(['status' => 'checked_out', 'actual_check_out' => now()]);
        $booking->property->update(['status' => 'available']);
        NotificationHelper::guestCheckedOut($booking->load(['user','property']));

        HousekeepingTask::where('booking_id', $booking->id)
            ->where('task_type', 'checkout_clean')
            ->update(['status' => 'in_progress']);

        Notification::create([
            'user_id' => $booking->user_id,
            'type'    => 'in_app',
            'title'   => 'Check-out Complete',
            'message' => "Thank you for staying at Villa Elena! Booking {$booking->booking_ref} is now complete. We hope to see you again!",
            'link'    => route('customer.bookings.show', $booking, false),
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('check_out', 'bookings', $booking->id,
            "Checked out {$booking->user->full_name} for {$booking->booking_ref}");

        try {
            event(new FrontdeskUpdated(
                'checkout',
                "{$booking->user->full_name} checked out of {$booking->property->property_name} by " . Auth::user()->full_name . ".",
                bookingId: $booking->id,
                actor: Auth::user()->full_name,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast FrontdeskUpdated (checkout): ' . $e->getMessage());
        }

        // Sinasabi kung kailan dapat handa ang villa, hindi lang na
        // "activated" ang task — ito ang oras na aktwal na mahalaga sa
        // staff, at kadalasan makitid ito (2 oras sa pagitan ng slots).
        $nextIn = Booking::where('property_id', $booking->property_id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('id', '!=', $booking->id)
            ->where('check_in_date', '>=', today())
            ->orderBy('check_in_date')->orderBy('check_in_time')
            ->get()
            ->first(fn ($b) => $b->checkInDateTime()->gte($booking->checkOutDateTime()));

        $message = "✅ {$booking->user->full_name} checked out.";
        $message .= $nextIn
            ? ' Villa must be cleaned and ready by ' . $nextIn->checkInDateTime()->format('M j, g:i A') . '.'
            : ' Housekeeping task activated — no booking follows yet.';

        return back()->with('success', $message);
    }

    // ── Housekeeping Tasks ─────────────────────────────────────────
    public function startTask(HousekeepingTask $task)
    {
        $task->update(['status' => 'in_progress']);
        StaffLog::record('task_started', 'housekeeping_tasks', $task->id,
            "Started task for {$task->property->property_name}");

        try {
            event(new FrontdeskUpdated(
                'task_started',
                "Housekeeping task for {$task->property->property_name} started by " . Auth::user()->full_name . ".",
                taskId: $task->id,
                actor: Auth::user()->full_name,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast FrontdeskUpdated (task_started): ' . $e->getMessage());
        }

        return back()->with('success', "Task started.");
    }

    public function completeTask(HousekeepingTask $task)
    {
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        StaffLog::record('task_completed', 'housekeeping_tasks', $task->id,
            "Completed task for {$task->property->property_name}");

        try {
            event(new FrontdeskUpdated(
                'task_completed',
                "Housekeeping task for {$task->property->property_name} completed by " . Auth::user()->full_name . ".",
                taskId: $task->id,
                actor: Auth::user()->full_name,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast FrontdeskUpdated (task_completed): ' . $e->getMessage());
        }

        return back()->with('success', "✅ Housekeeping task marked as complete.");
    }
}