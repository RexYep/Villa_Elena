<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\Recommendation;
use App\Models\Setting;
use App\Models\StaffLog;
use App\Services\Prescriptive\BriefingWriter;
use App\Services\Prescriptive\DemandModel;
use App\Services\Prescriptive\PrescriptiveEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ang ACTION CENTER — ADMIN LANG, tulad ng Promotions.
 *
 * Parehong dahilan ang nakasulat na sa PromotionController: ang isang
 * promo ay direktang pagbaba ng kita, kaya iisa lang ang taong dapat may
 * kapangyarihang gumawa nito. Dagdag pa, hayagang inilalantad ng mga card
 * dito ang panloob na datos ng negosyo — mahihinang petsa, tinatayang
 * kita, pagpapalagay sa elasticity. Hindi iyon para sa guest, at hindi
 * rin para sa frontdesk.
 *
 * ANG BUONG POINT NG CONTROLLER NA ITO: dito nagiging TOTOO ang isang
 * mungkahi. Bago ang pindot na ito, ang isang `Recommendation` ay teksto
 * lang sa isang table — walang presyong nagbago, walang petsang nasara,
 * walang nakita ang guest.
 */
class PrescriptiveController extends Controller
{
    public function index()
    {
        $open = Recommendation::open()
            ->orderByDesc('expected_impact')
            ->get()
            // Ang isang kard na lumipas na ang petsa ay hindi na dapat
            // may Apply button kahit `new` pa ang status sa DB — hindi pa
            // lang natatakbo ang cron. Sinasala ito rito para hindi
            // maipakita ang aksyong tiyak na babagsak.
            ->reject(fn (Recommendation $r) => $r->isExpired());

        $history = Recommendation::decided()
            ->orderByDesc('updated_at')
            ->limit(20)
            ->with('appliedBy:id,full_name')
            ->get();

        return view('admin.prescriptive.index', [
            'open' => $open,
            'history' => $history,
            'lastGenerated' => Recommendation::max('generated_at'),
            'totalImpact' => $open->sum(fn (Recommendation $r) => (float) $r->expected_impact),
            // Walang tawag sa Groq dito — nabasa lang ang isinulat ng
            // huling `prescriptive:generate`. Ang page na tumatawag sa AI
            // kada bisita ay mabagal at kumakain ng quota (tingnan ang
            // Insights at Forecast).
            'briefing' => Setting::get(BriefingWriter::SETTING_TEXT, ''),
        ]);
    }

    /**
     * WHAT-IF SIMULATOR — ang parehong modelo, pero ang admin ang nagtatanong.
     *
     * Bakit ito mahalaga: ang mga card sa index ay mga sagot na hindi
     * hiningi. Dito, ang may-ari ang nagtatakda ng petsa, slot, at pagbabago
     * sa presyo, at ipinapakita ng kaparehong `DemandModel` kung ano ang
     * inaasahang mangyayari. Iyon ang pagkakaiba ng isang tool na
     * pinagkakatiwalaan sa isang tool na sinusunod na lang — kaya niyang
     * subukan ang sariling hinala at ihambing ito.
     *
     * Ganap na READ-ONLY. Walang isinusulat, kaya GET ito at ligtas itong
     * i-refresh, i-bookmark, o ipakita sa isang defense nang paulit-ulit.
     */
    public function simulate(Request $request)
    {
        $data = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
            'slot' => 'nullable|in:both,day,night',
            'change' => 'nullable|numeric|min:-50|max:50',
        ]);

        $start = Carbon::parse($data['start'] ?? Carbon::today()->addDays(2))->startOfDay();
        $end = Carbon::parse($data['end'] ?? Carbon::today()->addDays(14))->startOfDay();
        $slotChoice = $data['slot'] ?? 'both';
        $change = (float) ($data['change'] ?? -10);

        // 90 araw ang hangganan — hindi para sa bilis kundi para sa
        // katapatan. Ang isang taon ng hinuha sa ibabaw ng anim na buwang
        // datos ay hindi na simulation, panaginip na.
        if ($start->diffInDays($end) > 90) {
            $end = $start->copy()->addDays(90);
        }

        $demand = new DemandModel;
        $slots = $slotChoice === 'both' ? array_keys(Booking::SLOTS) : [$slotChoice];

        $elasticity = $change < 0
            ? DemandModel::setting('prescriptive_elasticity', 1.5)
            : DemandModel::setting('prescriptive_peak_elasticity', 0.6);

        $ceiling = (float) config('prescriptive.probability_ceiling', 0.90);
        $magnitude = abs($change) / 100;

        $rows = [];
        $skipped = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            foreach ($slots as $slot) {
                // Hindi ipinapasok ang mga petsang nabenta na o sarado —
                // walang maisisimula ang isang pagbabago sa presyo doon, at
                // ang pagsasama sa kanila ay magpapalobo sa kabuuan.
                if ($demand->isOccupied($date, $slot) || $demand->isBlocked($date)) {
                    $skipped++;

                    continue;
                }

                $p = $demand->probability($date, $slot);
                $price = $demand->price($date, $slot);

                $pNew = $change < 0
                    ? min($ceiling, $p * (1 + $elasticity * $magnitude))
                    : max(0.0, $p * (1 - $elasticity * $magnitude));

                $newPrice = round($price * (1 + $change / 100), 2);

                $rows[] = [
                    'date' => $date->copy(),
                    'slot' => $slot,
                    'holiday' => $demand->holidayName($date),
                    'p' => $p,
                    'p_new' => $pNew,
                    'price' => $price,
                    'new_price' => $newPrice,
                    'baseline' => round($p * $price, 2),
                    'projected' => round($pNew * $newPrice, 2),
                ];
            }
        }

        $baseline = round(array_sum(array_column($rows, 'baseline')), 2);
        $projected = round(array_sum(array_column($rows, 'projected')), 2);

        return view('admin.prescriptive.simulate', [
            'rows' => $rows,
            'skipped' => $skipped,
            'start' => $start,
            'end' => $end,
            'slotChoice' => $slotChoice,
            'change' => $change,
            'elasticity' => $elasticity,
            'baseline' => $baseline,
            'projected' => $projected,
            'delta' => round($projected - $baseline, 2),
            'baselineBookings' => round(array_sum(array_column($rows, 'p')), 1),
            'projectedBookings' => round(array_sum(array_column($rows, 'p_new')), 1),
        ]);
    }

    /**
     * ACCURACY — ang page kung saan sinusukat ng sistema ang sarili nito.
     *
     * Dalawang hiwalay na tanong ang sinasagot dito, at ang paghahalo sa
     * kanila ang pinakamadaling paraan para magsinungaling nang hindi
     * sinasadya:
     *
     *   1. GAANO KATUMPAK ANG MODELO? Sinusukat sa mga mungkahing HINDI
     *      kinilos (dismissed/expired). Walang interbensyon, kaya ang
     *      pagitan ng hula at naganap ay purong error ng `DemandModel`.
     *   2. GUMANA BA ANG MGA AKSYON? Sinusukat sa mga inaplay. May
     *      interbensyon dito, kaya hindi ito malinis na sukat ng modelo.
     *
     * Ang pangalawa ay HINDI ebidensya ng sanhi — walang control group,
     * at ang paghahambing ay laban sa sariling baseline ng modelo.
     * Nakasulat iyon sa page mismo, hindi lang dito sa komento.
     */
    public function accuracy()
    {
        $measured = Recommendation::measured()
            ->orderByDesc('target_end')
            ->limit(100)
            ->get();

        $noAction = $measured->filter(fn ($r) => in_array($r->status, ['dismissed', 'expired'], true));
        $applied = $measured->filter(fn ($r) => $r->status === 'applied');

        return view('admin.prescriptive.accuracy', [
            'measured' => $measured,
            'noAction' => $noAction,
            'applied' => $applied,

            // Kalibrasyon ng modelo: kabuuang inaasahan laban sa kabuuang
            // naganap, sa mga araw na walang ginawa.
            'baselineProjected' => round($noAction->sum(fn ($r) => (float) $r->baseline_projection), 2),
            'baselineActual' => round($noAction->sum(fn ($r) => (float) $r->actual_revenue), 2),

            // Mga aksyon: ipinangakong dagdag laban sa naitalang dagdag.
            'promisedGain' => round($applied->sum(fn ($r) => (float) $r->expected_impact), 2),
            'realizedGain' => round($applied->sum(fn ($r) => (float) $r->realized_impact), 2),

            // Bilang ng mga naisara nang hindi masusukat — hayagang
            // inaamin sa halip na tahimik na tanggalin sa bilang.
            'unmeasurable' => Recommendation::whereNotNull('settled_at')
                ->whereNull('realized_impact')
                ->count(),
            'pending' => Recommendation::whereNull('settled_at')->count(),
        ]);
    }

    /** Manwal na pagpapatakbo ng engine, para hindi kailangang hintayin ang cron. */
    public function regenerate(PrescriptiveEngine $engine, BriefingWriter $briefing)
    {
        $stats = $engine->run();

        // Ang briefing ay tumutukoy sa mga partikular na card; kung hindi
        // ito muling isusulat dito, magsasalita ito tungkol sa mga
        // mungkahing kalalabas lang sa listahan.
        $briefing->write();

        return back()->with('success', sprintf(
            'Recommendations refreshed — %d new, %d updated, %d expired.',
            $stats['created'],
            $stats['refreshed'],
            $stats['expired']
        ));
    }

    /**
     * Isinasagawa ang mungkahi.
     *
     * Ang claim ay nasa loob ng naka-lock na transaksyon BAGO gawin ang
     * record, kaya ang dobleng pindot ay hindi makakagawa ng dalawang
     * promo — parehong hugis ng pag-iingat na ginagamit sa refund
     * transfers, at sa parehong dahilan.
     */
    public function apply(Recommendation $recommendation)
    {
        if (! $recommendation->isActionable()) {
            return back()->with('error', 'That recommendation is no longer open — it may have been applied, dismissed, or expired.');
        }

        // Nagbago na ba ang mundo mula noong nabuo ang mungkahi? Ang card
        // ay maaaring ilang oras nang nakabukas sa isang tab.
        if ($conflict = $this->staleReason($recommendation)) {
            return back()->with('error', $conflict);
        }

        try {
            $result = DB::transaction(function () use ($recommendation) {
                $fresh = Recommendation::whereKey($recommendation->id)->lockForUpdate()->first();

                if (! $fresh || $fresh->status !== 'new') {
                    return null;
                }

                $record = match ($fresh->action_type) {
                    Recommendation::ACTION_CREATE_PROMO => $this->createPromo($fresh),
                    Recommendation::ACTION_CREATE_BLOCK => $this->createBlock($fresh),
                    Recommendation::ACTION_CREATE_PRICING_RULE => $this->createPricingRule($fresh),
                    default => throw new \RuntimeException("Unknown action type: {$fresh->action_type}"),
                };

                $fresh->update([
                    'status' => 'applied',
                    'applied_at' => now(),
                    'applied_by' => Auth::id(),
                    'applied_record_type' => match ($fresh->action_type) {
                        Recommendation::ACTION_CREATE_PROMO => 'discounts',
                        Recommendation::ACTION_CREATE_BLOCK => 'availability_blocks',
                        Recommendation::ACTION_CREATE_PRICING_RULE => 'pricing_rules',
                    },
                    'applied_record_id' => $record->id,
                ]);

                return [$fresh, $record];
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not apply that recommendation: '.$e->getMessage());
        }

        if ($result === null) {
            return back()->with('error', 'That recommendation was already acted on.');
        }

        [$applied, $record] = $result;

        StaffLog::record(
            'applied_recommendation',
            'recommendations',
            $applied->id,
            "Applied recommendation '{$applied->title}' (projected {$applied->impact_label})"
        );

        return back()->with('success', $this->successMessage($applied, $record));
    }

    public function dismiss(Request $request, Recommendation $recommendation)
    {
        if ($recommendation->status !== 'new') {
            return back()->with('error', 'That recommendation is no longer open.');
        }

        $data = $request->validate([
            'dismiss_reason' => 'nullable|string|max:255',
        ]);

        $recommendation->update([
            'status' => 'dismissed',
            'dismissed_at' => now(),
            'dismiss_reason' => $data['dismiss_reason'] ?? null,
        ]);

        StaffLog::record(
            'dismissed_recommendation',
            'recommendations',
            $recommendation->id,
            "Dismissed recommendation '{$recommendation->title}'"
        );

        return back()->with('success', 'Recommendation dismissed. It will not be suggested again.');
    }

    // ── Internals ──────────────────────────────────────────────────

    /**
     * Bakit hindi na puwedeng i-apply ngayon? Null kung puwede pa.
     *
     * Kinakailangan ito dahil ang mungkahi ay nabuo kagabi at ang pindot ay
     * ngayon. Sa pagitan noon, maaaring may nag-book na sa mismong mga
     * petsang iyon — at ang pagsasara ng villa sa ibabaw ng isang tunay na
     * booking ay malayong mas masama kaysa sa hindi pagkakaroon ng
     * mungkahi.
     */
    private function staleReason(Recommendation $rec): ?string
    {
        if ($rec->action_type !== Recommendation::ACTION_CREATE_BLOCK) {
            return null;
        }

        $clash = Booking::where('property_id', $this->villa()->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('check_in_date', '<=', $rec->target_end)
            ->whereDate('check_out_date', '>=', $rec->target_start)
            ->exists();

        return $clash
            ? 'A booking now falls inside that window, so the villa cannot be blocked. Refresh the recommendations to recompute.'
            : null;
    }

    private function createPromo(Recommendation $rec): Discount
    {
        // Sinasadyang hindi dumadaan sa `action_payload` nang buo — ang
        // isang payload na nagtakda ng `used_count` o `code` ay makakalusot
        // sa validation ng PromotionController na hindi naman ito
        // dinadaanan. Ang mga field lang na inaasahan ang kinukuha.
        $p = $rec->action_payload;

        return Discount::create([
            'label' => $p['label'],
            'description' => $p['description'] ?? null,
            'type' => $p['type'],
            'value' => $p['value'],
            'start_date' => $p['start_date'],
            'expiry_date' => $p['expiry_date'],
            'applies_to' => $p['applies_to'],
            'is_public' => $p['is_public'] ?? 1,
            'is_active' => $p['is_active'] ?? 1,
        ]);
    }

    /**
     * ⚠️ `type = 'fixed'` ang IPINIPILIT dito, kahit ano pa ang nasa payload.
     *
     * Ang isang `percentage` na rule ay kinukuwenta ng
     * `Property::getPackagePrice()` laban sa `base_price` LAMANG, hindi sa
     * `weekend_price` — kaya ang "+15%" sa isang Sabado ay magbibigay ng
     * 4,000 x 1.15 = 4,600 kapalit ng 6,000, at ang mungkahing magtaas ng
     * presyo ay tahimik na magpapamura. Ang advisor ay nagpapadala na ng
     * ganap na halaga; ipinipilit din ito rito bilang pangalawang sapin,
     * dahil ang tahimik na pagbaba ng presyo ay hindi nakikita hangga't
     * hindi na nababawi ang kita.
     */
    private function createPricingRule(Recommendation $rec): PricingRule
    {
        $p = $rec->action_payload;

        return PricingRule::create([
            'property_id' => $this->villa()->id,
            'label' => $p['label'],
            'start_date' => $p['start_date'],
            'end_date' => $p['end_date'],
            'price' => $p['price'],
            'type' => 'fixed',
            'is_active' => $p['is_active'] ?? 1,
        ]);
    }

    private function createBlock(Recommendation $rec): AvailabilityBlock
    {
        $p = $rec->action_payload;

        return AvailabilityBlock::create([
            'property_id' => $this->villa()->id,
            'start_date' => $p['start_date'],
            'end_date' => $p['end_date'],
            'reason' => $p['reason'] ?? 'maintenance',
            'notes' => $p['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    private function successMessage(Recommendation $rec, $record): string
    {
        if ($rec->action_type === Recommendation::ACTION_CREATE_PROMO) {
            return "Promo '{$record->label}' is now live — guests booking those dates will see the lower rate. Edit or switch it off any time under Promotions.";
        }

        if ($rec->action_type === Recommendation::ACTION_CREATE_PRICING_RULE) {
            return "Rate '{$record->label}' is now in effect — {$rec->window_label} is priced at ₱"
                .number_format((float) $record->price, 0)
                .'. Edit or deactivate it under Properties → Pricing Rules.';
        }

        return "The villa is now blocked for {$rec->window_label}. Those dates no longer appear as available to guests.";
    }

    private function villa(): Property
    {
        return Property::where('type', 'villa')->firstOrFail();
    }
}
