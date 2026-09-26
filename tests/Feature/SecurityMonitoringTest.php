<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\StaffLog;
use App\Models\User;
use App\Services\SecurityMonitor;
use App\Support\DestructiveCommandGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 12 — backup, monitoring and the tests for them.
 *
 * F1  no backup or restore existed, next to a documented command that drops
 *     production.
 * F2  authorization failures were completely silent. Measured before the fix:
 *     an authenticated cross-account request for another guest's payment data
 *     returned 403 and produced zero log lines, zero audit rows.
 * F3  twenty rate limiters, none of which recorded anything.
 * F4  rejected payment webhooks were logged but nobody was told.
 * F5  failed logins were audited but nothing alerted or correlated.
 */
class SecurityMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTables();
    }

    // ── SecurityMonitor itself ───────────────────────────────────────

    public function test_escalation_fires_exactly_on_the_threshold_and_not_after(): void
    {
        $admin = $this->makeAdmin();

        // `=== $threshold`, not `>=`. With `>=` every event past the threshold
        // would notify, which is precisely what trains people to ignore these.
        foreach ([1, 2, 3, 4] as $count) {
            SecurityMonitor::escalate('t_event', $count, 3, 'Title', 'Message');
        }

        $this->assertSame(1, DB::table('notifications')->where('user_id', $admin->id)->count(),
            'Escalation must notify once, on the crossing only.');
    }

    public function test_repeated_events_are_counted_but_audit_rows_stay_bounded(): void
    {
        // The flood guard. This class runs on every rejected request, so an
        // attacker picks how often it runs; an audit row per event would be a
        // write amplifier against a free-tier database.
        for ($i = 1; $i <= 25; $i++) {
            $count = SecurityMonitor::record('t_flood', 'ip:203.0.113.1', "event {$i}");
            $this->assertSame($i, $count, 'The window counter must keep counting.');
        }

        $this->assertSame(1, DB::table('staff_logs')->where('action', 't_flood')->count(),
            '25 events must not write 25 audit rows.');
    }

    public function test_distinct_counting_only_advances_for_new_items(): void
    {
        $bucket = 'ip:203.0.113.2';

        $this->assertSame(1, SecurityMonitor::countDistinct('t_spray', $bucket, 'a@example.test'));
        $this->assertSame(1, SecurityMonitor::countDistinct('t_spray', $bucket, 'a@example.test'),
            'The same item twice is still one distinct item.');
        $this->assertSame(2, SecurityMonitor::countDistinct('t_spray', $bucket, 'b@example.test'));
        $this->assertSame(3, SecurityMonitor::countDistinct('t_spray', $bucket, 'c@example.test'));

        // A different actor counts separately.
        $this->assertSame(1, SecurityMonitor::countDistinct('t_spray', 'ip:203.0.113.3', 'a@example.test'));
    }

    public function test_the_monitor_never_throws_when_its_own_counter_storage_is_broken(): void
    {
        // It is called from middleware wrapping every response, including the
        // PayMongo webhook, which must never return a non-2xx. A monitor that can
        // 500 the thing it watches is worse than no monitor.
        //
        // THIS TEST USED TO PROVE NOTHING, and a tamper is what exposed it. The
        // first version dropped `staff_logs` — but StaffLog::record() has its own
        // try/catch, so the exception never reached SecurityMonitor and the test
        // stayed green even with SecurityMonitor's catch block deleted. It was
        // measuring Task 8's guard, not this one.
        //
        // Breaking the CACHE is what reaches this class's own failure path:
        // bump() is the first thing record() calls, before any inner guard.
        config(['cache.default' => 'database']);
        Schema::dropIfExists('cache');

        $count = SecurityMonitor::record('t_broken', 'ip:203.0.113.4', 'the counter store is gone');

        $this->assertIsInt($count, 'record() must return a count rather than throwing.');
    }

    public function test_escalation_never_throws_when_it_cannot_reach_the_admins(): void
    {
        // The other half: notifyAdmin() queries `users` and writes
        // `notifications`. Either being unavailable must not propagate, because
        // escalate() is reached from the webhook path too.
        //
        // An admin has to exist first. Without one, notifyAdmin() iterates an
        // empty list, attempts no insert, and succeeds honestly — the first
        // version of this test asserted false and got true for exactly that
        // reason, which is a broken test rather than a broken guard.
        $this->makeAdmin();

        Schema::drop('notifications');

        $this->assertFalse(
            SecurityMonitor::escalate('t_broken', 1, 1, 'Title', 'Message'),
            'escalate() must report failure by returning false, not by throwing.'
        );
    }

    // ── F2: authorization failures ───────────────────────────────────

    public function test_a_cross_account_request_for_payment_data_is_recorded(): void
    {
        [$attacker, $booking] = $this->makeVictimAndAttacker();

        $this->actingAs($attacker)->get('/pay/'.$booking->id.'/status')->assertForbidden();

        $row = DB::table('staff_logs')->where('action', SecurityMonitor::AUTHZ_FAILED)->first();

        $this->assertNotNull($row, 'A 403 on another guest\'s payment data must be recorded.');
        $this->assertStringContainsString('Attacker', $row->description, 'The actor must be named.');
        $this->assertStringContainsString('payment.status', $row->description, 'The target must be named.');
    }

    public function test_repeated_refusals_notify_the_admins_once(): void
    {
        $admin = $this->makeAdmin();
        [$attacker, $booking] = $this->makeVictimAndAttacker();

        for ($i = 0; $i < 9; $i++) {
            $this->actingAs($attacker)->get('/pay/'.$booking->id.'/status');
        }

        $notifications = DB::table('notifications')->where('user_id', $admin->id)->get();

        $this->assertCount(1, $notifications, 'Nine refusals must produce exactly one alert.');
        $this->assertStringContainsString('permission failures', $notifications->first()->title);

        $this->assertLessThanOrEqual(2,
            DB::table('staff_logs')->where('action', 'like', SecurityMonitor::AUTHZ_FAILED.'%')->count(),
            'Audit rows must stay bounded: the first occurrence plus the escalation.');
    }

    public function test_successful_and_unauthenticated_requests_record_nothing(): void
    {
        // The control, and a strong one: the SAME endpoint that produced the 403
        // above, requested by the guest who actually owns the booking. If this
        // recorded anything, the recorder would be firing on ordinary traffic —
        // and noise is how a real signal gets filtered out.
        [, $booking] = $this->makeVictimAndAttacker();
        $owner = User::find($booking->user_id);

        $this->actingAs($owner)->get('/pay/'.$booking->id.'/status')->assertOk();

        // And two other shapes that are refusals but not authorization failures:
        // a signed-out visitor is redirected to login (302), and a bad URL is a
        // 404. Neither is someone being told "no, not yours".
        $this->get('/my/bookings')->assertRedirect();
        $this->get('/no-such-page-exists')->assertNotFound();

        $this->assertSame(0, DB::table('staff_logs')->count(),
            'Normal traffic must not be recorded as a security event.');
    }

    public function test_a_specific_event_suppresses_the_generic_403_recorder(): void
    {
        // Before this, a wrong CRON_SECRET produced TWO records for one incident
        // — the route's own, plus the generic one from the 403 coming back out
        // through the middleware. Two rows, two warnings, two thresholds.
        config(['app.cron_secret' => 'the-real-secret']);

        $this->withHeaders(['X-Cron-Secret' => 'wrong'])->get('/cron/run-schedule')->assertForbidden();

        $this->assertSame(1, DB::table('staff_logs')->where('action', SecurityMonitor::CRON_SECRET_REJECTED)->count());
        $this->assertSame(0, DB::table('staff_logs')->where('action', SecurityMonitor::AUTHZ_FAILED)->count(),
            'The specific recorder must win; the generic one must stand down.');
    }

    // ── F3: rate limiting ────────────────────────────────────────────

    public function test_a_tripped_limiter_is_recorded_and_names_the_limiter(): void
    {
        // The contact limiter is 5/hour and returns a redirect-with-flash, not a
        // 429 — which is why status codes alone could never reveal this.
        for ($i = 0; $i < 9; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
                ->post('/contact', ['name' => 'P', 'email' => 'p@example.test', 'message' => 'hello there']);
        }

        $row = DB::table('staff_logs')->where('action', SecurityMonitor::RATE_LIMITED)->first();

        $this->assertNotNull($row, 'A tripped limiter must be recorded.');
        $this->assertStringContainsString("'contact'", $row->description,
            'Knowing WHICH protection was hit is most of the value.');
    }

    public function test_requests_under_the_limit_are_not_recorded(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.51'])
            ->post('/contact', ['name' => 'P', 'email' => 'p@example.test', 'message' => 'hello there']);

        $this->assertSame(0, DB::table('staff_logs')->where('action', SecurityMonitor::RATE_LIMITED)->count());
    }

    // ── F4: rejected webhooks and cron secrets ───────────────────────

    public function test_a_forged_webhook_signature_is_recorded_and_still_answers_200(): void
    {
        // The 200 is not incidental. PayMongo auto-disables a webhook that
        // repeatedly answers 4xx/5xx, it does not recover, and nothing in the app
        // can detect the disabled state — so monitoring must not change the
        // status code.
        $this->call('POST', '/webhooks/paymongo',
            server: ['HTTP_PAYMONGO_SIGNATURE' => 't=1,te=deadbeef', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['data' => ['id' => 'evt_forged', 'attributes' => ['type' => 'payment.paid', 'livemode' => false]]]),
        )->assertOk();

        $this->assertSame(1, DB::table('staff_logs')->where('action', SecurityMonitor::WEBHOOK_REJECTED)->count());
    }

    public function test_repeated_forged_webhooks_alert_because_payments_may_be_going_unrecorded(): void
    {
        $admin = $this->makeAdmin();

        for ($i = 0; $i < 4; $i++) {
            $this->call('POST', '/webhooks/paymongo',
                server: ['HTTP_PAYMONGO_SIGNATURE' => 't=1,te=bad'.$i, 'CONTENT_TYPE' => 'application/json'],
                content: json_encode(['data' => ['id' => 'evt_'.$i, 'attributes' => ['type' => 'payment.paid', 'livemode' => false]]]),
            )->assertOk();
        }

        $notification = DB::table('notifications')->where('user_id', $admin->id)->first();

        $this->assertNotNull($notification, 'Three rejected deliveries in an hour must alert.');
        $this->assertStringContainsString('may not be being recorded', $notification->title,
            'The alert has to say what is at stake, not just that a signature failed.');
    }

    public function test_a_wrong_cron_secret_is_recorded_and_still_refused(): void
    {
        config(['app.cron_secret' => 'the-real-secret']);

        $this->withHeaders(['X-Cron-Secret' => 'guess'])->get('/cron/run-schedule')->assertForbidden();

        $row = DB::table('staff_logs')->where('action', SecurityMonitor::CRON_SECRET_REJECTED)->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('X-Cron-Secret header', $row->description);
    }

    // ── F5: credential spray ─────────────────────────────────────────

    public function test_one_source_trying_many_accounts_is_detected(): void
    {
        // The gap no existing guard saw: lockout is per email+IP and per email,
        // and the route limit is 30/min per IP. One guess against many addresses
        // trips neither — each account has a single failure, and the IP stays
        // well under the per-minute ceiling.
        $admin = $this->makeAdmin();

        foreach (range(1, 6) as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->post('/login', ['email' => "target{$n}@example.test", 'password' => 'Summer2026!']);
        }

        $notification = DB::table('notifications')
            ->where('user_id', $admin->id)
            ->where('title', 'like', '%many different accounts%')
            ->first();

        $this->assertNotNull($notification, 'A spray across distinct accounts must alert.');
        $this->assertStringContainsString('5 DIFFERENT accounts', $notification->message);

        // The per-account audit rows from Task 8 are untouched — one per attempt.
        $this->assertSame(6, DB::table('staff_logs')->where('action', 'login_failed')->count());
    }

    public function test_repeated_attempts_on_one_account_do_not_trigger_the_spray_alert(): void
    {
        // Because that is a different fact, already covered by the per-account
        // lockout, and conflating the two would make the spray alert meaningless.
        $admin = $this->makeAdmin();

        foreach (range(1, 6) as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.21'])
                ->post('/login', ['email' => 'one-target@example.test', 'password' => "guess{$n}"]);
        }

        $this->assertSame(0, DB::table('notifications')
            ->where('user_id', $admin->id)
            ->where('title', 'like', '%many different accounts%')
            ->count());
    }

    // ── F1: backup and the destructive-command guard ─────────────────

    public function test_the_guard_refuses_a_destructive_command_on_a_remote_database(): void
    {
        $refusal = DestructiveCommandGuard::refusalFor('migrate:fresh', 'aiven', 'mysql-x.aivencloud.com', false);

        $this->assertNotNull($refusal);
        $this->assertStringContainsString('db:backup --database=aiven', $refusal,
            'The refusal must tell the operator what to do instead.');
    }

    public function test_the_guard_allows_local_and_non_destructive_commands(): void
    {
        // Three controls. A guard that refuses everything is not a guard.
        $this->assertNull(DestructiveCommandGuard::refusalFor('migrate:fresh', 'mysql', '127.0.0.1', false),
            'Local development must be unaffected.');
        $this->assertNull(DestructiveCommandGuard::refusalFor('migrate', 'aiven', 'mysql-x.aivencloud.com', false),
            'A forward migration is not destructive.');
        $this->assertNull(DestructiveCommandGuard::refusalFor('migrate:fresh', 'aiven', 'mysql-x.aivencloud.com', true),
            'An explicit opt-in must be honoured.');
    }

    public function test_every_schema_destroying_command_is_covered(): void
    {
        // migrate:fresh is the one CLAUDE.md documents, but it is not the only
        // one that loses data.
        foreach (['migrate:fresh', 'migrate:reset', 'migrate:rollback', 'db:wipe'] as $command) {
            $this->assertNotNull(
                DestructiveCommandGuard::refusalFor($command, 'aiven', 'remote.example.com', false),
                "{$command} destroys or rolls back schema and must be guarded."
            );
        }
    }

    public function test_backup_refuses_a_connection_it_cannot_dump(): void
    {
        // The test database is SQLite; mysqldump cannot dump it, and saying so is
        // better than writing a file that is not a backup.
        $this->artisan('db:backup', ['--database' => 'sqlite'])
            ->expectsOutputToContain('only dumps MySQL')
            ->assertExitCode(1);
    }

    public function test_backup_refuses_a_connection_that_does_not_exist(): void
    {
        $this->artisan('db:backup', ['--database' => 'no-such-connection'])
            ->expectsOutputToContain('no \'no-such-connection\' database connection')
            ->assertExitCode(1);
    }

    // ── F6: serious application errors ───────────────────────────────

    public function test_the_production_log_level_is_pinned(): void
    {
        // Unset, config/logging.php's own default of `debug` applied to
        // production. It costs nothing today — there are no Log::debug() calls —
        // but it means the first person to add one, or a DB::listen() while
        // chasing a bug, ships query text with bound values to Render.
        $yaml = file_get_contents(base_path('render.yaml'));

        $this->assertMatchesRegularExpression('/key:\s*LOG_LEVEL\s*\n\s*value:\s*info/', $yaml,
            'LOG_LEVEL must be pinned to info in production.');
    }

    public function test_the_app_has_no_debug_level_logging_to_be_hidden_by_that(): void
    {
        // The claim the level rests on. If someone adds Log::debug(), this fails
        // and they have to decide deliberately whether production should see it.
        $found = [];

        foreach ((new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()))) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            if (preg_match('/Log::debug\(|->debug\(/', (string) file_get_contents($file->getPathname()))) {
                $found[] = $file->getFilename();
            }
        }

        $this->assertSame([], $found,
            'Debug-level logging was added. LOG_LEVEL=info means production will not show it — '
            .'either raise the call to info, or change the level deliberately.');
    }

    public function test_an_unhandled_exception_is_recorded_and_escalates(): void
    {
        $admin = $this->makeAdmin();

        \Illuminate\Support\Facades\Route::get('/__boom', fn () => throw new \RuntimeException('internal detail: secret=abc123'));

        for ($i = 0; $i < 4; $i++) {
            try {
                $this->get('/__boom');
            } catch (\Throwable) {
                // The test harness re-throws; the report callback has already run.
            }
        }

        $this->assertGreaterThan(0,
            DB::table('staff_logs')->where('action', SecurityMonitor::APP_ERROR)->count(),
            'An unhandled exception must be recorded.');

        $notification = DB::table('notifications')->where('user_id', $admin->id)->first();
        $this->assertNotNull($notification, 'Three errors in an hour must alert.');
        $this->assertStringContainsString('throwing errors', $notification->title);
    }

    public function test_the_error_alert_does_not_carry_the_exception_message(): void
    {
        // An exception message can hold SQL with bound values (QueryException) or
        // a gateway's raw response body. This string is rendered to admins in the
        // notification bell — the same rule as v7.38 F6/F7.
        $admin = $this->makeAdmin();

        \Illuminate\Support\Facades\Route::get('/__leak',
            fn () => throw new \RuntimeException('SQLSTATE[42S02]: password=hunter2 for guest@example.test'));

        for ($i = 0; $i < 4; $i++) {
            try {
                $this->get('/__leak');
            } catch (\Throwable) {
            }
        }

        $rows = DB::table('staff_logs')->where('action', 'like', SecurityMonitor::APP_ERROR.'%')->get();
        $notifications = DB::table('notifications')->where('user_id', $admin->id)->get();

        $all = $rows->pluck('description')->concat($notifications->pluck('message'))->implode(' ');

        foreach (['hunter2', 'SQLSTATE', 'guest@example.test'] as $secret) {
            $this->assertStringNotContainsString($secret, $all,
                "The exception message leaked into an admin-visible record: {$secret}");
        }

        // Control: it must still say WHICH exception and where, or the alert is useless.
        $this->assertStringContainsString('RuntimeException', $all);
    }

    public function test_ordinary_http_refusals_are_not_reported_as_application_errors(): void
    {
        // 403/404 are HttpExceptions the framework never reports, and they have
        // their own recorder. Counting them here would drown the real faults.
        [$attacker, $booking] = $this->makeVictimAndAttacker();

        $this->actingAs($attacker)->get('/pay/'.$booking->id.'/status')->assertForbidden();
        $this->get('/no-such-page')->assertNotFound();

        $this->assertSame(0, DB::table('staff_logs')->where('action', 'like', SecurityMonitor::APP_ERROR.'%')->count());
    }

    // ── F7: the health endpoint ──────────────────────────────────────

    public function test_the_health_endpoint_answers_without_authentication(): void
    {
        // A monitor cannot hold a session, and /up must not be behind maintenance
        // mode either — otherwise it cannot tell "down" from "switched off".
        $this->get('/up')->assertOk();
    }

    public function test_the_health_endpoint_fails_when_the_database_is_unreachable(): void
    {
        // The whole reason it touches the database. The app being up while Aiven
        // is not is the outage this deployment is most likely to have, and a
        // liveness-only check would report it as healthy.
        config(['database.connections.'.config('database.default').'.database' => '/nonexistent/path.sqlite']);
        DB::purge(config('database.default'));

        $this->get('/up')->assertStatus(500);
    }

    public function test_the_health_endpoint_leaks_nothing_when_debug_is_off(): void
    {
        // The framework renders the exception into the health page only when
        // APP_DEBUG is on. Production has it off; this pins that.
        config(['app.debug' => false]);
        config(['database.connections.'.config('database.default').'.database' => '/nonexistent/path.sqlite']);
        DB::purge(config('database.default'));

        $body = $this->get('/up')->assertStatus(500)->getContent();

        foreach (['SQLSTATE', 'nonexistent', 'vendor', 'Exception'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "The health page leaked: {$leak}");
        }
    }

    // ── F8: staff_logs retention ─────────────────────────────────────

    public function test_routine_rows_past_the_short_window_are_pruned(): void
    {
        $this->makeLog('user_login', 200);
        $this->makeLog('user_login', 10);

        $this->artisan('staff-logs:prune')->assertExitCode(0);

        $this->assertSame(1, StaffLog::count());
        $this->assertSame(10, (int) now()->diffInDays(StaffLog::first()->created_at, true),
            'The row that survived must be the recent one.');
    }

    public function test_security_rows_are_kept_far_longer_than_routine_ones(): void
    {
        // The two-tier policy, proved at the same age: 200 days is past the
        // routine window and well inside the security one.
        $this->makeLog('user_login', 200);          // routine
        $this->makeLog('login_lockout', 200);       // security
        $this->makeLog('credential_spray', 200);    // security, added in v7.41

        $this->artisan('staff-logs:prune')->assertExitCode(0);

        $surviving = StaffLog::pluck('action')->sort()->values()->all();
        $this->assertSame(['credential_spray', 'login_lockout'], $surviving);
    }

    public function test_security_rows_past_the_long_window_are_eventually_pruned(): void
    {
        // Otherwise the "two tiers" are really one tier plus "forever".
        $this->makeLog('login_lockout', 400);

        $this->artisan('staff-logs:prune')->assertExitCode(0);

        $this->assertSame(0, StaffLog::count());
    }

    public function test_exempt_actions_are_never_pruned_however_old(): void
    {
        // THE LOAD-BEARING ONE. auto_checkin_skipped_balance is not history, it is
        // the dedup key the scheduler reads. Prune it and bookings:auto-checkinout
        // — which runs everyMinute() — re-notifies the admins about that booking
        // every single minute until staff resolve it.
        //
        // No local row is old enough to exercise this, so it is only ever tested
        // here.
        $this->makeLog('auto_checkin_skipped_balance', 5000);
        $this->makeLog('user_login', 5000);

        $this->artisan('staff-logs:prune')->assertExitCode(0);

        $this->assertSame(['auto_checkin_skipped_balance'], StaffLog::pluck('action')->all());
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $this->makeLog('user_login', 200);

        $this->artisan('staff-logs:prune', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $this->assertSame(1, StaffLog::count());
    }

    public function test_pruning_refuses_to_run_with_an_empty_exempt_list(): void
    {
        // An empty list is a mistake, not a policy, and the consequence is the
        // scheduler spamming notifications.
        config(['audit.never_prune' => []]);
        $this->makeLog('auto_checkin_skipped_balance', 5000);

        $this->artisan('staff-logs:prune')
            ->expectsOutputToContain('empty never_prune list')
            ->assertExitCode(1);

        $this->assertSame(1, StaffLog::count(), 'Nothing may be deleted when the guard refuses.');
    }

    public function test_chunking_deletes_everything_eligible(): void
    {
        // Chunked deletes page by primary key, not offset — paging with an offset
        // while deleting shifts the window and skips rows.
        foreach (range(1, 12) as $i) {
            $this->makeLog('user_login', 200);
        }

        $this->artisan('staff-logs:prune', ['--chunk' => 5])->assertExitCode(0);

        $this->assertSame(0, StaffLog::count(), 'A chunk smaller than the backlog must still clear it.');
    }

    public function test_retaining_forever_is_possible(): void
    {
        config(['audit.retention.routine_days' => 0, 'audit.retention.security_days' => 0]);
        $this->makeLog('user_login', 5000);

        $this->artisan('staff-logs:prune')->assertExitCode(0);

        $this->assertSame(1, StaffLog::count());
    }

    public function test_the_new_monitoring_actions_are_in_the_security_tier(): void
    {
        // Otherwise the record of a credential spray would be the FIRST thing to
        // expire, at 90 days rather than 365 — exactly backwards.
        foreach ([
            SecurityMonitor::AUTHZ_FAILED,
            SecurityMonitor::RATE_LIMITED,
            SecurityMonitor::WEBHOOK_REJECTED,
            SecurityMonitor::CRON_SECRET_REJECTED,
            SecurityMonitor::LOGIN_FAILED_BURST,
            SecurityMonitor::CREDENTIAL_SPRAY,
            SecurityMonitor::APP_ERROR,
        ] as $action) {
            $this->assertContains($action, StaffLog::SECURITY_ACTIONS,
                "{$action} is not in the security tier, so it would be pruned early.");
            $this->assertContains($action.'_escalated', StaffLog::SECURITY_ACTIONS,
                "{$action}_escalated is not in the security tier.");
        }
    }

    // ── helpers ──────────────────────────────────────────────────────

    /** An audit row backdated by $daysAgo. */
    private function makeLog(string $action, int $daysAgo): StaffLog
    {
        $log = StaffLog::create([
            'user_id' => null,
            'action' => $action,
            'description' => 'fixture',
        ]);

        // created_at is set by timestamps, so it has to be pushed back after.
        StaffLog::where('id', $log->id)->update(['created_at' => now()->subDays($daysAgo)]);

        return $log->refresh();
    }

    private function makeAdmin(): User
    {
        return User::create(['full_name' => 'Boss', 'email' => 'boss@example.test',
            'password' => bcrypt('x'), 'role' => 'admin', 'status' => 1, 'email_verified_at' => now()]);
    }

    /** @return array{0: User, 1: Booking} */
    private function makeVictimAndAttacker(): array
    {
        $victim = User::create(['full_name' => 'Victim', 'email' => 'victim@example.test',
            'password' => bcrypt('x'), 'role' => 'customer', 'status' => 1, 'email_verified_at' => now()]);
        $attacker = User::create(['full_name' => 'Attacker', 'email' => 'attacker@example.test',
            'password' => bcrypt('x'), 'role' => 'customer', 'status' => 1, 'email_verified_at' => now()]);

        $booking = Booking::create(['booking_ref' => 'VE-MON0001', 'user_id' => $victim->id,
            'property_id' => 1, 'check_in_date' => '2026-10-01', 'check_out_date' => '2026-10-01',
            'num_nights' => 1, 'num_guests' => 2, 'base_amount' => 4000, 'total_amount' => 4000,
            'amount_paid' => 0, 'balance_due' => 4000, 'status' => 'confirmed',
            'payment_status' => 'unpaid', 'source' => 'online']);

        return [$attacker, $booking];
    }

    /**
     * The migrations are MySQL-only and do not run on the SQLite test database,
     * so tests build what they need — the convention set by
     * RateLimitingTest::makeUsersTable().
     */
    private function makeTables(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('customer');
            $table->tinyInteger('status')->default(1);
            $table->string('phone')->nullable();
            $table->string('profile_image')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('bookings', function ($table) {
            $table->id();
            $table->string('booking_ref')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->date('check_in_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('check_out_time')->nullable();
            $table->integer('num_nights')->default(1);
            $table->integer('num_guests')->default(1);
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('extras_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('discount_id')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('source')->default('online');
            $table->text('special_requests')->nullable();
            $table->string('slot_hold')->nullable();
            $table->string('paymongo_session_id')->nullable();
            $table->string('paymongo_payment_type')->nullable();
            $table->timestamp('overpayment_notified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('staff_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('target_table')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type')->default('in_app');
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('queued');
            $table->timestamps();
        });

        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->string('data_type')->default('string');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }
}
