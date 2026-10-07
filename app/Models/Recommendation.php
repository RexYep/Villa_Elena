<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One recommendation: "do this, on these dates, because of this fact".
 *
 * A row here is ONLY A SUGGESTION. It has no effect on prices, the calendar,
 * the frontdesk or any guest until the admin presses the button on its card
 * — that is when `Admin\PrescriptiveController::apply()` creates the real
 * record (promo, pricing rule, block, housekeeping task) or sends the
 * reminder, and fills in `applied_*`.
 *
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $summary
 * @property array<array-key, mixed>|null $evidence the facts shown on the card
 * @property \Illuminate\Support\Carbon $target_start
 * @property \Illuminate\Support\Carbon $target_end
 * @property string|null $slot
 * @property string $action_type
 * @property array<array-key, mixed> $action_payload
 * @property string $status new | applied | dismissed | expired
 * @property string $fingerprint
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property int|null $applied_by
 * @property string|null $applied_record_type
 * @property int|null $applied_record_id
 * @property \Illuminate\Support\Carbon|null $dismissed_at
 * @property string|null $dismiss_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $appliedBy
 * @property-read string $action_label
 * @property-read string $action_tag_class
 * @property-read string $action_icon
 * @property-read string $apply_label
 * @property-read string $confirm_message
 * @property-read string $window_label
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation actedOn()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation open()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereActionPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereActionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereAppliedRecordType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereDismissReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereDismissedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Recommendation whereId($value)
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
    // The stored values of the first three predate their names; they are
    // kept so rows written before the rules were simplified still belong to
    // the rule that now owns them.
    public const TYPE_PROMO = 'idle_date_promo';

    public const TYPE_PEAK_RATE = 'peak_rate';

    public const TYPE_MAINTENANCE = 'maintenance_window';

    public const TYPE_TURNOVER = 'turnover_clean';

    public const TYPE_BALANCE = 'balance_reminder';

    public const ACTION_CREATE_PROMO = 'create_promo';

    public const ACTION_CREATE_PRICING_RULE = 'create_pricing_rule';

    public const ACTION_CREATE_BLOCK = 'create_block';

    public const ACTION_CREATE_TASK = 'create_housekeeping_task';

    public const ACTION_SEND_REMINDER = 'send_balance_reminder';

    /**
     * How each kind of action is presented. One table, so the card, the
     * button, the history list and the dashboard cannot describe the same
     * action four different ways.
     *
     * @var array<string, array{label: string, tag: string, icon: string, button: string}>
     */
    private const ACTIONS = [
        self::ACTION_CREATE_PROMO => [
            'label' => 'Promotion', 'tag' => 'tag-amber', 'icon' => 'tag', 'button' => 'Create promo',
        ],
        self::ACTION_CREATE_PRICING_RULE => [
            'label' => 'Peak pricing', 'tag' => 'tag-green', 'icon' => 'graph-up-arrow', 'button' => 'Raise rate',
        ],
        self::ACTION_CREATE_BLOCK => [
            'label' => 'Maintenance', 'tag' => 'tag-blue', 'icon' => 'tools', 'button' => 'Block dates',
        ],
        self::ACTION_CREATE_TASK => [
            'label' => 'Housekeeping', 'tag' => 'tag-purple', 'icon' => 'brush', 'button' => 'Send task',
        ],
        self::ACTION_SEND_REMINDER => [
            'label' => 'Booking follow-up', 'tag' => 'tag-cyan', 'icon' => 'bell', 'button' => 'Send reminder',
        ],
    ];

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
        'status',
        'fingerprint',
        'generated_at',
        'applied_at',
        'applied_by',
        'applied_record_type',
        'applied_record_id',
        'dismissed_at',
        'dismiss_reason',
    ];

    protected $casts = [
        'evidence' => 'array',
        'action_payload' => 'array',
        'target_start' => 'date',
        'target_end' => 'date',
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

    /** Cards still waiting for the admin's decision. */
    public function scopeOpen($query)
    {
        return $query->where('status', 'new');
    }

    /**
     * Cards a person decided on. `expired` is left out on purpose: nobody
     * chose that, the opportunity simply passed, so it is not history.
     */
    public function scopeActedOn($query)
    {
        return $query->whereIn('status', ['applied', 'dismissed']);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Has the date passed? A card for Sep 8–14 is pointless on Sep 15 even
     * while its status is still `new` (the daily run expires it, but a page
     * can be open before that runs).
     */
    public function isExpired(): bool
    {
        return $this->target_end->lt(Carbon::today());
    }

    public function isActionable(): bool
    {
        return $this->status === 'new' && ! $this->isExpired();
    }

    /** The kind of decision — the tag on the card. */
    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action_type]['label'] ?? 'Action';
    }

    public function getActionTagClassAttribute(): string
    {
        return self::ACTIONS[$this->action_type]['tag'] ?? 'tag-neutral';
    }

    public function getActionIconAttribute(): string
    {
        return self::ACTIONS[$this->action_type]['icon'] ?? 'lightbulb';
    }

    /** The button says what it does, not "Apply". */
    public function getApplyLabelAttribute(): string
    {
        return self::ACTIONS[$this->action_type]['button'] ?? 'Apply';
    }

    /**
     * What pressing the button will do, said in full before it is done.
     * An action that changes what guests see or pay must be readable in one
     * place, not guessed from a button label.
     */
    public function getConfirmMessageAttribute(): string
    {
        $p = $this->action_payload ?? [];
        $window = $this->window_label;

        return match ($this->action_type) {
            self::ACTION_CREATE_PROMO => 'Create the promo "'.($p['label'] ?? $this->title)."\"? Guests will see the lower price for check-ins {$window} right away, on the landing page and at booking. You can switch it off any time under Promotions.",
            self::ACTION_CREATE_PRICING_RULE => "Raise the rate for check-ins {$window}? Guests who book those dates pay more from now on. Bookings already made keep their price. You can turn the rate off again from this page.",
            self::ACTION_CREATE_BLOCK => "Block the villa on {$window} for maintenance? Those dates stop being bookable right away. You can remove the block from the Calendar.",
            self::ACTION_CREATE_TASK => 'Send "'.($p['title'] ?? $this->title).'" to the staff frontdesk as an urgent task?',
            self::ACTION_SEND_REMINDER => 'Send '.($p['guest_name'] ?? 'the guest').' an in-app reminder about the balance on booking '.($p['booking_ref'] ?? '').'?',
            default => 'Apply this recommendation?',
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
