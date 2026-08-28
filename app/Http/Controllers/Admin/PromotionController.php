<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Discount;
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
                'is_public'  => 1,
                'is_active'  => 1,
            ]),
            'customerCount' => $this->customerCount(),
            'rates'         => $this->villaRates(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

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
            'customerCount' => $this->customerCount(),
            'rates'         => $this->villaRates(),
        ]);
    }

    public function update(Request $request, Discount $promotion)
    {
        $data = $this->validated($request, $promotion);

        $promotion->update($data);

        StaffLog::record('updated_promo', 'discounts', $promotion->id,
            "Updated promo '{$promotion->label}' ({$promotion->value_label}, {$promotion->window_label})");

        $notified = $this->maybeNotifyCustomers($request, $promotion);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promo '{$promotion->label}' updated." . $this->notifySuffix($notified));
    }

    /**
     * On/off switch. Mas gusto ito kaysa sa pagbura: ang isang promong
     * naka-attach na sa mga booking ay bahagi na ng kasaysayan ng presyo.
     */
    public function toggle(Discount $promotion)
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        $state = $promotion->is_active ? 'activated' : 'deactivated';

        StaffLog::record('toggled_promo', 'discounts', $promotion->id,
            "Promo '{$promotion->label}' {$state}");

        return back()->with('success', "Promo '{$promotion->label}' {$state}.");
    }

    public function destroy(Discount $promotion)
    {
        // Ang mga booking na gumamit nito ay hindi mabubura — ang
        // `discount_id` nila ay nagiging NULL (nullOnDelete), pero
        // buo pa rin ang `discount_amount`, kaya tumpak pa rin ang
        // financials. Nawawala lang ang atribusyon.
        $label = $promotion->label;
        $promotion->delete();

        StaffLog::record('deleted_promo', 'discounts', null, "Deleted promo '{$label}'");

        return back()->with('success', "Promo '{$label}' deleted. Past bookings keep their discounted totals.");
    }

    /**
     * Manwal na pag-blast, para sa promong nagawa na nang hindi tinikan
     * ang "notify" checkbox — o para sa isang naka-schedule na promong
     * nag-umpisa na ngayon.
     */
    public function notify(Discount $promotion)
    {
        $sent = $this->broadcastToCustomers($promotion);

        return back()->with('success', "Announced '{$promotion->label}' to {$sent} customer" . ($sent === 1 ? '' : 's') . '.');
    }

    // ── Internals ──────────────────────────────────────────────────

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
            'applies_to'  => 'required|in:all,day,night',
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

        $data = $request->validate($rules, [
            'start_date.after_or_equal'  => 'The start date cannot be in the past.',
            'expiry_date.after_or_equal' => 'The end date cannot be in the past, and cannot come before the start date.',
        ]);

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
     */
    private function broadcastToCustomers(Discount $promo): int
    {
        $title   = 'New Promo: ' . $promo->label;
        $message = trim(($promo->description ? $promo->description . ' ' : '') .
            $promo->value_label . ' on the villa rate — ' . $promo->window_label . '.');
        $link    = route('home', [], false);

        $sent = 0;

        User::where('role', 'customer')
            ->where('status', 1)
            ->select('id')
            ->chunkById(200, function ($customers) use ($title, $message, $link, &$sent) {
                foreach ($customers as $customer) {
                    // Ang isang bigong abiso ay hindi dapat pumatay sa
                    // buong blast — mananatiling naabisuhan ang lahat ng
                    // iba pa, at nakatala sa log kung sino ang hindi.
                    try {
                        NotificationHelper::notifyGuest($customer->id, $title, $message, $link);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error("Promo announcement failed for user {$customer->id}: " . $e->getMessage());
                    }
                }
            });

        $promo->forceFill(['notified_at' => now()])->save();

        StaffLog::record('announced_promo', 'discounts', $promo->id,
            "Announced promo '{$promo->label}' to {$sent} customers");

        return $sent;
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
