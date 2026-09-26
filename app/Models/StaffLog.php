<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action e.g. created_booking, updated_property, checked_in_guest
 * @property string|null $target_table
 * @property int|null $target_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $old_values Data before the change
 * @property array<array-key, mixed>|null $new_values Data after the change
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereTargetTable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffLog whereUserId($value)
 * @mixin \Eloquent
 */
class StaffLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'target_table',
        'target_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Actions where a NULL `user_id` means "nobody was signed in", as opposed
     * to "the scheduler did it".
     *
     * Both cases store NULL, and until the audit viewer existed the ambiguity
     * cost nothing because nothing read the column. It costs a great deal
     * now: rendering a failed login attempt as "System" would credit the
     * resort's own scheduler with an outsider's password guess.
     */
    public const UNAUTHENTICATED_ACTIONS = [
        'login_failed',
        'login_lockout',
        'login_rejected_inactive',
        'two_factor_failed',
        'two_factor_exhausted',
        'password_reset_completed',
    ];

    /**
     * The actions worth reading first after an incident — authentication
     * outcomes, credential changes, and changes to who can do what.
     *
     * Kept as data rather than as a `like 'login_%'` pattern because the
     * membership is a judgement about consequence, not about spelling:
     * `two_factor_disabled` and `toggled_user_status` share no prefix with
     * anything, and `user_login` is deliberately absent — 465 of the table's
     * 1,196 rows are successful logins, and letting them in would bury the
     * 30 rows that actually want reading.
     */
    public const SECURITY_ACTIONS = [
        'login_failed',
        'login_lockout',
        'login_rejected_inactive',
        'two_factor_failed',
        'two_factor_exhausted',
        'two_factor_disabled',
        'two_factor_enabled',
        'password_changed',
        'password_reset_completed',
        'trusted_device_removed',
        'account_self_deactivated',
        'created_user',
        'updated_user',
        'deleted_user',
        'toggled_user_status',
        'admin_profile_updated',
        'updated_settings',

        // The monitoring events added in v7.41 (Task 12). They belong here for
        // two reasons at once: this list is what the audit viewer's security
        // filter reads, so an admin following an alert link actually finds them —
        // and it is the tier `staff-logs:prune` keeps for 365 days rather than 90.
        // Without these entries the records of a credential spray would be the
        // first thing to expire, which is precisely backwards.
        'authorization_failed',
        'authorization_failed_escalated',
        'rate_limited',
        'rate_limited_escalated',
        'webhook_signature_rejected',
        'webhook_signature_rejected_escalated',
        'cron_secret_rejected',
        'cron_secret_rejected_escalated',
        'login_failed_burst',
        'login_failed_burst_escalated',
        'credential_spray',
        'credential_spray_escalated',
        // Not a security event, but the retention argument is the same: an error
        // that was only noticed weeks later is the one you most need the record of.
        'application_error',
        'application_error_escalated',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who to show for this row. A NULL `user_id` is not one thing — see
     * UNAUTHENTICATED_ACTIONS above.
     */
    public function getActorLabelAttribute(): string
    {
        if ($this->user) {
            return $this->user->full_name;
        }

        return in_array($this->action, self::UNAUTHENTICATED_ACTIONS, true)
            ? 'Not signed in'
            : 'System';
    }

    public function getIsSecurityEventAttribute(): bool
    {
        return in_array($this->action, self::SECURITY_ACTIONS, true);
    }

    /**
     * `created_booking` -> `Created booking`. Presentation only; the stored
     * value is never rewritten, because it is the thing being audited.
     */
    public function getActionLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * The keys that actually differ between old_values and new_values.
     *
     * Only PropertyController::update() populates those two columns today
     * (76 of 1,196 rows), and it stores the ENTIRE row on both sides — so
     * rendering them raw shows forty identical fields around the one that
     * moved. Returns [key => [old, new]] for the fields that changed, and an
     * empty array when there is nothing to compare.
     */
    public function changedValues(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        if (! $old && ! $new) {
            return [];
        }

        $changed = [];

        foreach (array_keys($old + $new) as $key) {
            // `updated_at` moves on every single write; it is noise in a list
            // whose whole purpose is to show what a person changed.
            if ($key === 'updated_at') {
                continue;
            }

            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($before !== $after) {
                $changed[$key] = [$before, $after];
            }
        }

        return $changed;
    }

    // ── Static helper to quickly log any action ────────────────────

    /**
     * The column widths this method writes into. MySQL runs with
     * STRICT_TRANS_TABLES, so an over-long value is not quietly trimmed —
     * it is SQLSTATE[22001] and the whole INSERT is refused.
     */
    private const MAX_LENGTHS = [
        'action'       => 100,
        'target_table' => 50,
        'ip_address'   => 45,
        'user_agent'   => 255,
    ];

    /**
     * Write one audit row.
     *
     * TWO THINGS HERE ARE LOAD-BEARING, and both exist because of a measured
     * failure rather than a tidiness preference.
     *
     * 1. `user_agent` IS ATTACKER-CONTROLLED INPUT and it lands in a
     *    varchar(255). Under STRICT_TRANS_TABLES a 400-character
     *    `User-Agent:` header made this INSERT throw. Measured end to end
     *    against the real MySQL database, an admin deleting a user:
     *
     *      User-Agent 400 chars -> user row DELETED, audit rows written: 0
     *      User-Agent  80 chars -> user row DELETED, audit rows written: 1
     *
     *    Same actor, same action, same code path. Because every call site
     *    records AFTER the action it describes, the action committed and the
     *    only record of who did it was never written. One header was a switch
     *    that turned the audit log off, across all 56 call sites at once.
     *
     *    So every caller-supplied string is cut to its column width first. A
     *    truncated user agent is worth incomparably more than no row at all.
     *
     * 2. The INSERT is wrapped. Truncation removes the cause we found; it
     *    cannot promise there is no other. A deadlock, a dropped connection
     *    or a future column would each bring back the same shape of bug — and
     *    at FrontDeskController's walk-in, where record() runs INSIDE
     *    reserveSlot()'s transaction, it would additionally roll back a real
     *    booking and a real cash payment with a guest standing at the counter.
     *
     *    The deliberate trade: this FAILS OPEN. A business action is never
     *    destroyed by the logging of it. An audit log that can veto a payment
     *    is a denial of service waiting to be found — and the fail-closed
     *    behaviour we had was not a decision anyone made, it was this bug.
     *    The cost is that a lost row is now silent to the user, so it is
     *    shouted at CRITICAL instead, carrying the entire entry so it can be
     *    reconstructed from the application log.
     */
    public static function record(
        string $action,
        string $targetTable = null,
        int $targetId = null,
        string $description = null,
        array $oldValues = null,
        array $newValues = null
    ): void {
        $attributes = [
            'user_id'      => Auth::id(),
            'action'       => self::fit('action', $action),
            'target_table' => self::fit('target_table', $targetTable),
            'target_id'    => $targetId,
            'description'  => $description,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'ip_address'   => self::fit('ip_address', request()->ip()),
            'user_agent'   => self::fit('user_agent', request()->userAgent()),
        ];

        try {
            static::create($attributes);
        } catch (\Throwable $e) {
            Log::critical('AUDIT WRITE FAILED — this action is not in staff_logs.', [
                'action'    => $attributes['action'],
                'user_id'   => $attributes['user_id'],
                'target'    => $attributes['target_table'].'#'.$attributes['target_id'],
                'entry'     => $attributes,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a change, storing ONLY the fields that actually moved.
     *
     * `old_values` and `new_values` have existed since the table was created
     * and, before this, exactly ONE of 56 call sites populated them. The cost
     * was not theoretical. These are two real rows from the live table:
     *
     *   id=819  users#3  "Updated user account: Nick Salvador"  old: NULL  new: NULL
     *   id=825  users#3  "Updated user account: Nick Salvador"  old: NULL  new: NULL
     *
     * `Admin\UserController::update()` accepts `role` AND `password`. One of
     * those two edits may have promoted a customer to admin and the log
     * cannot say which — it is word-for-word identical to a corrected phone
     * number. Likewise all 44 `updated_settings` rows read "Resort settings
     * updated", so a changed deposit percentage or a disabled maintenance
     * mode left no fingerprint at all.
     *
     * WHY ONLY THE CHANGED SUBSET, and not both whole rows the way
     * PropertyController::update() does it: a full `toArray()` pair buries
     * the one field that moved under forty identical ones, and it copies
     * every column into the audit table on every edit. `changedValues()`
     * reads both shapes, so the existing full-row rows keep rendering.
     *
     * NOTHING SENSITIVE IS EVER STORED — see REDACTED_KEYS. An audit trail
     * that quietly accumulates password hashes is a second credential store
     * with none of the protections of the first, and this method is called
     * from exactly the places that handle them.
     */
    public static function recordChange(
        string $action,
        ?string $targetTable,
        ?int $targetId,
        string $description,
        array $before,
        array $after
    ): void {
        $changed = self::diff($before, $after);

        // Nothing moved. An audit row claiming a change where there was none
        // is worse than no row: it makes every genuine entry less believable.
        if (! $changed) {
            return;
        }

        static::record(
            $action,
            $targetTable,
            $targetId,
            $description.' — changed: '.implode(', ', array_keys($changed)),
            array_map(fn ($pair) => $pair[0], $changed),
            array_map(fn ($pair) => $pair[1], $changed)
        );
    }

    /**
     * Field names whose VALUES must never reach the audit table. The field
     * still appears in the changed list, so "the password was reset" is
     * recorded — only the secret itself is withheld.
     */
    public const REDACTED_KEYS = [
        'password',
        'remember_token',
        'token',
        'token_hash',
        'two_factor_secret',
        'api_token',
        // Not credentials, but identifiers that must not accumulate in an
        // append-only table nothing ever prunes (v7.40).
        //
        // `id_number` is a government-issued identifier. Nothing writes it today,
        // so this is pre-emptive: if identity capture is ever built, the audit
        // trail records THAT it changed without becoming a second copy of every
        // guest's ID number.
        //
        // `account_number` is a bank or e-wallet account. Both refund-destination
        // call sites already keep it out of the log on purpose, with comments
        // saying why — but that is a convention enforced at two call sites, and a
        // third one written later would not inherit it. This makes it a property
        // of the log instead.
        'id_number',
        'account_number',
    ];

    /**
     * [key => [before, after]] for the fields that differ.
     *
     * Comparison is loose-by-string on purpose: a form posts "4000" where the
     * model holds the int 4000, and a strict comparison would report that as
     * a change on every single save, filling the log with edits nobody made.
     */
    private static function diff(array $before, array $after): array
    {
        $changed = [];

        foreach (array_keys($before + $after) as $key) {
            if (in_array($key, ['updated_at', 'created_at'], true)) {
                continue;
            }

            $was = $before[$key] ?? null;
            $now = $after[$key] ?? null;

            if (self::sameValue($was, $now)) {
                continue;
            }

            $changed[$key] = in_array($key, self::REDACTED_KEYS, true)
                ? ['[redacted]', '[changed]']
                : [self::renderValue($was), self::renderValue($now)];
        }

        return $changed;
    }

    private static function sameValue($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }

        if ($a === null || $b === null) {
            return $a === $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * Arrays (amenities, JSON columns) are flattened so the viewer can print
     * them without deciding how to render nested structures; the column is
     * `json`, so a scalar stays a scalar.
     */
    private static function renderValue($value)
    {
        return is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Cut a value to its column width, counting CHARACTERS — the table is
     * utf8mb4 and MySQL measures varchar in characters, so substr() would
     * both over-trim and risk splitting a multi-byte sequence in half.
     */
    private static function fit(string $column, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, self::MAX_LENGTHS[$column]);
    }
}