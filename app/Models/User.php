<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $full_name
 * @property string|null $email
 * @property string $password
 * @property string|null $phone
 * @property string $role
 * @property string|null $profile_image
 * @property string|null $address
 * @property string|null $id_type
 * @property string|null $id_number
 * @property int $status 1=Active, 0=Deactivated
 * @property bool $two_factor_enabled
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $last_login
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read string|null $profile_image_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HousekeepingTask> $housekeepingTasks
 * @property-read int|null $housekeeping_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoginActivity> $loginActivities
 * @property-read int|null $login_activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StaffLog> $staffLogs
 * @property-read int|null $staff_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TrustedDevice> $trustedDevices
 * @property-read int|null $trusted_devices_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfileImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @property bool $email_notifications_enabled
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailNotificationsEnabled($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * Live na Total Guests sa admin dashboard (tingnan ang DashboardStats).
     *
     * Sinasadyang MAKITID ang kondisyon: ang User ay sine-save sa bawat
     * login, 2FA, pag-update ng profile at iba pa. Ang bilang ng guest ay
     * nagbabago LANG kapag may bagong account (pagrehistro, walk-in, admin)
     * o binago ang role — kung hindi, bawat login ay magiging broadcast
     * sa Pusher na walang anumang binago.
     */
    protected static function booted(): void
    {
        static::saved(function ($user) {
            // `email`: dito nakabatay ang registered count ng Total Guests.
            if ($user->wasRecentlyCreated || $user->wasChanged(['role', 'email'])) {
                \App\Services\DashboardStats::touch();
            }
        });

        static::deleted(fn () => \App\Services\DashboardStats::touch());
    }

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'role',
        'profile_image',
        'address',
        // `id_type` / `id_number` are deliberately NOT fillable.
        //
        // They are government-issued identifiers — "sensitive personal
        // information" under RA 10173 §3(l) — and as of v7.40 NOTHING in the app
        // reads or writes them: no controller, no form, no view, no seeder. They
        // are reserved schema, not a feature.
        //
        // Being in $fillable made them an unguarded write target for a value
        // nothing validates and nothing needs. No mass-assignment call exists
        // today (every write in this codebase passes an explicit array), so this
        // is closing the surface before something opens it, not fixing a live
        // hole. See also $hidden below, and StaffLog::REDACTED_KEYS.
        //
        // If identity capture at check-in is ever actually built: set these from
        // a controller with explicit validation, do not re-add them here, and
        // consider an encrypted cast — plus update the privacy policy, which was
        // corrected in v7.40 to stop promising a collection that never happened.
        'status',
        'last_login',
        'two_factor_enabled',
        'email_notifications_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Never let a government ID reach a JSON payload or a serialised model,
        // whatever loaded the row. Three public pages eager-load whole `users`
        // rows into view data (narrowed in v7.40, but $hidden holds even if a
        // future query widens again), and $hidden is what makes that structural
        // rather than a property of each individual query.
        'id_type',
        'id_number',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'password' => 'hashed',
        'status' => 'integer',
        'two_factor_enabled' => 'boolean',
        'email_notifications_enabled' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function staffLogs()
    {
        return $this->hasMany(StaffLog::class);
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class, 'assigned_to');
    }

    public function trustedDevices()
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class);
    }

    /**
     * Cut every OTHER way into this account. Call it whenever the password
     * changes — a reset or a profile change.
     *
     * Rotating the password and `remember_token` was not enough, and the gap
     * defeated the whole point of the reset flow: "someone is in my account"
     * → reset the password → **the intruder is still logged in**. Their
     * session row in `sessions` was untouched and stayed valid for the rest of
     * SESSION_LIFETIME, and their browser's `trusted_device` cookie kept
     * skipping 2FA for up to TRUSTED_DEVICE_DAYS (60).
     *
     * Two things are revoked:
     *
     * 1. **Other sessions.** Only meaningful on the `database` session driver,
     *    which is what production runs; the `file` driver used in local dev
     *    stores no user id, so there is nothing to select on and this is a
     *    no-op there. That asymmetry is why the guard is a config check and
     *    not a try/catch — a silent no-op in dev must not look like success.
     *    `$keepSessionId` spares the caller's own session, so changing your
     *    password from the profile page doesn't log you out of it. A reset
     *    passes NULL: there is no session to keep.
     *
     * 2. **Trusted devices.** All of them, including the caller's. A trusted
     *    device is precisely a stored "skip the second factor" grant, so
     *    leaving one alive after a password change would leave the cheapest
     *    route in open. The cost is one emailed code at the next login on
     *    each device, which is the correct trade.
     *
     * @return array{sessions: int, devices: int} how many rows each part removed
     */
    public function revokeOtherLogins(?string $keepSessionId = null): array
    {
        $sessions = 0;
        $table = config('session.table', 'sessions');

        if (config('session.driver') === 'database' && Schema::hasTable($table)) {
            $sessions = DB::table($table)
                ->where('user_id', $this->id)
                ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
                ->delete();
        }

        $devices = $this->trustedDevices()->delete();

        return ['sessions' => $sessions, 'devices' => $devices];
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Ang sariling portal ng user na ito — ang "uwian" niya.
     *
     * Ang public landing page ay HINDI customer-only: nabubuksan din ito ng
     * naka-login na admin o staff (halimbawa, para tingnan ang epekto ng isang
     * bagong setting). Dati, isang bare `@auth` ang nag-aalok sa kanilang
     * lahat ng "My Bookings" papuntang /my/ — na sinasagot ng RoleMiddleware
     * ng 403, at mula v7.37 ay itinatala pa ni RecordSecurityResponses bilang
     * security event. Sariling dashboard na ang inaalok ngayon sa bawat role.
     *
     * NULL kapag walang kilalang portal ang role: mas mabuting walang button
     * kaysa sa isang button na 403.
     *
     * Kapareha ito ng mapa sa AuthController::redirectByRole() — kapag may
     * idinagdag na bagong role, dalawa ang dapat baguhin.
     */
    public function homeRouteName(): ?string
    {
        return match ($this->role) {
            'admin' => 'admin.dashboard',
            'staff' => 'staff.frontdesk',
            'customer' => 'customer.home',
            default => null,
        };
    }

    /**
     * Ang label ng link sa itaas. Hiwalay sa homeRouteName() dahil magkaiba
     * ang tawag ng bawat portal sa sarili nito sa loob ng app.
     */
    public function homeLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin Panel',
            'staff' => 'Front Desk',
            default => 'My Bookings',
        };
    }

    /**
     * Where this user makes a booking when they are NOT a guest — NULL for a
     * customer, who books through the public portal.
     *
     * The public booking flow writes `user_id = Auth::id()`, so an admin or
     * staff account that goes through it becomes the "guest" of a real,
     * slot-holding booking it cannot even open afterwards (/my/ is
     * role:customer). Their bookings are made for someone else, and these two
     * forms are the ones that ask who that is.
     */
    public function bookingToolRouteName(): ?string
    {
        return match ($this->role) {
            'admin' => 'admin.bookings.create',
            'staff' => 'staff.walkin',
            default => null,
        };
    }

    /** NULL exactly when bookingToolRouteName() is, so a view can `??` on it. */
    public function bookingToolLabel(): ?string
    {
        return match ($this->role) {
            'admin' => 'Create a booking in Admin',
            'staff' => 'Create a walk-in booking',
            default => null,
        };
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }

    public function getFullNameAttribute($value): string
    {
        return ucwords($value);
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        // Dating `Storage::disk('public')->url()` na nakabalot sa
        // try/catch. Ang problema ay hindi ang catch kundi ang tawag:
        // sa Cloudinary, ang url() ay isang LIVE na Admin API call kada
        // larawan kada render, at 500/oras lang ang libreng quota — kaya
        // "Rate Limit Exceeded" at nawawala ang lahat ng larawan sa
        // buong site hanggang sa susunod na oras. Tingnan ang
        // MediaUrlHelper.
        return \App\Helpers\MediaUrlHelper::resolve($this->profile_image);
    }

    // ── Pagiging regular na customer ───────────────────────────────

    /**
     * ANG IISANG KAHULUGAN ng "natapos na stay".
     *
     * Dalawang bagay ang nagtatanong nito at dapat hindi sila mag-iba:
     * ang completedStayCount() (isang guest, pagpepresyo) at ang
     * scopeWithCompletedStays() (maraming guest, ang audience ng
     * anunsyo). Noong magkahiwalay ang dalawa, ang blast ay napunta sa
     * lahat habang ang presyo ay para lang sa iilan.
     */
    public const COMPLETED_STAY_STATUS = 'checked_out';

    /** Memo kada instance — tingnan ang completedStayCount(). */
    private ?int $completedStayCount = null;

    /**
     * Ilang stay na ang TUNAY NANG natapos ng guest na ito.
     *
     * Ito ang sinusukat ng isang promong `guest_scope = 'returning'`
     * (tingnan ang Discount::isValidOn()).
     *
     * `checked_out` LAMANG ang binibilang, at mahalaga ang bawat
     * hindi-kasama:
     *
     *   - `pending` / `confirmed` — hindi pa natutuloy ang stay. Kung
     *     bibilangin ang mga ito, kayang gawing "regular" ang sarili ng
     *     sinuman sa pamamagitan ng paggawa ng mga booking na hindi
     *     naman babayaran — isang diskuwentong binili ng wala.
     *   - `checked_in` — nasa villa pa sila ngayon. Hindi pa tapos ang
     *     stay, kaya hindi pa ito dapat maging basehan ng bawas.
     *   - `cancelled` / `no_show` — walang nangyaring stay.
     *
     * Hindi ito sumusuri ng petsa: ang tanong ay "ilan na ang natapos
     * NGAYON", kaya ang isang guest na may dalawang tapos nang stay ay
     * regular na kahit gaano pa kalayo sa hinaharap ang bino-book niya.
     *
     * Hindi ito nakaka-dedup ng magkaparehong tao na may dalawang User
     * row (hal. dalawang email) — walang identity matching ang sistema.
     * Ang pagpili ni staff sa umiiral nang guest sa walk-in dropdown ang
     * tunay na panlaban doon.
     */
    public function completedStayCount(): int
    {
        // Ang isang guest-aware na quote ay maaaring tawagin nang
        // paulit-ulit sa isang request (preview, form, submit), kaya
        // isang COUNT lang kada instance.
        return $this->completedStayCount ??= $this->bookings()
            ->where('status', static::COMPLETED_STAY_STATUS)
            ->count();
    }

    /**
     * Ang MARAMIHANG anyo ng isReturningGuest(), bilang isang SQL
     * predicate.
     *
     * Kailangan ito ng anunsyo ng promo: ang pagtawag ng
     * completedStayCount() kada guest ay isang COUNT kada row, at ang
     * buong punto ng chunkById() ay hindi hawakan ang lahat ng user
     * nang sabay. Ang `whereHas(..., '>=', $min)` ay isang sub-select,
     * kaya nananatiling isang query ang audience gaano man karami.
     *
     * Pareho ang kahulugan ng "natapos" dito at sa
     * completedStayCount() dahil pareho silang dumadaan sa
     * COMPLETED_STAY_STATUS.
     */
    public function scopeWithCompletedStays($query, int $min = 1)
    {
        return $query->whereHas(
            'bookings',
            fn ($b) => $b->where('status', static::COMPLETED_STAY_STATUS),
            '>=',
            max(1, $min)
        );
    }

    /**
     * May sapat na bang natapos na stay ang guest para sa threshold na
     * ito? Tumatanggap ng threshold < 1 bilang 1 — ang isang promong
     * "returning" na nasisiyahan sa zero na stay ay para sa lahat, at
     * hindi iyon kailanman ang ibig sabihin ng admin.
     */
    public function isReturningGuest(int $minCompleted = 1): bool
    {
        return $this->completedStayCount() >= max(1, $minCompleted);
    }
}
