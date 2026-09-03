<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Isang PRESCRIPTIVE na mungkahi: "ito ang dapat gawin, ito ang dahilan,
 * ito ang tinatayang epekto".
 *
 * Ang isang row dito ay MUNGKAHI LANG. Wala itong epekto sa presyo, sa
 * kalendaryo, o sa nakikita ng guest hangga't hindi pumipindot ng Apply
 * ang admin — doon lang gagawin ng PrescriptiveController ang tunay na
 * record (Discount / AvailabilityBlock) at itatakda ang `applied_*`.
 *
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $summary
 * @property array<array-key, mixed>|null $evidence
 * @property \Illuminate\Support\Carbon $target_start
 * @property \Illuminate\Support\Carbon $target_end
 * @property string|null $slot day | night | null = pareho
 * @property string $action_type create_promo | create_block
 * @property array<array-key, mixed> $action_payload
 * @property numeric $expected_impact PHP; positibo = kita o naiwasang lugi
 * @property numeric|null $baseline_projection
 * @property numeric $confidence 0-100, base sa laki ng sample
 * @property int $sample_size ilang historical na obserbasyon ang pinagbatayan
 * @property string $status
 * @property string $fingerprint
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property int|null $applied_by
 * @property string|null $applied_record_type
 * @property int|null $applied_record_id
 * @property \Illuminate\Support\Carbon|null $dismissed_at
 * @property string|null $dismiss_reason
 * @property numeric|null $realized_impact
 * @property numeric|null $actual_revenue
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $appliedBy
 * @property-read string $action_label
 * @property-read string $action_tag_class
 * @property-read string $confidence_label
 * @property-read string $impact_caption
 * @property-read string $impact_label
 * @property-read float|null $projected_total
 * @property-read string $realized_label
 * @property-read string $slot_label
 * @property-read string $window_label
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation decided()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation measured()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation noAction()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation open()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereActionPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereActionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereActualRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedRecordType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereBaselineProjection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereConfidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereDismissReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereDismissedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereExpectedImpact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereRealizedImpact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereSampleSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereSettledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereSlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereTargetEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereTargetStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Recommendation extends Model
{
    public const TYPE_IDLE_PROMO = 'idle_date_promo';

    public const TYPE_MAINTENANCE = 'maintenance_window';

    public const TYPE_PEAK_RATE = 'peak_rate';

    public const ACTION_CREATE_PROMO = 'create_promo';

    public const ACTION_CREATE_BLOCK = 'create_block';

    public const ACTION_CREATE_PRICING_RULE = 'create_pricing_rule';

    protected $fillable = [
        'type',
        'title',
        'summary',
        'evidence',
        'target_start',
        'target_end',
        'slot',
        'action_type',
        'action_payload',
        'expected_impact',
        'confidence',
        'sample_size',
        'status',
        'fingerprint',
        'generated_at',
        'applied_at',
        'applied_by',
        'applied_record_type',
        'applied_record_id',
        'dismissed_at',
        'dismiss_reason',
        'realized_impact',
        'baseline_projection',
        'actual_revenue',
        'settled_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'action_payload' => 'array',
        'target_start' => 'date',
        'target_end' => 'date',
        'expected_impact' => 'decimal:2',
        'confidence' => 'decimal:2',
        'realized_impact' => 'decimal:2',
        'baseline_projection' => 'decimal:2',
        'actual_revenue' => 'decimal:2',
        'settled_at' => 'datetime',
        'generated_at' => 'datetime',
        'applied_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /** Mga mungkahing naghihintay pa ng desisyon ng admin. */
    public function scopeOpen($query)
    {
        return $query->where('status', 'new');
    }

    /** Naaksyunan na — para sa history/audit na listahan. */
    public function scopeDecided($query)
    {
        return $query->whereIn('status', ['applied', 'dismissed', 'expired']);
    }

    /**
     * Mga mungkahing may TOTOONG naitalang resulta.
     *
     * Hindi sapat ang `settled_at` — minamarkahan ding settled ang mga
     * maintenance card at ang mga nauna pa sa Phase 4, pero NULL ang
     * `realized_impact` nila dahil wala talagang masusukat. Ang mga iyon
     * ay walang lugar sa anumang kuwenta ng katumpakan.
     */
    public function scopeMeasured($query)
    {
        return $query->whereNotNull('settled_at')->whereNotNull('realized_impact');
    }

    /** Ang mga hindi kinilos — dito nasusuri ang modelo nang malinis. */
    public function scopeNoAction($query)
    {
        return $query->whereIn('status', ['dismissed', 'expired']);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Lipas na ba ang pagkakataon? Ang isang mungkahi para sa Set 8–14 ay
     * walang saysay kapag Set 15 na — hindi na ito puwedeng i-apply kahit
     * `new` pa ang status (hinuhuli ito ng generator at ini-expire, pero
     * puwedeng may nakabukas na page bago pa iyon tumakbo).
     */
    public function isExpired(): bool
    {
        return $this->target_end->lt(Carbon::today());
    }

    public function isActionable(): bool
    {
        return $this->status === 'new' && ! $this->isExpired();
    }

    public function getImpactLabelAttribute(): string
    {
        return '₱'.number_format((float) $this->expected_impact, 0);
    }

    /**
     * Ang confidence ay HINDI accuracy — sinusukat lang nito kung gaano
     * karaming totoong nakaraang araw ang pinagbatayan. Sinasadyang
     * salita ang ipinapakita, hindi porsyento lang, para hindi ito
     * mabasa bilang "85% tama ako".
     */
    public function getConfidenceLabelAttribute(): string
    {
        $c = (float) $this->confidence;

        return match (true) {
            $c >= 70 => 'High',
            $c >= 40 => 'Medium',
            default => 'Low',
        };
    }

    /** Anong uri ng desisyon ito — para sa tag sa card. */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_CREATE_PROMO => 'Pricing — discount',
            self::ACTION_CREATE_PRICING_RULE => 'Pricing — increase',
            self::ACTION_CREATE_BLOCK => 'Scheduling',
            default => 'Action',
        };
    }

    public function getActionTagClassAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_CREATE_PROMO => 'promo',
            self::ACTION_CREATE_PRICING_RULE => 'rate',
            self::ACTION_CREATE_BLOCK => 'block',
            default => '',
        };
    }

    /**
     * Ang salitang nasa ilalim ng halaga. Hindi lahat ng epekto ay
     * bagong kita — ang maintenance ay tungkol sa kita na HINDI nawala.
     */
    public function getImpactCaptionAttribute(): string
    {
        return $this->action_type === self::ACTION_CREATE_BLOCK
            ? 'revenue protected'
            : 'projected gain';
    }

    public function isMeasured(): bool
    {
        return $this->settled_at !== null && $this->realized_impact !== null;
    }

    /** Ang naganap, may tanda — "+₱1,200" o "−₱400". */
    public function getRealizedLabelAttribute(): string
    {
        if (! $this->isMeasured()) {
            return '—';
        }

        $value = (float) $this->realized_impact;

        return ($value >= 0 ? '+₱' : '−₱').number_format(abs($value), 0);
    }

    /**
     * Ang inaasahang KABUUAN kung susundin ang mungkahi — baseline kasama
     * ang ipinangakong dagdag. Ito ang tapat na katapat ng
     * `actual_revenue`, hindi ang `expected_impact` na mag-isa.
     */
    public function getProjectedTotalAttribute(): ?float
    {
        if ($this->baseline_projection === null) {
            return null;
        }

        return round((float) $this->baseline_projection + (float) $this->expected_impact, 2);
    }

    public function getSlotLabelAttribute(): string
    {
        return match ($this->slot) {
            'day' => Booking::SLOTS['day']['label'] ?? 'Day slot',
            'night' => Booking::SLOTS['night']['label'] ?? 'Night slot',
            default => 'Both slots',
        };
    }

    public function getWindowLabelAttribute(): string
    {
        if ($this->target_start->isSameDay($this->target_end)) {
            return $this->target_start->format('M j, Y');
        }

        return $this->target_start->format('M j').' – '.$this->target_end->format('M j, Y');
    }
}
