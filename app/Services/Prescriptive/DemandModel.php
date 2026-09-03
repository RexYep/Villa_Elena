<?php

namespace App\Services\Prescriptive;

use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Ang PREDICTIVE na pundasyon ng prescriptive engine.
 *
 * Isang villa lang ang binebenta, at dalawang fixed slot kada araw, kaya
 * hindi kailangang maging kumplikado ang "demand" dito: para sa bawat
 * (araw-ng-linggo x slot), ilang bahagi ng nakaraang ganoong araw ang
 * talagang nabook? Iyon ang `p`. Simple, nasusuri ng tao, at kayang
 * ipaliwanag nang walang black box.
 *
 * DALAWANG BAGAY NA SINASADYA:
 *
 * 1. Ang mga araw na NAKA-BLOCK (maintenance, owner use) ay tinatanggal
 *    sa denominator. Hindi sila "hindi nabenta" — hindi sila naialok
 *    kailanman. Kung isasama sila, magmumukhang mahina ang isang araw na
 *    hindi naman inalok, at magmumungkahi ang engine ng diskuwento para
 *    sa problemang wala naman.
 *
 * 2. Ang presyo ay laging galing sa `Property::quoteFor()` — ang iisang
 *    pinagmumulan ng presyo sa buong sistema. Hindi kailanman kinokopya
 *    rito ang lohika ng pagpepresyo; iyon mismo ang pagkakamaling naitala
 *    na sa CLAUDE.md tungkol sa walk-in form.
 */
class DemandModel
{
    private Property $villa;

    private HolidayCalendar $holidays;

    /** @var array<string, array{rate: float, booked: int, sample: int}> */
    private array $fillCache = [];

    /** @var array<string, bool>|null 'Y-m-d|slot' => true */
    private ?array $occupied = null;

    /** @var array<string, string>|null 'Y-m-d' => reason */
    private ?array $blocked = null;

    /** @var array<string, bool>|null 'Y-m-d' => true */
    private ?array $ruled = null;

    /** @var array<string, array> */
    private array $quoteCache = [];

    public function __construct(?Property $villa = null, ?HolidayCalendar $holidays = null)
    {
        $this->villa = $villa ?? Property::where('type', 'villa')->firstOrFail();
        $this->holidays = $holidays ?? new HolidayCalendar;
    }

    public function villa(): Property
    {
        return $this->villa;
    }

    // ── Mga pagpapalagay na kayang baguhin ng admin ────────────────

    public static function setting(string $key, float $default): float
    {
        $value = Setting::get($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function lookbackDays(): int
    {
        return (int) static::setting('prescriptive_lookback_days', 180);
    }

    public function lookaheadDays(): int
    {
        return (int) static::setting('prescriptive_lookahead_days', 45);
    }

    // ── Historical fill rate ───────────────────────────────────────

    /**
     * Ang bahagi ng mga nakaraang araw na katulad nito na talagang nabook.
     *
     * PINAPAKINIS ANG `rate` (Jeffreys prior, alpha = 0.5):
     *
     *     rate = (nabook + 0.5) / (sample + 1)
     *
     * Hindi ito palamuti. Ang hilaw na 0/26 ay nagbibigay ng p = 0, at sa
     * multiplicative na tugon sa diskuwento (tingnan ang
     * IdleDatePromoAdvisor) ang 0 na pinarami ay 0 pa rin — kaya ang mga
     * araw na WALANG kahit isang booking, na siya mismong pinakanangangailangan
     * ng tulong, ay hindi kailanman makakatanggap ng mungkahi. Ang totoong
     * sinasabi ng 0/26 ay "bihira", hindi "imposible".
     *
     * Ang HILAW na bilang ay ibinabalik pa rin (`booked`, `sample`,
     * `raw_rate`) at iyon ang ipinapakita sa ebidensya — dapat makita ng
     * admin ang aktuwal na naganap, hindi lang ang pinakinis na numero.
     *
     * @return array{rate: float, raw_rate: float, booked: int, sample: int}
     */
    public function fillRate(int $dayOfWeek, string $slot): array
    {
        $key = $dayOfWeek.'-'.$slot;

        if (isset($this->fillCache[$key])) {
            return $this->fillCache[$key];
        }

        $start = Carbon::today()->subDays($this->lookbackDays());
        $end = Carbon::today()->subDay();

        $booked = 0;
        $sample = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->dayOfWeek !== $dayOfWeek) {
                continue;
            }

            // Hindi kailanman naialok — wala sa denominator (tingnan ang #1).
            if ($this->isBlocked($d)) {
                continue;
            }

            $sample++;

            if ($this->isOccupied($d, $slot)) {
                $booked++;
            }
        }

        return $this->fillCache[$key] = [
            'rate' => $sample > 0 ? ($booked + 0.5) / ($sample + 1) : 0.0,
            'raw_rate' => $sample > 0 ? $booked / $sample : 0.0,
            'booked' => $booked,
            'sample' => $sample,
        ];
    }

    /**
     * Posibilidad na mabook ang isang PARATING na petsa/slot.
     *
     * Kung nabook o naka-block na ito, hindi na ito tanong — 1.0 at 0.0
     * ayon sa pagkakasunod. Sinasala naman ito ng mga advisor bago pa
     * marating ang puntong ito.
     */
    public function probability(Carbon $date, string $slot): float
    {
        if ($this->isBlocked($date)) {
            return 0.0;
        }

        if ($this->isOccupied($date, $slot)) {
            return 1.0;
        }

        return $this->fillRate($date->dayOfWeek, $slot)['rate'];
    }

    /**
     * Confidence na base LAMANG sa laki ng sample, hindi sa accuracy.
     * 12 obserbasyon (mga 3 buwan ng isang araw-ng-linggo) ang itinuturing
     * na buo; wala pa noon, proporsyonal.
     */
    public static function confidence(int $sample): float
    {
        return round(min(1.0, $sample / 12) * 100, 2);
    }

    // ── Presyo at inaasahang kita ──────────────────────────────────

    /** @return array{base: float, discount: float, total: float, promo: ?\App\Models\Discount} */
    public function quote(Carbon $date, string $slot): array
    {
        $key = $date->format('Y-m-d').'|'.$slot;

        if (isset($this->quoteCache[$key])) {
            return $this->quoteCache[$key];
        }

        [$checkIn] = Booking::slotDateTimes($slot, $date->format('Y-m-d'));

        return $this->quoteCache[$key] = $this->villa->quoteFor($checkIn, $slot);
    }

    /** Ang aktwal na sisingilin ngayon sa petsang ito (kasama ang umiiral na promo). */
    public function price(Carbon $date, string $slot): float
    {
        return (float) $this->quote($date, $slot)['total'];
    }

    public function expectedRevenue(Carbon $date, string $slot): float
    {
        return round($this->probability($date, $slot) * $this->price($date, $slot), 2);
    }

    /** May tumatamang promo na ba sa petsa/slot na ito? */
    public function hasPromo(Carbon $date, string $slot): bool
    {
        return $this->quote($date, $slot)['promo'] !== null;
    }

    // ── Kalagayan ng kalendaryo ────────────────────────────────────

    /**
     * May booking na ba sa petsa/slot na ito?
     *
     * Kasama ang `pending` — ang guest na kasalukuyang nasa checkout ay may
     * hawak na slot. Mas mabuting laktawan ang isang petsang malamang
     * mabenta na kaysa mag-alok ng diskuwento sa ibabaw ng isang booking
     * na paparating na.
     */
    public function isOccupied(Carbon $date, string $slot): bool
    {
        $this->loadOccupancy();

        return isset($this->occupied[$date->format('Y-m-d').'|'.$slot]);
    }

    public function isBlocked(Carbon $date): bool
    {
        return $this->blockReason($date) !== null;
    }

    /**
     * May aktibong `pricing_rules` na ba sa petsang ito?
     *
     * Hindi dinadaanan ng engine ang isang petsang may sadyang itinakdang
     * presyo — iyon ay desisyon ng may-ari, at ang pagmumungkahi ng iba sa
     * ibabaw nito ay pakikialam, hindi payo.
     */
    public function hasPricingRule(Carbon $date): bool
    {
        $this->loadPricingRules();

        return isset($this->ruled[$date->format('Y-m-d')]);
    }

    public function blockReason(Carbon $date): ?string
    {
        $this->loadBlocks();

        return $this->blocked[$date->format('Y-m-d')] ?? null;
    }

    public function holidayName(Carbon $date): ?string
    {
        return $this->holidays->name($date);
    }

    // ── Preloading (isang query bawat isa, hindi kada araw) ────────

    private function horizonEnd(): Carbon
    {
        $days = max(
            $this->lookaheadDays(),
            (int) config('prescriptive.maintenance_horizon_days', 60)
        );

        return Carbon::today()->addDays($days + 1);
    }

    private function loadOccupancy(): void
    {
        if ($this->occupied !== null) {
            return;
        }

        $this->occupied = [];

        $from = Carbon::today()->subDays($this->lookbackDays() + 1);

        Booking::where('property_id', $this->villa->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('check_in_date', [$from->toDateString(), $this->horizonEnd()->toDateString()])
            ->get(['check_in_date', 'check_in_time'])
            ->each(function ($booking) {
                $date = $booking->check_in_date->format('Y-m-d');
                $time = Carbon::parse($booking->check_in_time)->format('H:i');

                foreach (Booking::SLOTS as $slot => $def) {
                    if ($def['check_in'] === $time) {
                        $this->occupied[$date.'|'.$slot] = true;

                        return;
                    }
                }

                // Lumang booking na walang tugmang slot (bago ang fixed slots):
                // ituring na sakop ang BUONG araw sa halip na ipagwalang-bahala
                // — nangyari talaga iyon, at hindi tama ang mag-alok ng promo
                // sa ibabaw nito.
                foreach (array_keys(Booking::SLOTS) as $slot) {
                    $this->occupied[$date.'|'.$slot] = true;
                }
            });
    }

    private function loadPricingRules(): void
    {
        if ($this->ruled !== null) {
            return;
        }

        $this->ruled = [];

        $this->villa->pricingRules()
            ->where('is_active', 1)
            ->whereDate('end_date', '>=', Carbon::today()->toDateString())
            ->whereDate('start_date', '<=', $this->horizonEnd()->toDateString())
            ->get(['start_date', 'end_date'])
            ->each(function ($rule) {
                for ($d = $rule->start_date->copy(); $d->lte($rule->end_date); $d->addDay()) {
                    $this->ruled[$d->format('Y-m-d')] = true;
                }
            });
    }

    private function loadBlocks(): void
    {
        if ($this->blocked !== null) {
            return;
        }

        $this->blocked = [];

        $from = Carbon::today()->subDays($this->lookbackDays() + 1);

        AvailabilityBlock::where('property_id', $this->villa->id)
            ->whereDate('end_date', '>=', $from->toDateString())
            ->whereDate('start_date', '<=', $this->horizonEnd()->toDateString())
            ->get(['start_date', 'end_date', 'reason'])
            ->each(function ($block) {
                for ($d = $block->start_date->copy(); $d->lte($block->end_date); $d->addDay()) {
                    $this->blocked[$d->format('Y-m-d')] = $block->reason;
                }
            });
    }
}
