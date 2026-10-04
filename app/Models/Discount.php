<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Seasonal/automatic promo.
 *
 * WALANG itinatype na code ang guest. Ang isang promo ay tumatama kapag
 * pasok ang CHECK-IN date ng booking sa window nito (`start_date` →
 * `expiry_date`, pareho kasama) at tugma ang slot (`applies_to`). Iyon
 * ang ibig sabihin ng "seasonal" dito: petsa ng STAY ang batayan, hindi
 * petsa ng pag-book — para tugma sa `pricing_rules`, na ganoon din ang
 * hugis (tingnan ang Property::getPackagePrice()).
 *
 * Ang discount ay palaging kinukuwenta laban sa BASE RATE lang (package
 * price), hindi sa extras — tingnan ang Property::quoteFor().
 *
 * @property int $id
 * @property string|null $code
 * @property string|null $label
 * @property string|null $description
 * @property string $type
 * @property numeric $value
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property int $min_nights
 * @property int|null $usage_limit null = unlimited
 * @property int $used_count
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string $applies_to
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read string $slot_label
 * @property-read string $state
 * @property-read string $state_badge
 * @property-read string $value_label
 * @property-read string $window_label
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereAppliesTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereMinNights($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereNotifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUsageLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereUsedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Discount whereValue($value)
 * @mixin \Eloquent
 */
class Discount extends Model
{
    use HasFactory;

    /** Default na porsyento sa admin create form. */
    public const DEFAULT_PERCENTAGE = 20;

    protected $fillable = [
        'code',
        'label',
        'description',
        'type',
        'value',
        'start_date',
        'min_nights',
        'usage_limit',
        'used_count',
        'expiry_date',
        'applies_to',
        'guest_scope',
        'min_completed_bookings',
        'is_public',
        'is_active',
        'notified_at',
    ];

    protected $casts = [
        'value'                  => 'decimal:2',
        'start_date'             => 'date',
        'expiry_date'            => 'date',
        'min_completed_bookings' => 'integer',
        'is_active'              => 'boolean',
        'is_public'              => 'boolean',
        'notified_at'            => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // ── Validity ───────────────────────────────────────────────────

    /**
     * Buhay pa ba ang promo sa kabuuan — active, hindi pa ubos ang
     * usage limit, at hindi pa lampas ang expiry. HINDI nito sinusuri
     * ang partikular na check-in date; para doon, isValidOn().
     */
    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        // Inclusive ang huling araw — ang isang promo na "expires Aug 31"
        // ay dapat pa ring tumama sa isang Aug 31 na check-in.
        if ($this->expiry_date && $this->expiry_date->lt(Carbon::today())) return false;
        return true;
    }

    /**
     * Tumatama ba ang promo sa PARTIKULAR na check-in datetime, slot, at
     * guest?
     *
     * Dalawa ang aksis ng pagiging karapat-dapat, at magkaiba sila:
     *
     *   KAILAN / ALING SLOT — ang date window at `applies_to`.
     *   SINO — ang `guest_scope`.
     *
     * Ang `$guest` ay nananatiling opsyonal dahil may mga tunay na
     * konteksto na walang guest (anonymous na price preview, ang
     * date×slot na list-price grid ng staff, ang prescriptive
     * forecasting). Tingnan ang isEligibleGuest() para sa dahilan kung
     * bakit NEGATIBO ang sagot sa mga iyon para sa isang
     * returning-guest na promo.
     */
    public function isValidOn(Carbon $checkIn, ?string $slot = null, ?User $guest = null): bool
    {
        if (! $this->isValid()) return false;

        $date = $checkIn->copy()->startOfDay();

        if ($this->start_date && $date->lt($this->start_date->copy()->startOfDay())) return false;
        if ($this->expiry_date && $date->gt($this->expiry_date->copy()->startOfDay())) return false;

        if ($slot !== null && $this->applies_to !== 'all' && $this->applies_to !== $slot) return false;

        if (! $this->isEligibleGuest($guest)) return false;

        return true;
    }

    /** Para lang ba sa mga regular na customer ang promong ito? */
    public function isReturningOnly(): bool
    {
        return $this->guest_scope === 'returning';
    }

    /**
     * Karapat-dapat ba ang guest na ito sa promong ito?
     *
     * Ang isang `all` na promo ay laging oo — kasama ang walang guest,
     * kaya hindi nagbabago ang pagpepresyo ng bawat umiiral nang promo.
     *
     * Ang isang `returning` na promo ay NAGSASARA kapag walang guest.
     * Sinasadya ito, at ito ang pinakamahalagang desisyon sa buong
     * tampok na ito: ang isang call site na nakalimutang ipasa ang guest
     * ay magbibigay ng LIST PRICE. Ang kapalit na default — ipagpalagay
     * na karapat-dapat — ay magpapakita ng mababang presyo sa preview at
     * saka magsisingil ng mataas sa submit, na siya mismong pagkakamaling
     * iniiwasan ng iisang choke point ng pagpepresyo (quoteFor()). Ang
     * mas malaking bayad ay hindi kailanman puwedeng maging sorpresa;
     * ang hindi inaasahang bawas naman ay maaari.
     */
    public function isEligibleGuest(?User $guest = null): bool
    {
        if (! $this->isReturningOnly()) return true;

        return $guest !== null
            && $guest->isReturningGuest((int) $this->min_completed_bookings);
    }

    // ── Computation ────────────────────────────────────────────────

    /**
     * Ilang piso ang babawas sa ibinigay na halaga. Hindi kailanman
     * hihigit sa halaga mismo — walang booking na magiging negatibo.
     */
    public function calculateDiscount(float $amount): float
    {
        if ($amount <= 0) return 0.0;

        $off = $this->type === 'percentage'
            ? $amount * (min(100, (float) $this->value) / 100)
            : (float) $this->value;

        return round(min($off, $amount), 2);
    }

    /**
     * Ang PINAKAMAGANDANG promo para sa isang check-in/slot/guest, o null.
     *
     * Kapag may dalawang magkapatong na promo, ang MAS MALAKING bawas sa
     * piso ang nananalo (hindi ang mas mataas na porsyento — magkaiba
     * iyon kapag may halong `fixed`). Deterministiko ito: pareho ang
     * mananalo tuwing tatawagin, kaya hindi puwedeng magkaiba ang
     * presyong nakita sa preview at ang aktwal na sinisingil.
     *
     * NAGPAPALIGSAHAN ang returning-guest na promo sa seasonal na promo —
     * HINDI nagsasalansan. Ang isang regular na customer sa gitna ng
     * isang seasonal promo ay nakukuha ang MAS MALAKI sa dalawa, hindi
     * ang pinagsama. Napagpasyahan ito: ang pagsasalansan ay
     * nangangahulugan ng pivot table, dahil iisa lang ang
     * `bookings.discount_id`, ang `discount_amount`, at ang `used_count`
     * — at ang buong pagtutuos ng promo ay nakatayo sa iisang-promo na
     * invariant na iyon.
     */
    public static function bestFor(float $baseAmount, Carbon $checkIn, ?string $slot = null, ?User $guest = null): ?self
    {
        if ($baseAmount <= 0) return null;

        $candidates = static::query()
            ->where('is_active', 1)
            ->get()
            ->filter(fn (self $d) => $d->isValidOn($checkIn, $slot, $guest));

        if ($candidates->isEmpty()) return null;

        return $candidates
            ->sortByDesc(fn (self $d) => [$d->calculateDiscount($baseAmount), $d->id])
            ->first();
    }

    /**
     * Mga promong dapat i-advertise sa landing page — public, active,
     * hindi pa ubos, at hindi pa tapos ang window.
     *
     * KASAMA rito ang mga `scheduled` pa lang (hindi pa dumarating ang
     * `start_date`). Sinasadya iyon: ang isang promong sasaklaw sa mga
     * stay sa Setyembre ay dapat makita ngayong Agosto — iyon mismo ang
     * panahong makakapag-impluwensya pa ito ng pag-book. Kung sa
     * unang araw pa lang ng window ito lalabas, na-advertise lang ito
     * habang tumatakbo na, kung kailan huli na para sa sinumang
     * nagpaplano nang maaga.
     *
     * Ang `expiry_date` lang ang tunay na hangganan dito: kapag lampas
     * na, wala nang stay na maaaring maabot pa nito.
     *
     * Tandaan: ito ay tungkol sa PAG-ANUNSYO. Ang pagpepresyo ay dumadaan
     * pa rin sa isValidOn(), na sinusuri ang aktwal na check-in date —
     * kaya ang isang paparating na promo ay ipinapakita rito nang maaga
     * pero hindi pa rin nagbabawas sa isang stay bago ang start_date.
     *
     * Ang `$viewer` ay ang taong titingin. Ang isang returning-guest na
     * promo ay inaalis kapag HINDI karapat-dapat ang titingin — kung
     * hindi, ang isang bagong bisita ay makakakita ng "20% OFF" sa
     * banner at sisingilin pa rin ng list price. Iyon ang eksaktong
     * mababang-preview/mataas-na-bayad na pagkakamali.
     *
     * KAAKIBAT NITO: kapag may returning-guest na promo pero hindi
     * karapat-dapat ang titingin, HINDI sapat ang manahimik lang. Sa
     * chatbot, ang "walang promo ngayon" ay isang PAGTANGGI, at ang
     * pagtanggi ay pahayag din (v7.20) — mali ito para sa isang regular
     * na hindi pa naka-login. Tingnan ang
     * returningOnlyExists() at ang ChatbotController.
     */
    public static function publicActive(?User $viewer = null)
    {
        return static::query()
            ->where('is_active', 1)
            ->where('is_public', 1)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', Carbon::today()))
            // Tumatakbo na muna bago ang paparating pa lang; sa loob ng
            // bawat pangkat, ang pinakamalapit nang magsimula ang una.
            ->orderByRaw('CASE WHEN start_date IS NULL OR start_date <= ? THEN 0 ELSE 1 END', [Carbon::today()->toDateString()])
            ->orderByRaw('start_date IS NULL DESC')
            ->orderBy('start_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (self $d) => ! $d->usage_limit || $d->used_count < $d->usage_limit)
            ->filter(fn (self $d) => $d->isEligibleGuest($viewer))
            ->values();
    }

    /**
     * Ano ang dapat sabihin sa titingin tungkol sa isang
     * returning-guest na promong HINDI niya nakukuha — o NULL kung
     * wala namang dapat sabihin.
     *
     * Ito ang sumasagot sa butas na iniiwan ng publicActive(): kapag
     * ang tanging buhay na promo ay returning-only at hindi
     * karapat-dapat ang titingin, walang maipapakita — pero hindi rin
     * tama ang sabihing "wala pong promo ngayon". Ang pagtanggi ay
     * pahayag din (v7.20).
     *
     * TATLONG ESTADO, at hindi isang bool (v7.50). Ang dating anyo ay
     * `returningOnlyExists()` + isang bool, at ang bool ay hindi
     * makapagsabi kung BAKIT hindi karapat-dapat ang tao — kaya ang
     * isang naka-sign-in nang guest na kulang pa sa threshold ay
     * sinasabihang "sign in". Hindi puwedeng maibalik sa isang bool:
     * iba ang dapat sabihin sa dalawang tao.
     *
     *   null      — wala siyang dapat malaman (walang buhay na promo,
     *               o nakukuha na niya ang isa).
     *   'guest'   — hindi naka-sign in. Ang sign-in ang susunod niyang
     *               hakbang.
     *   'short'   — naka-sign in, pero kulang pa ang natapos na stay.
     *               Ang sign-in ay WALANG kinalaman dito.
     *
     * Ang `need` ay ang PINAKAMABABANG threshold na hindi pa niya
     * naabot, hindi ang pinakamataas: iyon ang susunod na tunay na
     * maaabot. Ang pagbanggit ng mas mataas ay magkukuwento ng isang
     * promong baka hindi naman niya makamit kailanman.
     *
     * Pareho ang mga hangganan ng publicActive() — public, active,
     * hindi pa ubos, hindi pa lampas ang expiry — kaya hindi ito
     * mag-a-anunsyo ng promong hindi naman ipapakita.
     *
     * @return array{state: string, need: int, have: int}|null
     */
    public static function returningTeaserFor(?User $viewer = null): ?array
    {
        // Staff and admin never book as guests, so "You're N stays away"
        // means nothing to them.
        if ($viewer !== null && $viewer->role !== 'customer') {
            return null;
        }

        $live = static::query()
            ->where('is_active', 1)
            ->where('is_public', 1)
            ->where('guest_scope', 'returning')
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', Carbon::today()))
            ->get()
            ->filter(fn (self $d) => ! $d->usage_limit || $d->used_count < $d->usage_limit);

        if ($live->isEmpty()) {
            return null;
        }

        // May nakukuha na siyang returning promo — may TUNAY nang card
        // sa banner at tunay nang bawas sa presyo. Walang dapat i-tease.
        if ($live->contains(fn (self $d) => $d->isEligibleGuest($viewer))) {
            return null;
        }

        $need = (int) $live
            ->map(fn (self $d) => max(1, (int) $d->min_completed_bookings))
            ->min();

        return [
            'state' => $viewer === null ? 'guest' : 'short',
            'need'  => $need,
            'have'  => $viewer?->completedStayCount() ?? 0,
        ];
    }

    /**
     * Mukhang doble ba ang promong ito, laban sa isang kakagawa pa
     * lang? Nagbabalik ng dahilan sa prosa, o NULL.
     *
     * Bakit ito umiiral: ang pag-save ng promo ay maaaring magtagal
     * (ang anunsyo ay humahawak ng isang abiso kada guest), kaya ang
     * form ay mukhang nag-hang at napipindot muli. Nangyari na ito —
     * dalawang promong magkapareho (#28, #29), dalawang blast sa
     * parehong 8 guest, at ang `notified_at` ay walang nagawa dahil
     * MAGKAIBANG row ang dalawa. Binubura ni admin ang doble, pero
     * HINDI nababawi ang mga abisong naipadala na.
     *
     * Sinusundan nito ang hugis ng Payment::manualEntryProblem(): ang
     * isang kamukha ay HINDI mali, kaya hindi ito tinatanggihan nang
     * tuluyan — nagtatanong ito. Maaaring tunay na gustong gumawa ni
     * admin ng dalawang magkamukhang promo; ang hindi sinasadyang
     * doble ay mas malamang lang.
     *
     * Ang 10 minuto ay ang hangganan ng "kakagawa pa lang": sapat para
     * sa isang pag-double-click o sa isang pag-resubmit ng page,
     * maikli pa para hindi maharang ang isang promong talagang
     * ginagawa muli kinabukasan.
     */
    public static function duplicateProblem(array $data, bool $confirmed = false): ?string
    {
        if ($confirmed) {
            return null;
        }

        $twin = static::query()
            ->where('label', $data['label'] ?? '')
            ->where('type', $data['type'] ?? '')
            ->where('value', $data['value'] ?? 0)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->latest('id')
            ->first();

        if (! $twin) {
            return null;
        }

        return 'You created an identical promo — "' . $twin->label . '" (' . $twin->value_label . ') — '
            . $twin->created_at->diffForHumans() . '.'
            . ($twin->notified_at
                ? ' Guests have already been notified about it, and announcing a second copy would notify them twice.'
                : ' A second copy would be advertised and announced separately.')
            . ' If you really need two, tick "Create it anyway" to confirm.';
    }

    /** Nakatakda pa lang ba — hindi pa dumarating ang start_date? */
    public function isUpcoming(): bool
    {
        return $this->start_date && $this->start_date->gt(Carbon::today());
    }

    // ── Display helpers ────────────────────────────────────────────

    /** Hal. "20% OFF" o "₱500 OFF" */
    public function getValueLabelAttribute(): string
    {
        return $this->type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '% OFF'
            : '₱' . number_format((float) $this->value, 0) . ' OFF';
    }

    /**
     * Hinahango sa Booking::SLOTS, hindi isang `match` kada slot — ang
     * dating anyo ay tahimik na nagsasabing "Any slot" para sa isang
     * promong para lang sa 22-Hours, dahil walang sangay na tumutugma
     * doon. Ang isang bagong slot ay dapat lumitaw nang mag-isa.
     */
    public function getSlotLabelAttribute(): string
    {
        return Booking::SLOTS[$this->applies_to]['label'] ?? 'Any slot';
    }

    /**
     * Hal. "All guests" o "Returning guests (2+ stays)".
     *
     * Nandito ito at hindi sa view para sa parehong dahilan ng
     * slot_label: tatlong view ang nagpapakita nito, at ang isang
     * kopyang nauna o nahuli sa iba ay nagsasabi ng ibang panuntunan
     * kaysa sa aktwal na ipinapatupad.
     */
    public function getGuestScopeLabelAttribute(): string
    {
        if (! $this->isReturningOnly()) return 'All guests';

        $min = max(1, (int) $this->min_completed_bookings);

        return $min === 1
            ? 'Returning guests'
            : "Returning guests ({$min}+ stays)";
    }

    /**
     * Ang buong panuntunan sa pagiging karapat-dapat sa isang linya,
     * para sa guest — hal. "Returning guests (2+ completed stays)".
     * Hiwalay sa itaas: ang admin ay nagbabasa ng maikling badge, ang
     * guest ay kailangang maintindihan kung bakit.
     */
    public function getGuestScopeNoteAttribute(): ?string
    {
        if (! $this->isReturningOnly()) return null;

        $min = max(1, (int) $this->min_completed_bookings);

        return $min === 1
            ? 'For returning guests — applies once you have completed a stay with us.'
            : "For returning guests with at least {$min} completed stays.";
    }

    /**
     * Estado para sa admin list: scheduled / running / expired /
     * used_up / inactive.
     */
    public function getStateAttribute(): string
    {
        if (! $this->is_active) return 'inactive';
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return 'used_up';
        if ($this->expiry_date && $this->expiry_date->lt(Carbon::today())) return 'expired';
        if ($this->start_date && $this->start_date->gt(Carbon::today())) return 'scheduled';
        return 'running';
    }

    public function getStateBadgeAttribute(): string
    {
        return match ($this->state) {
            'running'   => '<span class="badge bg-success">Running</span>',
            'scheduled' => '<span class="badge bg-info">Scheduled</span>',
            'expired'   => '<span class="badge bg-secondary">Expired</span>',
            'used_up'   => '<span class="badge bg-warning text-dark">Fully used</span>',
            default     => '<span class="badge bg-secondary">Inactive</span>',
        };
    }

    /**
     * Para sa ADMIN lang ito (listahan ng promo, staff log). Ang "no end
     * date" ay tama roon: alam ni admin na siya ang may hawak ng dulo.
     * Para sa guest, tingnan ang guest_window_phrase sa ibaba.
     */
    public function getWindowLabelAttribute(): string
    {
        $from = $this->start_date ? $this->start_date->format('M d, Y') : 'Anytime';
        $to   = $this->expiry_date ? $this->expiry_date->format('M d, Y') : 'no end date';

        return $from . ' — ' . $to;
    }

    /**
     * Ang window sa salitang para sa GUEST — hal. "for stays until
     * Oct 06, 2026" o "until further notice".
     *
     * HINDI kailanman "no end date" (v7.51). Ang isang promong walang
     * `expiry_date` ay hindi pangakong walang hanggan: kaya itong
     * patayin ni admin anumang oras (toggle), o lagyan ng dulo sa
     * susunod na edit. Ang abisong nagsabing "no end date" ay nangako
     * ng bagay na walang paraan ang sistema para tuparin — at ang
     * abiso ay nakaimbak na teksto, hindi na nagbabago kapag nagbago
     * ang promo. "Until further notice" ang totoo.
     *
     * Hiwalay sa window_label sa parehong dahilan ng
     * guest_scope_label / guest_scope_note: magkaiba ang kailangang
     * malaman ng admin at ng guest.
     */
    public function getGuestWindowPhraseAttribute(): string
    {
        $from = $this->start_date?->format('M d, Y');
        $to   = $this->expiry_date?->format('M d, Y');

        return match (true) {
            $from && $to => "for stays from {$from} to {$to}",
            (bool) $to   => "for stays until {$to}",
            (bool) $from => "for stays from {$from}, until further notice",
            default      => 'until further notice',
        };
    }

    /**
     * Ang mga field na NAGBABAGO NG ALOK — kung magkano, kailan, aling
     * slot, at sino. Ang pagbabago sa alinman dito sa isang promong
     * naianunsyo na ay nagpapadala ng update sa mga guest (tingnan ang
     * PromotionController::notifyPromoChange()).
     *
     * SADYANG WALA rito ang `label` at `description`: ang panuntunang
     * "ang edit ay hindi nagbla-blast muli" ay para sa mga typo, at
     * nananatili iyon. Ang pagbabago sa pera o sa petsa ay hindi typo —
     * ibang alok na iyon.
     */
    public const MATERIAL_FIELDS = [
        'type', 'value', 'start_date', 'expiry_date',
        'applies_to', 'guest_scope', 'min_completed_bookings',
    ];

    /**
     * Ano ang nagbago mula sa `$old`, sa mga pangungusap na para sa
     * guest. Walang laman kung walang materyal na nagbago.
     *
     * Nandito sa model at hindi sa controller dahil ang bawat linya ay
     * binubuo mula sa mga label accessor (value_label, slot_label,
     * guest_window_phrase) — ang parehong mga salitang ginamit ng
     * orihinal na anunsyo, kaya ang "dati" sa update ay tumutugma sa
     * nabasa na ng guest.
     *
     * @return list<string>
     */
    public function guestFacingChangesFrom(self $old): array
    {
        $lines = [];

        if ($old->type !== $this->type || (float) $old->value !== (float) $this->value) {
            $lines[] = "The discount is now {$this->value_label} (previously {$old->value_label}).";
        }

        $day = fn ($d) => $d?->format('Y-m-d');
        if ($day($old->start_date) !== $day($this->start_date)
            || $day($old->expiry_date) !== $day($this->expiry_date)) {
            $lines[] = "It now applies {$this->guest_window_phrase} (previously: {$old->guest_window_phrase}).";
        }

        if ($old->applies_to !== $this->applies_to) {
            $lines[] = $this->applies_to === 'all'
                ? 'It now applies to every slot.'
                : "It now applies to {$this->slot_label} bookings only.";
        }

        $min = fn (self $d) => $d->isReturningOnly() ? max(1, (int) $d->min_completed_bookings) : 0;
        if ($min($old) !== $min($this)) {
            $lines[] = match (true) {
                ! $this->isReturningOnly() => 'It is now open to all guests.',
                $min($this) === 1          => 'It is now for returning guests — anyone who has completed a stay with us.',
                default                    => 'It is now for returning guests with at least ' . $min($this) . ' completed stays.',
            };
        }

        return $lines;
    }
}
