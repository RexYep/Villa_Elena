<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use App\Models\StaffLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * THE MISSING HALF OF THE AUDIT TRAIL.
 *
 * `staff_logs` had 56 write sites and, before this controller, exactly zero
 * read sites — no admin page, no artisan command, no export. The only thing
 * in the codebase that ever SELECTed from it was a dedup check inside
 * AutoCheckInOutBookings, and that one reads a single action name.
 *
 * That is not a cosmetic gap. Production is Render with no shell access, in
 * front of an Aiven-hosted MySQL, so the owner had no way to read these rows
 * at all — not "an inconvenient way", none. An audit trail nobody can consult
 * records history for a reader who does not exist; it cannot answer a
 * question about a disputed refund, and it cannot show that anything was
 * attempted. Writing it was never the point.
 *
 * Read-only by construction. There is no store/update/destroy here and there
 * should never be one: a log an admin can edit is worth less than no log,
 * because it carries the authority of a record without the properties of one.
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request)
    {
        $filters = $request->validate([
            'action' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer',
            'target_table' => 'nullable|string|max:50',
            'target_id' => 'nullable|integer',
            'q' => 'nullable|string|max:150',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'security' => 'nullable|in:1',
        ]);

        $query = StaffLog::with('user:id,full_name,role')->latest('created_at')->latest('id');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // "System" and "Not signed in" are both stored as a NULL user_id —
        // see StaffLog::actorLabel(). `0` is the sentinel the dropdown uses
        // for them, since an empty string means "no filter at all".
        //
        // The cast is not decoration. `integer` validation ACCEPTS a numeric
        // string without converting it, so this arrives as "0", and a strict
        // `=== 0` silently fell through to `where('user_id', '0')` — which
        // matches nothing at all. The page rendered 200 with an empty state,
        // so the only thing that caught it was counting the rows a real
        // request returned against the 156 NULL-actor rows known to exist.
        if (isset($filters['user_id'])) {
            (int) $filters['user_id'] === 0
                ? $query->whereNull('user_id')
                : $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['target_table'])) {
            $query->where('target_table', $filters['target_table']);

            // Only meaningful alongside a table — `target_id` 42 on its own
            // spans bookings, users and payments at once.
            if (isset($filters['target_id'])) {
                $query->where('target_id', $filters['target_id']);
            }
        }

        if (! empty($filters['q'])) {
            $query->where('description', 'like', '%'.$filters['q'].'%');
        }

        // Half-open on the upper end via endOfDay() rather than whereDate():
        // whereDate() wraps the column in DATE(), which makes
        // staff_logs_created_at_index unusable and forces a scan — the exact
        // problem the index migration was written to remove.
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (! empty($filters['security'])) {
            $query->whereIn('action', StaffLog::SECURITY_ACTIONS);
        }

        $logs = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'actions' => $this->actionOptions(),
            'actors' => $this->actorOptions(),
            'tables' => $this->tableOptions(),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Successful sign-ins, read from `login_activities`.
     *
     * This exists because of F11. `completeLogin()` used to write the same
     * event twice — a `user_login` row in `staff_logs` AND a row here — and
     * the staff_logs copy was both the poorer record (no device label, no
     * "came in via an emailed code" flag) and 39% of the whole audit table.
     * Removing it without this page would have meant the audit surface could
     * show failed logins but not successful ones, which is the wrong half.
     *
     * Historical `user_login` rows are untouched and still appear on the main
     * audit page; the action dropdown is built from the data, so the name
     * stays selectable for as long as those rows exist.
     */
    public function signIns(Request $request)
    {
        $filters = $request->validate([
            'user_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'otp' => 'nullable|in:1',
            'staff' => 'nullable|in:1',
        ]);

        $query = LoginActivity::with('user:id,full_name,role,email')->latest('created_at')->latest('id');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        // A sign-in that had to pass an emailed code is a sign-in from a
        // device this account had not used before.
        if (! empty($filters['otp'])) {
            $query->where('via_new_device_otp', true);
        }

        // Privileged accounts only — the ones whose sign-ins are worth
        // reading first after an incident.
        if (! empty($filters['staff'])) {
            $query->whereIn('user_id', User::query()->select('id')->whereIn('role', ['admin', 'staff']));
        }

        return view('admin.audit.signins', [
            'signIns' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'actors' => $this->signInActors(),
            'stats' => [
                'total' => LoginActivity::count(),
                'today' => LoginActivity::where('created_at', '>=', now()->startOfDay())->count(),
                'new_device' => LoginActivity::where('via_new_device_otp', true)
                    ->where('created_at', '>=', now()->subDays(7))->count(),
                'privileged' => LoginActivity::whereIn('user_id',
                    User::query()->select('id')->whereIn('role', ['admin', 'staff']))
                    ->where('created_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    private function signInActors()
    {
        return User::query()
            ->select('id', 'full_name', 'role')
            ->whereIn('id', LoginActivity::query()->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Every action name that has ever been written, not a hardcoded list.
     *
     * It has to be derived. Three action names already in the table —
     * `booking_cancellation_reverted`, `refund_payout_reversed`,
     * `refund_transfer_fee_corrected` — exist in NO current source file; they
     * were written by code that has since been renamed or removed. A
     * hardcoded dropdown would silently hide them, which is precisely the
     * kind of history an audit log exists to keep.
     */
    private function actionOptions(): array
    {
        return StaffLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();
    }

    private function tableOptions(): array
    {
        return StaffLog::query()
            ->select('target_table')
            ->whereNotNull('target_table')
            ->distinct()
            ->orderBy('target_table')
            ->pluck('target_table')
            ->all();
    }

    /**
     * Only users who actually appear in the log — listing every account
     * would offer filters that can only ever return nothing.
     */
    private function actorOptions()
    {
        return User::query()
            ->select('id', 'full_name', 'role')
            ->whereIn('id', StaffLog::query()->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('full_name')
            ->get();
    }

    private function stats(): array
    {
        $since = now()->subDay();

        return [
            'total' => StaffLog::count(),
            'today' => StaffLog::where('created_at', '>=', now()->startOfDay())->count(),
            'failed_logins' => StaffLog::whereIn('action', ['login_failed', 'login_lockout'])
                ->where('created_at', '>=', $since)->count(),
            'security_24h' => StaffLog::whereIn('action', StaffLog::SECURITY_ACTIONS)
                ->where('created_at', '>=', $since)->count(),
        ];
    }
}
