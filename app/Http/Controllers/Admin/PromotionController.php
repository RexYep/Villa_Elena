<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\StaffLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Seasonal promo management — ADMIN LANG.
 *
 * Sinasadyang nakasalalay ito sa `routes/admin.php` (`role:admin`) at
 * wala sa staff portal: ang promo ay direktang pagbaba ng kita, kaya
 * dapat iisa lang ang taong may kapangyarihang gumawa nito at bakas
 * lahat ng galaw sa `staff_logs`. Ang staff ay awtomatikong nakikinabang
 * sa promo sa walk-in booking, pero hindi sila makakagawa o makakapalit.
 */
class PromotionController extends Controller
{
    public function index()
    {
        $promos = Discount::withCount('bookings')->orderByDesc('id')->paginate(15);

        return view('admin.promotions.index', compact('promos'));
    }

    public function create()
    {
        return view('admin.promotions.form', [
            'promo'      => new Discount([
                'type'       => 'percentage',
                'value'      => Discount::DEFAULT_PERCENTAGE,
                'applies_to' => 'all',
                // Ang bagong promo ay para sa lahat hangga't hindi
                // sinasabi ni admin ang kabaligtaran, at ang threshold ay
                // 1 — ang pinakalikas na kahulugan ng "regular" ay
                // sinumang bumalik.
                'guest_scope'            => 'all',
                'min_completed_bookings' => 1,
                'is_public'  => 1,
                'is_active'  => 1,
            ]),
            'customerCount'   => $this->customerCount(),
            'returningCounts' => $this->returningCounts(),
            'rates'         => $this->villaRates(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Ang isang doble ay hindi mali, kaya nagtatanong tayo at hindi
        // tumatanggi — tingnan ang Discount::duplicateProblem(). Dito,
        // bago ang pag-create, dahil ang pinipigilan ay ang pangalawang
        // row (at ang pangalawang blast na kasama nito).
        if ($problem = Discount::duplicateProblem($data, $request->boolean('confirm_duplicate'))) {
            return back()->withErrors(['label' => $problem])->withInput();
        }

        $promo = Discount::create($data);

        StaffLog::record('created_promo', 'discounts', $promo->id,
            "Created promo '{$promo->label}' ({$promo->value_label}, {$promo->window_label})");

        $notified = $this->maybeNotifyCustomers($request, $promo);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promo '{$promo->label}' created." . $this->notifySuffix($notified));
    }

    public function edit(Discount $promotion)
    {
        return view('admin.promotions.form', [
            'promo'         => $promotion,
            'customerCount'   => $this->customerCount(),
            'returningCounts' => $this->returningCounts(),
            'rates'         => $this->villaRates(),
        ]);
    }

    public function update(Request $request, Discount $promotion)
    {
        $data = $this->validated($request, $promotion);

        // A promo edit moves money: `value`, `type` and the date window all
        // change what guests are charged. Only the keys this form submits are
        // compared, so `used_count` drifting does not register as an edit.
        $before = collect($promotion->only(array_keys($data)))->all();

        // Ang promo GAYA NG SINABI sa mga guest, bago ang edit na ito.
        $old = clone $promotion;

        $promotion->update($data);

        StaffLog::recordChange('updated_promo', 'discounts', $promotion->id,
            "Updated promo '{$promotion->label}' ({$promotion->value_label}, {$promotion->window_label})",
            $before,
            collect($promotion->only(array_keys($data)))->all());

        $notified = $this->maybeNotifyCustomers($request, $promotion);

        // Kung naianunsyo na ito dati at nagbago ang ALOK, sinasabihan
        // ang mga nasabihan. Walang ginagawa ito sa isang promong
        // ngayon pa lang inaanunsyo (walang `notified_at` ang $old).
        $updated = $this->notifyPromoChange($old, $promotion);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promo '{$promotion->label}' updated." . $this->notifySuffix($notified) . $this->changeSuffix($updated));
    }

    /**
     * On/off switch. Mas gusto ito kaysa sa pagbura: ang isang promong
     * naka-attach na sa mga booking ay bahagi na ng kasaysayan ng presyo.
     */
    public function toggle(Discount $promotion)
    {
        $old = clone $promotion;

        $promotion->update(['is_active' => ! $promotion->is_active]);

        $state = $promotion->is_active ? 'activated' : 'deactivated';

        StaffLog::record('toggled_promo', 'discounts', $promotion->id,
            "Promo '{$promotion->label}' {$state}");

        // Ang pagpatay sa isang naianunsyong promo ang pinakamalaking
        // pagbabago sa lahat — dati ay tahimik ito.
        $updated = $this->notifyPromoChange($old, $promotion);

        return back()->with('success', "Promo '{$promotion->label}' {$state}." . $this->changeSuffix($updated));
    }

    public function destroy(Discount $promotion)
    {
        // Ang mga booking na gumamit nito ay hindi mabubura — ang
        // `discount_id` nila ay nagiging NULL (nullOnDelete), pero
        // buo pa rin ang `discount_amount`, kaya tumpak pa rin ang
        // financials. Nawawala lang ang atribusyon.
        $label = $promotion->label;
        $deletedId = $promotion->id;
        $old = clone $promotion;

        $promotion->delete();

        StaffLog::record('deleted_promo', 'discounts', $deletedId,
            "Deleted promo '{$label}' (promo #{$deletedId})");

        // Ang pagbura ay pagtatapos din ng alok. Ang mga abisong naipadala
        // na ay hindi nabubura kasama ng promo, kaya kung walang kasunod
        // na abiso, mananatili sa bell ng guest ang isang alok na wala na.
        $updated = $this->notifyPromoChange($old, null);

        return back()->with('success', "Promo '{$label}' deleted. Past bookings keep their discounted totals." . $this->changeSuffix($updated));
    }

    /**
     * Manwal na pag-blast, para sa promong nagawa na nang hindi tinikan
     * ang "notify" checkbox — o para sa isang naka-schedule na promong
     * nag-umpisa na ngayon.
     */
    public function notify(Discount $promotion)
    {
        // Ang PAREHONG `notified_at` na hadlang ng maybeNotifyCustomers().
        // Dati ay tumatawag ito ng broadcastToCustomers() nang diretso, at
        // ang tanging pumipigil sa pangalawang blast ay ang pagkawala ng
        // button sa listahan — pero nangyayari lang iyon kapag naka-render
        // na ang tugon. Dalawang mabilisang pindot ay parehong dumadaan
        // (v7.50). Ang isang affordance ay hindi hadlang.
        if ($promotion->notified_at) {
            return back()->with('error',
                "'{$promotion->label}' was already announced "
                . $promotion->notified_at->diffForHumans()
                . '. Guests are only told once, so nothing was sent again.');
        }

        $sent = $this->broadcastToCustomers($promotion);

        return back()->with('success',
            "Announced '{$promotion->label}' to {$sent} "
            . ($promotion->isReturningOnly() ? 'qualifying guest' : 'customer')
            . ($sent === 1 ? '' : 's') . '.');
    }

    // ── Internals ──────────────────────────────────────────────────

    /**
     * Ang pinakamalaking `fixed` na bawas na may saysay pa.
     *
     * Ang batayan ay ang peak rate ng villa (`weekend_price`, o `base_price`
     * kapag wala iyon), dahil iyon ang pinakamataas na `base_amount` na
     * kayang ibalik ng `getPackagePrice()`. Isang `pricing_rules` na row ay
     * puwedeng mas mataas pa — kaya kinukuha rin ang pinakamataas na aktibong
     * override, at kung alin ang mas malaki roon ang nananaig.
     *
     * Ang fallback na 100000 ay para lang hindi maging `max:0` ang rule sa
     * isang walang-laman na database (hal. bagong install bago mag-seed):
     * ang `max:0` ay tatanggi sa BAWAT promo at magmumukhang sirang form.
     */
    private function maxFixedDiscount(): float
    {
        $villa = fn () => Property::where('type', 'villa');

        $listPrice = max(
            (float) ($villa()->max('weekend_price') ?? 0),
            (float) ($villa()->max('base_price') ?? 0),
        );

        $rulePrice = (float) (PricingRule::where('is_active', 1)->max('price') ?? 0);

        return round(max($listPrice, $rulePrice) ?: 100000, 2);
    }

    private function validated(Request $request, ?Discount $existing = null): array
    {
        $rules = [
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'type'        => 'required|in:fixed,percentage',
            'value'       => 'required|numeric|min:0.01',
            'start_date'  => 'nullable|date',
            // Ang katapusan ay hindi kailanman puwedeng nasa nakaraan:
            // ang gayong promo ay patay na sa mismong sandali ng
            // pag-save — walang stay na maaabot pa nito — pero mukha
            // pa rin siyang buhay sa form. `today` ang tinatanggap
            // (para sa promong ngayong araw nagtatapos), hindi kahapon.
            'expiry_date' => 'nullable|date|after_or_equal:today|after_or_equal:start_date',
            // Hinahango sa Booking::SLOTS, hindi nakalista — kung hindi,
            // ang bagong slot sa dropdown ay tatanggihan ng validator na
            // hindi nakakita nito.
            'applies_to'  => 'required|in:all,'.implode(',', array_keys(Booking::SLOTS)),
            // Pangalawang aksis ng pagiging karapat-dapat: SINO, hiwalay
            // sa KAILAN at ALING SLOT. Tingnan ang Discount::isValidOn().
            'guest_scope' => 'required|in:all,returning',
            // 50 ang itaas na hangganan dahil ang bawat halagang mas
            // mataas pa roon ay isang promong walang makakakuha — ang
            // pinakamaraming stay ng sinumang guest sa sistemang ito ay
            // nasa isang digit pa lang. Mas mabuting harangin ito sa form
            // kaysa gumawa ng promong tahimik na walang bisa.
            'min_completed_bookings' => 'required_if:guest_scope,returning|nullable|integer|min:1|max:50',
            'usage_limit' => 'nullable|integer|min:1',
        ];

        // Ang simula ay hindi rin puwedeng nasa nakaraan — MALIBAN kung
        // hindi naman ito ginagalaw. Ang isang promong tumatakbo na ay
        // may nakaraang `start_date` sa likas na paraan; hindi dapat
        // pilitin si admin na palitan iyon para lang makapag-ayos ng
        // typo sa pangalan. Kung babaguhin naman niya talaga ang petsa,
        // dapat pasulong iyon.
        $submittedStart = $request->input('start_date');
        $unchangedStart = $existing
            && $submittedStart === ($existing->start_date?->format('Y-m-d'));

        if (! $unchangedStart) {
            $rules['start_date'] = 'nullable|date|after_or_equal:today';
        }

        // Ang 150%-off ay hindi typo na kayang hulaan — mas mabuting
        // harangin sa form kaysa sa i-clamp nang tahimik habang naka-
        // tingin ang admin sa maling numero.
        if ($request->input('type') === 'percentage') {
            $rules['value'] = 'required|numeric|min:0.01|max:100';
        }

        // Ganoon din ang `fixed`, na dati ay walang anumang itaas na
        // hangganan. Ang bawas ay kinakaltas sa `base_amount` — ang
        // pinakamataas na posibleng halaga niyon ay ang peak rate ng villa
        // — kaya ang anumang mas malaki pa roon ay laging typo. HINDI ito
        // nagiging negatibong kabuuan (ini-clamp ng
        // Discount::calculateDiscount() sa `min($off, $amount)`), pero
        // iyon mismo ang problema: ang isang ₱400,000 na naitype bilang
        // ₱4,000 ay tahimik na ginagawang ₱0 ang bawat stay sa buong
        // window ng promo, at walang anumang mensahe sa admin.
        if ($request->input('type') === 'fixed') {
            $rules['value'] = 'required|numeric|min:0.01|max:'.$this->maxFixedDiscount();
        }

        $data = $request->validate($rules, [
            'start_date.after_or_equal'  => 'The start date cannot be in the past.',
            'expiry_date.after_or_equal' => 'The end date cannot be in the past, and cannot come before the start date.',
            'min_completed_bookings.required_if' => 'Say how many completed stays a guest needs to qualify.',
        ]);

        // Ang isang promong para sa lahat ay hindi nagtatago ng threshold
        // na nakalimutan — ibinabalik ito sa 1 para hindi maging gatilyo
        // ng ibang kahulugan kapag ginawang "returning" sa susunod na
        // pag-edit nang hindi muling tiningnan ang numero.
        $data['min_completed_bookings'] = $data['guest_scope'] === 'returning'
            ? max(1, (int) ($data['min_completed_bookings'] ?? 1))
            : 1;

        $data['is_public'] = $request->boolean('is_public');
        $data['is_active'] = $request->boolean('is_active');

        // Isang beses lang ang blast bawat promo. Ang `notified_at` ang
        // tanda; hindi ito ni-re-reset ng pag-edit, kaya hindi
        // mababahaan ng paulit-ulit na abiso ang bell ng guest tuwing
        // may itatamang typo si admin.
        unset($data['notify_customers']);

        return $data;
    }

    private function maybeNotifyCustomers(Request $request, Discount $promo): int
    {
        if (! $request->boolean('notify_customers')) return 0;
        if ($promo->notified_at) return 0;

        return $this->broadcastToCustomers($promo);
    }

    /**
     * In-app notification lang — SADYANG hindi email.
     *
     * Ang production ay dumadaan sa Brevo HTTPS API na may mahigpit na
     * libreng quota, at kasama roon ang mga transactional na mensahe
     * (2FA, booking confirmation, password reset). Ang isang marketing
     * blast na pumatay sa quota ay hindi lang nakakainis — hindi na
     * makakapag-login ang mga guest. Ang bell sa portal ang tamang
     * lugar para dito.
     *
     * ANG AUDIENCE AY DUMADAAN SA PAREHONG PAGSUSURI NG PAGIGING
     * KARAPAT-DAPAT GAYA NG PRESYO (v7.50).
     *
     * Dati ay `role = customer AND status = 1` lang — tama iyon noong
     * ang bawat promo ay para sa lahat. Nang dumating ang
     * `guest_scope` (v7.49), natutunan ng pagpepresyo, ng banner at ng
     * chatbot ang aksis na "sino"; ang anunsyo ay hindi. Ang resulta ay
     * ang pinakamasamang anyo ng pagkakamali: isang abisong nangangako
     * ng bawas sa taong hindi naman makukuha iyon — at kapag pinindot
     * niya, wala siyang makikita.
     *
     * Ang pagsusuri ay isang SQL predicate (tingnan ang
     * User::scopeWithCompletedStays()) at hindi isang PHP filter kada
     * guest: ang buong dahilan ng chunkById() ay hindi hawakan ang
     * lahat ng user nang sabay-sabay.
     */
    private function broadcastToCustomers(Discount $promo): int
    {
        $title = ($promo->isReturningOnly() ? 'Returning-Guest Offer: ' : 'New Promo: ') . $promo->label;

        // Sinasabi ng mensahe KUNG BAKIT nakuha ito ng guest. Kung
        // hindi, ang isang bawas na para lang sa mga regular ay
        // mukhang pangkalahatan, at ipapasa niya iyon sa kaibigang
        // hindi naman makakakuha.
        $message = trim(($promo->description ? $promo->description . ' ' : '') .
            // guest_window_phrase, HINDI window_label: ang "no end date" ay
            // pangakong hindi kayang tuparin (v7.51).
            $promo->value_label . ' on the villa rate, ' . $promo->guest_window_phrase . '.' .
            ($promo->isReturningOnly()
                ? ' This is our thank-you for returning guests — it is applied automatically to your price, with no code to enter.'
                : ''));

        $link = route('home', [], false);

        $sent = 0;

        $this->announcementAudience($promo)
            ->select('id')
            ->chunkById(200, function ($customers) use ($title, $message, $link, &$sent) {
                foreach ($customers as $customer) {
                    // Ang isang bigong abiso ay hindi dapat pumatay sa
                    // buong blast — mananatiling naabisuhan ang lahat ng
                    // iba pa, at nakatala sa log kung sino ang hindi.
                    try {
                        // `broadcast: false` — tingnan ang
                        // NotificationHelper::notifyGuest(). Ang isang
                        // blocking na tawag sa Pusher KADA guest ang
                        // dahilan kung bakit umabot ng ~12 segundo ang
                        // 8-guest na blast, at kung bakit mukhang
                        // nag-hang ang form kaya napindot itong muli.
                        NotificationHelper::notifyGuest($customer->id, $title, $message, $link, broadcast: false);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error("Promo announcement failed for user {$customer->id}: " . $e->getMessage());
                    }
                }
            });

        $promo->forceFill(['notified_at' => now()])->save();

        StaffLog::record('announced_promo', 'discounts', $promo->id,
            "Announced promo '{$promo->label}' to {$sent} " .
            ($promo->isReturningOnly()
                ? 'qualifying returning guest' . ($sent === 1 ? '' : 's') . " ({$promo->min_completed_bookings}+ completed stays)"
                : 'customer' . ($sent === 1 ? '' : 's')));

        return $sent;
    }

    /**
     * Sabihan ang mga guest na nagbago ang isang promong NAIANUNSYO NA.
     *
     * Ang abiso ay nakaimbak na teksto: kapag nagbago ang promo, ang
     * nasa bell ng guest ay nananatiling luma. Nangyari ito — isang
     * promong inanunsyo bilang "no end date" ay nilagyan ng dulo, at
     * walang nakaalam maliban kay admin (v7.51). Hindi kailanman
     * nasisingil nang sobra ang guest (ang quoteFor() ay laging
     * bumabasa ng buhay na row), pero umaasa siya sa pangakong wala na.
     *
     * HINDI nito sinisira ang "ang edit ay hindi nagbla-blast muli":
     * ang panuntunang iyon ay para sa typo, at ang `label` at
     * `description` ay wala sa Discount::MATERIAL_FIELDS. Ang
     * nagpapadala rito ay pagbabago lang sa mismong alok.
     *
     * Tatlong uri ng pagbabago:
     *   - natapos (na-deactivate o nabura; `$current` ay null kapag nabura)
     *   - bumalik (muling in-activate)
     *   - nagbago ang mga tuntunin
     *
     * Walang ipinapadala kung hindi naman ito naianunsyo, o kung
     * walang nagbago sa paningin ng guest (hal. pag-deactivate ng
     * promong lampas na sa expiry).
     *
     * ANG AUDIENCE AY AYON SA LUMANG TUNTUNIN — sila ang nasabihan.
     * Kapag itinaas ang threshold mula 5 patungong 8, ang may 6 na
     * stay ang pinakakailangang makaalam, at wala na siya sa bagong
     * audience. (Ang pagbabalik lang ang gumagamit ng kasalukuyan:
     * iyon ay panibagong alok sa mga karapat-dapat ngayon.)
     */
    private function notifyPromoChange(Discount $old, ?Discount $current): int
    {
        if (! $old->notified_at) {
            return 0;
        }

        $wasLive = $old->isValid();
        $isLive  = $current !== null && $current->isValid();

        if ($wasLive && ! $isLive) {
            $title    = 'Promo Ended: ' . $old->label;
            $lines    = ['This promo has ended and no longer applies to new bookings. Bookings you have already made keep the price they were booked at.'];
            $audience = $this->announcementAudience($old);
        } elseif (! $wasLive && $isLive) {
            $title    = 'Promo Is Back: ' . $current->label;
            $lines    = ["It is available again: {$current->value_label} on the villa rate, {$current->guest_window_phrase}."];
            $audience = $this->announcementAudience($current);
        } elseif ($wasLive && $isLive) {
            $title    = 'Promo Update: ' . $current->label;
            $lines    = $current->guestFacingChangesFrom($old);
            $audience = $this->announcementAudience($old);

            if ($lines !== [] && $old->label !== $current->label) {
                array_unshift($lines, "(Previously announced as \"{$old->label}\".)");
            }
        } else {
            return 0;
        }

        if ($lines === []) {
            return 0;
        }

        $message = implode(' ', $lines);
        $link    = route('home', [], false);
        $sent    = 0;

        $audience->select('id')->chunkById(200, function ($customers) use ($title, $message, $link, &$sent) {
            foreach ($customers as $customer) {
                try {
                    NotificationHelper::notifyGuest($customer->id, $title, $message, $link, broadcast: false);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error("Promo change notice failed for user {$customer->id}: " . $e->getMessage());
                }
            }
        });

        StaffLog::record('announced_promo_change', 'discounts', $old->id,
            "Told {$sent} guest" . ($sent === 1 ? '' : 's') . " that promo '{$old->label}' changed: {$message}");

        return $sent;
    }

    private function changeSuffix(int $updated): string
    {
        return $updated > 0
            ? " {$updated} guest" . ($updated === 1 ? ' who was' : 's who were') . ' told about it '
                . ($updated === 1 ? 'has' : 'have') . ' been sent an update.'
            : '';
    }

    /**
     * Sino ang makakatanggap ng anunsyo ng promong ito.
     *
     * Hiwalay na method dahil DALAWA ang nagtatanong nito: ang blast
     * mismo at ang bilang na ipinapakita sa form bago pa ito pindutin.
     * Kung magkaiba ang dalawa, nagsisinungaling ang checkbox.
     */
    private function announcementAudience(Discount $promo)
    {
        $query = User::where('role', 'customer')->where('status', 1);

        if ($promo->isReturningOnly()) {
            $query->withCompletedStays((int) $promo->min_completed_bookings);
        }

        return $query;
    }

    /**
     * Ang totoong package rates ng villa, para sa live preview sa form.
     * Binabasa mula sa DB sa halip na i-hardcode ang ₱4,000/₱6,000 —
     * ibang halaga ang nasa dev/test na kapaligiran, at ang isang
     * preview na nagsisinungaling tungkol sa presyo ay mas masama pa
     * kaysa sa walang preview.
     */
    private function villaRates(): array
    {
        $villa = \App\Models\Property::where('type', 'villa')->first();

        return [
            'base'    => (float) ($villa->base_price ?? 0),
            'weekend' => (float) ($villa->weekend_price ?? $villa->base_price ?? 0),
        ];
    }

    /**
     * Ilang aktibong customer ang may N o higit pang natapos na stay,
     * para sa bawat N na maaaring ilagay ni admin (1..50, ang hangganan
     * ng validation).
     *
     * Bakit buong distribusyon at hindi isang bilang: ang audience ay
     * nakadepende sa `guest_scope` AT sa threshold, at pinipili pa lang
     * ni admin ang dalawang iyon habang nasa form. Isang
     * server-rendered na numero ay magiging mali sa sandaling may
     * mapindot. Ipinapasa ang buong hugis para ma-update ng JS ang
     * label habang nagbabago ang pinili — walang bagong request, at
     * iisang pinagmumulan pa rin ang bilang.
     *
     * Isang aggregate query ito, hindi 50.
     *
     * @return array<int, int>
     */
    private function returningCounts(): array
    {
        // Bilang ng natapos na stay kada aktibong customer.
        $perGuest = \App\Models\Booking::query()
            ->selectRaw('bookings.user_id, count(*) as stays')
            ->join('users', 'users.id', '=', 'bookings.user_id')
            ->where('users.role', 'customer')
            ->where('users.status', 1)
            ->where('bookings.status', User::COMPLETED_STAY_STATUS)
            ->groupBy('bookings.user_id')
            ->pluck('stays')
            ->all();

        $counts = [];
        for ($min = 1; $min <= 50; $min++) {
            $counts[$min] = count(array_filter($perGuest, fn ($n) => $n >= $min));
        }

        return $counts;
    }

    private function customerCount(): int
    {
        return User::where('role', 'customer')->where('status', 1)->count();
    }

    private function notifySuffix(int $notified): string
    {
        return $notified > 0
            ? " Announced to {$notified} customer" . ($notified === 1 ? '' : 's') . '.'
            : '';
    }
}
