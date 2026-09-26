<?php

namespace Tests\Feature;

use App\Models\LoginActivity;
use App\Models\StaffLog;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task 8 — audit logging and accountability. Covers F1, F2, F3, F4 and F12.
 *
 * Tables are built by hand: the real migrations are MySQL-only and do not run
 * on the in-memory SQLite test connection.
 *
 * ONE LIMIT OF THIS SUITE IS WORTH STATING UP FRONT. The F1 bug was SQLite-
 * invisible: SQLite does not enforce VARCHAR length, so the oversized
 * User-Agent that made MySQL throw SQLSTATE[22001] would have inserted here
 * quite happily and every assertion below would have passed against the
 * BROKEN code too. So F1 is tested from the other side — by the truncation
 * itself, which happens in PHP and is therefore visible on any driver, and by
 * forcing a genuine write failure. The original defect and its fix were both
 * measured against real MySQL; see the notes in StaffLog::record().
 */
class AuditLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->makeTables();
    }

    // ══════════════════════════════════════════════════════════════
    // F1 — a request header must not be able to switch the log off
    // ══════════════════════════════════════════════════════════════

    public function test_an_oversized_user_agent_is_truncated_instead_of_refused(): void
    {
        $admin = $this->makeUser(role: 'admin');

        $this->actingAs($admin)
            ->withHeaders(['User-Agent' => str_repeat('A', 400)])
            ->post('/logout');

        $entry = StaffLog::where('action', 'user_logout')->first();

        $this->assertNotNull($entry, 'A 400-character User-Agent must not cost us the audit row.');
        $this->assertSame(255, mb_strlen($entry->user_agent), 'It should be cut to the column width, not stored whole.');
    }

    public function test_every_caller_supplied_string_is_cut_to_its_column_width(): void
    {
        $this->withHeaders([
            'User-Agent' => str_repeat('u', 900),
        ])->app['request']->server->set('REMOTE_ADDR', str_repeat('9', 90));

        StaffLog::record(
            str_repeat('a', 300),          // action       -> 100
            str_repeat('t', 300),          // target_table -> 50
            7,
            'probe'
        );

        $entry = StaffLog::first();

        $this->assertSame(100, mb_strlen($entry->action));
        $this->assertSame(50, mb_strlen($entry->target_table));
        $this->assertLessThanOrEqual(45, mb_strlen((string) $entry->ip_address));
        $this->assertLessThanOrEqual(255, mb_strlen((string) $entry->user_agent));
    }

    public function test_truncation_never_splits_a_multibyte_character(): void
    {
        // 300 three-byte characters. A byte-wise substr() would slice one in
        // half and hand MySQL an invalid utf8mb4 sequence.
        StaffLog::record(str_repeat('あ', 300), 'users', 1, 'multibyte probe');

        $stored = StaffLog::first()->action;

        $this->assertSame(100, mb_strlen($stored));
        $this->assertSame($stored, mb_convert_encoding($stored, 'UTF-8', 'UTF-8'), 'Truncation must leave valid UTF-8.');
    }

    /**
     * The backstop. Truncation removes the cause we found; this covers the
     * ones we have not. A business action must never be destroyed by the
     * logging of it.
     */
    public function test_a_failed_audit_write_is_shouted_about_but_never_thrown(): void
    {
        Log::spy();

        // The bluntest possible write failure.
        Schema::drop('staff_logs');

        StaffLog::record('deleted_user', 'users', 42, 'Deleted user: Someone');

        Log::shouldHaveReceived('critical')
            ->withArgs(fn ($message, $context = []) => str_contains($message, 'AUDIT WRITE FAILED')
                && ($context['action'] ?? null) === 'deleted_user')
            ->once();
    }

    public function test_a_destructive_action_still_completes_when_the_log_cannot_be_written(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $victim = $this->makeUser();

        Log::spy();
        Schema::drop('staff_logs');

        $this->actingAs($admin)->delete("/admin/users/{$victim->id}")->assertRedirect();

        $this->assertNull(User::find($victim->id), 'The delete itself must not be held hostage by the logging of it.');
    }

    // ══════════════════════════════════════════════════════════════
    // F3 — failed logins
    // ══════════════════════════════════════════════════════════════

    public function test_a_wrong_password_is_recorded(): void
    {
        $user = $this->makeUser(email: 'known@example.test');

        $this->post('/login', ['email' => 'known@example.test', 'password' => 'not-the-password']);

        $entry = StaffLog::where('action', 'login_failed')->first();

        $this->assertNotNull($entry, 'A failed login used to leave no trace anywhere.');
        $this->assertSame($user->id, $entry->target_id, 'The attempt should point at the account it targeted.');
        $this->assertNull($entry->user_id, 'Nobody is authenticated during a failed login.');
        $this->assertStringContainsString('wrong password', $entry->description);
        $this->assertStringContainsString('known@example.test', $entry->description);
    }

    public function test_an_unknown_address_is_recorded_and_distinguishable(): void
    {
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever-123']);

        $entry = StaffLog::where('action', 'login_failed')->first();

        $this->assertNotNull($entry);
        $this->assertNull($entry->target_id, 'There is no account to point at.');
        $this->assertStringContainsString('no account with that address', $entry->description);
    }

    /**
     * The log must record the difference between spraying addresses and
     * grinding one account — but the BROWSER must still not be able to tell
     * them apart. That separation is the whole point of recording it here.
     */
    public function test_recording_the_reason_does_not_leak_it_to_the_browser(): void
    {
        $this->makeUser(email: 'known@example.test');

        $known = $this->post('/login', ['email' => 'known@example.test', 'password' => 'wrong-one-here']);
        $unknown = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-one-here']);

        $this->assertSame(
            $known->getSession()->get('errors')?->first('email'),
            $unknown->getSession()->get('errors')?->first('email'),
            'Both answers must stay byte-identical.'
        );
    }

    public function test_a_lockout_is_recorded_exactly_once_when_it_trips(): void
    {
        $this->makeUser(email: 'target@example.test');

        // Five failures trips the per-email+IP wall; the sixth is turned away
        // at the top of login() and must add nothing at all.
        for ($i = 0; $i < 7; $i++) {
            $this->post('/login', ['email' => 'target@example.test', 'password' => "guess-number-{$i}"]);
        }

        $this->assertSame(1, StaffLog::where('action', 'login_lockout')->count(),
            'One row at the moment the wall goes up — not one per blocked request.');
        $this->assertSame(5, StaffLog::where('action', 'login_failed')->count(),
            'Only the attempts that actually consumed a guess should be recorded.');
    }

    public function test_a_correct_password_on_a_deactivated_account_is_its_own_event(): void
    {
        $user = $this->makeUser(email: 'disabled@example.test', status: 0);

        $this->post('/login', ['email' => 'disabled@example.test', 'password' => 'the-real-password']);

        $entry = StaffLog::where('action', 'login_rejected_inactive')->first();

        $this->assertNotNull($entry, 'Working credentials for a disabled account is not the same event as a guess.');
        $this->assertSame($user->id, $entry->target_id);
        $this->assertSame(0, StaffLog::where('action', 'login_failed')->count(),
            'It must not be filed as a failed guess — the password was right.');
    }

    /**
     * F11 changed this contract deliberately. A successful login used to
     * write BOTH a `user_login` staff_logs row and a login_activities row —
     * same event, same second, same IP, with the staff_logs copy carrying
     * strictly less. It is now recorded once, in the richer table.
     */
    public function test_a_successful_login_is_recorded_once_not_twice(): void
    {
        $user = $this->makeUser(email: 'fine@example.test');

        $this->post('/login', ['email' => 'fine@example.test', 'password' => 'the-real-password']);

        $activity = LoginActivity::where('user_id', $user->id)->get();

        $this->assertCount(1, $activity, 'The sign-in itself must still be recorded.');
        $this->assertNotNull($activity->first()->device_label, 'Including what staff_logs never carried.');

        $this->assertSame(0, StaffLog::where('action', 'user_login')->count(),
            'The duplicate, poorer copy should be gone.');
        $this->assertSame(0, StaffLog::where('action', 'login_failed')->count());
    }

    public function test_logout_is_still_recorded_because_nothing_duplicates_it(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/logout');

        // login_activities has no concept of a logout, so this row is the
        // only record of it and stays exactly where it is.
        $this->assertSame(1, StaffLog::where('action', 'user_logout')->count());
    }

    public function test_two_factor_failures_and_the_code_being_burnt_are_recorded(): void
    {
        $user = $this->makeUser(email: '2fa@example.test');
        $user->update(['two_factor_enabled' => true]);

        $this->post('/login', ['email' => '2fa@example.test', 'password' => 'the-real-password'])
            ->assertRedirect(route('two-factor.verify'));

        // Five wrong codes: the first four are plain failures, the fifth
        // burns the code.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor/verify', ['code' => '00000'.$i]);
        }

        $this->assertSame(4, StaffLog::where('action', 'two_factor_failed')->count());
        $this->assertSame(1, StaffLog::where('action', 'two_factor_exhausted')->count());
        $this->assertSame(
            $user->id,
            StaffLog::where('action', 'two_factor_exhausted')->first()->target_id
        );
    }

    // ══════════════════════════════════════════════════════════════
    // F4 — credential and security-setting changes
    // ══════════════════════════════════════════════════════════════

    public function test_a_guest_changing_their_own_password_is_recorded(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put('/my/profile/password', [
            'current_password' => 'the-real-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $entry = StaffLog::where('action', 'password_changed')->first();

        $this->assertNotNull($entry);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($user->id, $entry->target_id);
        $this->assertStringNotContainsString('a-brand-new-password', (string) $entry->description,
            'The audit row must never carry the credential itself.');
    }

    public function test_an_admin_changing_their_own_password_is_recorded(): void
    {
        $admin = $this->makeUser(role: 'admin');

        $this->actingAs($admin)->put('/admin/profile/password', [
            'current_password' => 'the-real-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $this->assertSame(1, StaffLog::where('action', 'password_changed')->where('user_id', $admin->id)->count());
    }

    public function test_a_wrong_current_password_records_nothing(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put('/my/profile/password', [
            'current_password' => 'not-the-current-one',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $this->assertSame(0, StaffLog::where('action', 'password_changed')->count(),
            'Nothing changed, so nothing should be claimed to have changed.');
    }

    public function test_turning_two_factor_off_is_recorded_as_its_own_event(): void
    {
        $user = $this->makeUser();
        $user->update(['two_factor_enabled' => true]);

        $this->actingAs($user)->put('/my/profile/2fa', ['password' => 'the-real-password']);

        $this->assertFalse((bool) $user->fresh()->two_factor_enabled);
        $this->assertSame(1, StaffLog::where('action', 'two_factor_disabled')->count(),
            'Downgrading an authentication control must be visible on its own.');
    }

    public function test_turning_two_factor_on_is_recorded_too(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put('/my/profile/2fa', []);

        $this->assertTrue((bool) $user->fresh()->two_factor_enabled);
        $this->assertSame(1, StaffLog::where('action', 'two_factor_enabled')->count());
    }

    public function test_removing_a_trusted_device_records_what_was_removed(): void
    {
        $user = $this->makeUser();
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => TrustedDevice::hashToken('some-token'),
            'device_label' => 'Chrome on Windows',
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->delete("/my/profile/devices/{$device->id}");

        $entry = StaffLog::where('action', 'trusted_device_removed')->first();

        $this->assertNotNull($entry);
        $this->assertSame($device->id, $entry->target_id, 'The id has to be read before the delete, not after.');
        $this->assertStringContainsString('Chrome on Windows', $entry->description);
    }

    public function test_self_deactivation_is_recorded_while_the_actor_is_still_known(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->delete('/my/profile', ['password' => 'the-real-password']);

        $entry = StaffLog::where('action', 'account_self_deactivated')->first();

        $this->assertNotNull($entry);
        // Recorded one line before Auth::logout(); afterwards this is null.
        $this->assertSame($user->id, $entry->user_id, 'The actor must be captured before the logout wipes it.');
    }

    public function test_completing_a_password_reset_is_recorded(): void
    {
        $user = $this->makeUser(email: 'resetme@example.test');
        $token = app('auth.password.broker')->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'resetme@example.test',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $entry = StaffLog::where('action', 'password_reset_completed')->first();

        $this->assertNotNull($entry);
        $this->assertSame($user->id, $entry->target_id);
        $this->assertNull($entry->user_id, 'Nobody is signed in during a reset — the IP is the only identity there is.');
    }

    // ══════════════════════════════════════════════════════════════
    // F2 — the log can actually be read
    // ══════════════════════════════════════════════════════════════

    public function test_an_admin_can_read_the_audit_log(): void
    {
        $admin = $this->makeUser(role: 'admin');
        StaffLog::record('created_booking', 'bookings', 42, 'Created booking VE-TESTREF');

        $this->actingAs($admin)->get('/admin/audit-log')
            ->assertOk()
            ->assertSee('VE-TESTREF')
            ->assertSee('bookings#42');
    }

    public function test_only_admins_can_read_it(): void
    {
        $this->get('/admin/audit-log')->assertRedirect('/login');

        $this->actingAs($this->makeUser(role: 'customer'))->get('/admin/audit-log')->assertForbidden();
        $this->actingAs($this->makeUser(role: 'staff'))->get('/admin/audit-log')->assertForbidden();
    }

    /**
     * A log the panel can edit carries the authority of a record without the
     * properties of one.
     */
    public function test_the_audit_log_exposes_no_write_route(): void
    {
        $writeRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit')
                && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']));

        $this->assertCount(0, $writeRoutes, 'The audit log must stay read-only.');
    }

    public function test_the_filters_actually_narrow_the_result(): void
    {
        $admin = $this->makeUser(role: 'admin');

        $this->actingAs($admin);
        StaffLog::record('created_booking', 'bookings', 1, 'ALPHA entry');
        StaffLog::record('refund_issued', 'payments', 2, 'BRAVO entry');

        $this->get('/admin/audit-log?action=refund_issued')
            ->assertOk()->assertSee('BRAVO entry')->assertDontSee('ALPHA entry');

        $this->get('/admin/audit-log?target_table=bookings&target_id=1')
            ->assertOk()->assertSee('ALPHA entry')->assertDontSee('BRAVO entry');

        $this->get('/admin/audit-log?q=BRAVO')
            ->assertOk()->assertSee('BRAVO entry')->assertDontSee('ALPHA entry');
    }

    public function test_the_security_filter_shows_security_events_and_hides_routine_ones(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $this->actingAs($admin);

        StaffLog::record('created_booking', 'bookings', 1, 'ROUTINE entry');
        StaffLog::record('two_factor_disabled', 'users', 2, 'SECURITY entry');

        $this->get('/admin/audit-log?security=1')
            ->assertOk()->assertSee('SECURITY entry')->assertDontSee('ROUTINE entry');
    }

    /**
     * A NULL user_id is not one thing, and rendering a failed login as
     * "System" would credit the resort's own scheduler with an outsider's
     * password guess.
     */
    public function test_a_null_actor_is_labelled_honestly(): void
    {
        $scheduler = StaffLog::create(['action' => 'auto_check_in', 'target_table' => 'bookings', 'target_id' => 1]);
        $stranger = StaffLog::create(['action' => 'login_failed', 'target_table' => 'users', 'target_id' => null]);

        $this->assertSame('System', $scheduler->actor_label);
        $this->assertSame('Not signed in', $stranger->actor_label);
    }

    public function test_the_actor_filter_can_isolate_unauthenticated_events(): void
    {
        $admin = $this->makeUser(role: 'admin');

        $this->actingAs($admin);
        StaffLog::record('created_booking', 'bookings', 1, 'BY THE ADMIN');

        // Written with nobody signed in.
        auth()->logout();
        StaffLog::record('login_failed', 'users', null, 'BY A STRANGER');

        // `0` is the sentinel for NULL. This is the exact case that rendered
        // a healthy 200 with zero rows while the strict comparison was wrong.
        $this->actingAs($admin)->get('/admin/audit-log?user_id=0')
            ->assertOk()->assertSee('BY A STRANGER')->assertDontSee('BY THE ADMIN');
    }

    public function test_only_the_fields_that_changed_are_shown_as_a_diff(): void
    {
        $log = StaffLog::create([
            'action' => 'updated_property',
            'old_values' => ['property_name' => 'Old Name', 'base_price' => 4000, 'updated_at' => 'a'],
            'new_values' => ['property_name' => 'New Name', 'base_price' => 4000, 'updated_at' => 'b'],
        ]);

        $changes = $log->changedValues();

        $this->assertSame(['property_name' => ['Old Name', 'New Name']], $changes,
            'An unchanged field is noise, and updated_at moves on every write.');
    }

    // ══════════════════════════════════════════════════════════════
    // F12 — the indexes the viewer depends on
    // ══════════════════════════════════════════════════════════════

    public function test_the_migration_creates_every_index_the_viewer_filters_on(): void
    {
        $migration = require base_path('database/migrations/2026_09_25_100000_add_audit_indexes_to_staff_logs_table.php');
        $migration->up();

        $indexes = collect(DB::select('PRAGMA index_list(staff_logs)'))->pluck('name');

        foreach ([
            'staff_logs_created_at_index',
            'staff_logs_action_created_index',
            'staff_logs_target_index',
            'staff_logs_user_created_index',
        ] as $expected) {
            $this->assertTrue($indexes->contains($expected), "Missing index: {$expected}");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // F5 — what actually changed
    // ══════════════════════════════════════════════════════════════

    public function test_a_role_change_is_distinguishable_from_a_phone_edit(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $victim = $this->makeUser(role: 'customer');

        // Everything else is submitted UNCHANGED, so `role` is the only field
        // that can appear in the diff. Without this the phone number moves
        // from null to a value and the test stops proving what it claims.
        $victim->update(['phone' => '09170000000']);

        $this->actingAs($admin)->put("/admin/users/{$victim->id}", [
            'full_name' => $victim->full_name,
            'email' => $victim->email,
            'phone' => '09170000000',
            'role' => 'admin',
            'address' => null,
        ]);

        $entry = StaffLog::where('action', 'updated_user')->first();

        $this->assertNotNull($entry);
        $this->assertSame(['role' => 'customer'], $entry->old_values);
        $this->assertSame(['role' => 'admin'], $entry->new_values);
        $this->assertStringContainsString('changed: role', $entry->description);
    }

    public function test_an_admin_resetting_someone_elses_password_is_recorded_without_the_password(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $victim = $this->makeUser();

        $this->actingAs($admin)->put("/admin/users/{$victim->id}", [
            'full_name' => $victim->full_name,
            'email' => $victim->email,
            'phone' => '09170000000',
            'role' => $victim->role,
            'address' => null,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $entry = StaffLog::where('action', 'updated_user')->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('password', $entry->description,
            'That the credential was taken over has to be recorded.');

        // ...but never the credential itself, in any form.
        $payload = json_encode([$entry->old_values, $entry->new_values]);
        $this->assertStringNotContainsString('a-brand-new-password', $payload);
        $this->assertStringNotContainsString('$2y$', $payload, 'Not the hash either.');
        $this->assertSame('[redacted]', $entry->old_values['password']);
    }

    public function test_an_edit_that_changes_nothing_writes_no_row(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $victim = $this->makeUser();
        $victim->update(['phone' => '09170000000']);

        $this->actingAs($admin)->put("/admin/users/{$victim->id}", [
            'full_name' => $victim->full_name,
            'email' => $victim->email,
            'phone' => '09170000000',
            'role' => $victim->role,
            'address' => $victim->address,
        ]);

        $this->assertSame(0, StaffLog::where('action', 'updated_user')->count(),
            'A row claiming a change where there was none makes the real ones less believable.');
    }

    public function test_a_form_string_is_not_reported_as_a_change_to_an_int(): void
    {
        // The form posts "4000"; the model holds 4000. A strict comparison
        // would report an edit on every single save.
        StaffLog::recordChange('probe', 'properties', 1, 'probe',
            ['base_price' => 4000, 'name' => 'Villa'],
            ['base_price' => '4000', 'name' => 'Villa'],
        );

        $this->assertSame(0, StaffLog::where('action', 'probe')->count());
    }

    public function test_settings_changes_name_the_settings_that_moved(): void
    {
        $admin = $this->makeUser(role: 'admin');

        \App\Models\Setting::create(['setting_key' => 'deposit_percentage', 'setting_value' => '50']);

        StaffLog::recordChange('updated_settings', 'settings', null, 'Resort settings updated',
            ['deposit_percentage' => '50', 'resort_name' => 'Villa Elena'],
            ['deposit_percentage' => '10', 'resort_name' => 'Villa Elena'],
        );

        $entry = StaffLog::where('action', 'updated_settings')->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('changed: deposit_percentage', $entry->description);
        $this->assertSame(['deposit_percentage' => '50'], $entry->old_values);
        $this->assertSame(['deposit_percentage' => '10'], $entry->new_values);
        $this->assertArrayNotHasKey('resort_name', $entry->new_values, 'Unchanged settings are noise.');
    }

    // ══════════════════════════════════════════════════════════════
    // F6 — deletes must say WHICH record
    // ══════════════════════════════════════════════════════════════

    public function test_deleting_a_user_records_the_id_not_just_a_name(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $victim = $this->makeUser();
        $victimId = $victim->id;

        $this->actingAs($admin)->delete("/admin/users/{$victimId}");

        $entry = StaffLog::where('action', 'deleted_user')->first();

        $this->assertNotNull($entry);
        $this->assertSame($victimId, $entry->target_id,
            'A display name is not unique, not stable, and not a key.');
    }

    // ══════════════════════════════════════════════════════════════
    // F10 — the payment log pointed at the wrong table
    // ══════════════════════════════════════════════════════════════

    public function test_all_three_manual_payment_sites_agree_on_one_action_name(): void
    {
        $sources = [
            'app/Http/Controllers/Admin/BookingController.php',
            'app/Http/Controllers/Admin/PaymentController.php',
            'app/Http/Controllers/Staff/FrontDeskController.php',
        ];

        foreach ($sources as $file) {
            $code = file_get_contents(base_path($file));

            $this->assertStringNotContainsString("StaffLog::record('recorded_payment'", $code,
                "{$file} still uses the odd-one-out action name.");
            $this->assertStringNotContainsString("StaffLog::record('payment_recorded', 'bookings'", $code,
                "{$file} still files a payment against the bookings table.");
        }
    }

    public function test_a_payment_audit_row_points_at_the_payment_that_was_created(): void
    {
        // Two payments on ONE booking: if the row pointed at the booking (as
        // it used to) the two entries would be indistinguishable.
        $first = StaffLog::record('payment_recorded', 'payments', 101, 'Recorded ₱1000 for VE-AAA');
        $second = StaffLog::record('payment_recorded', 'payments', 102, 'Recorded ₱2000 for VE-AAA');

        $targets = StaffLog::where('action', 'payment_recorded')->pluck('target_id')->all();

        $this->assertSame([101, 102], $targets);
        $this->assertSame(['payments', 'payments'],
            StaffLog::where('action', 'payment_recorded')->pluck('target_table')->all());
    }

    // ══════════════════════════════════════════════════════════════
    // F7 — availability blocks
    // ══════════════════════════════════════════════════════════════

    public function test_blocking_dates_from_the_calendar_is_recorded(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $property = $this->makeProperty();

        $this->actingAs($admin)->post('/admin/calendar/block', [
            'property_id' => $property->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'reason' => 'maintenance',
            'notes' => 'repainting',
        ])->assertOk();

        $entry = StaffLog::where('action', 'created_availability_block')->first();

        $this->assertNotNull($entry);
        $this->assertSame('availability_blocks', $entry->target_table);
        $this->assertStringContainsString('2026-12-01', $entry->description);
    }

    /**
     * The important half. Creating a block at least left `created_by` on the
     * row; deleting one destroyed that row and recorded nothing anywhere.
     */
    public function test_unblocking_dates_is_recorded_after_the_row_is_gone(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $property = $this->makeProperty();

        $block = \App\Models\AvailabilityBlock::create([
            'property_id' => $property->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'reason' => 'owner_use',
            'created_by' => $admin->id,
        ]);
        $blockId = $block->id;

        $this->actingAs($admin)->delete("/admin/calendar/blocks/{$blockId}")->assertOk();

        $entry = StaffLog::where('action', 'deleted_availability_block')->first();

        $this->assertNotNull($entry, 'Re-opening closed dates for sale must leave a trace.');
        $this->assertSame($blockId, $entry->target_id);
        $this->assertStringContainsString('owner_use', $entry->description,
            'The reason dies with the row, so it has to be read before the delete.');
        $this->assertStringContainsString('2026-12-01', $entry->description);
    }

    public function test_blocking_dates_from_the_property_page_is_recorded_too(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $property = $this->makeProperty();

        $this->actingAs($admin)->post("/admin/properties/{$property->id}/block-dates", [
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'reason' => 'private_event',
        ]);

        $this->assertSame(1, StaffLog::where('action', 'created_availability_block')->count(),
            'Both block-creating paths go through the same action name.');
    }

    // ══════════════════════════════════════════════════════════════
    // F11 — the sign-in reading surface
    // ══════════════════════════════════════════════════════════════

    public function test_the_sign_in_history_page_lists_logins_and_is_admin_only(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $guest = $this->makeUser(email: 'seen@example.test');

        LoginActivity::create([
            'user_id' => $guest->id,
            'ip_address' => '198.51.100.22',
            'device_label' => 'Firefox on Android',
            'via_new_device_otp' => true,
        ]);

        $this->actingAs($admin)->get('/admin/audit-log/sign-ins')
            ->assertOk()
            ->assertSee('Firefox on Android')
            ->assertSee('198.51.100.22');

        $this->actingAs($this->makeUser(role: 'customer'))->get('/admin/audit-log/sign-ins')->assertForbidden();

        auth()->logout();
        $this->get('/admin/audit-log/sign-ins')->assertRedirect('/login');
    }

    public function test_the_sign_in_filters_narrow_correctly(): void
    {
        $admin = $this->makeUser(role: 'admin');
        $guest = $this->makeUser();

        LoginActivity::create(['user_id' => $admin->id, 'device_label' => 'ADMINDEVICE', 'via_new_device_otp' => false]);
        LoginActivity::create(['user_id' => $guest->id, 'device_label' => 'GUESTDEVICE', 'via_new_device_otp' => true]);

        $this->actingAs($admin);

        $this->get('/admin/audit-log/sign-ins?staff=1')
            ->assertOk()->assertSee('ADMINDEVICE')->assertDontSee('GUESTDEVICE');

        $this->get('/admin/audit-log/sign-ins?otp=1')
            ->assertOk()->assertSee('GUESTDEVICE')->assertDontSee('ADMINDEVICE');

        $this->get('/admin/audit-log/sign-ins?user_id='.$guest->id)
            ->assertOk()->assertSee('GUESTDEVICE')->assertDontSee('ADMINDEVICE');
    }

    /**
     * Removing the write must not remove the history. Past `user_login` rows
     * stay in the table and stay visible — an audit log that drops old
     * entries when a convention changes is not an audit log.
     */
    public function test_historical_user_login_rows_are_still_readable(): void
    {
        $admin = $this->makeUser(role: 'admin');

        StaffLog::create([
            'user_id' => $admin->id,
            'action' => 'user_login',
            'target_table' => 'users',
            'target_id' => $admin->id,
            'description' => 'HISTORICAL LOGIN ROW',
        ]);

        $this->actingAs($admin)->get('/admin/audit-log?action=user_login')
            ->assertOk()->assertSee('HISTORICAL LOGIN ROW');
    }

    // ══════════════════════════════════════════════════════════════
    // F8 — property images
    // ══════════════════════════════════════════════════════════════

    public function test_deleting_a_property_image_is_recorded(): void
    {
        Storage::fake('public');

        $admin = $this->makeUser(role: 'admin');
        $property = $this->makeProperty();
        $image = \App\Models\PropertyImage::create([
            'property_id' => $property->id,
            'image_path' => 'properties/some-photo.jpg',
            'is_primary' => false,
        ]);
        $imageId = $image->id;

        $this->actingAs($admin)->delete("/admin/properties/images/{$imageId}");

        $entry = StaffLog::where('action', 'deleted_property_image')->first();

        $this->assertNotNull($entry, 'This destroys a stored file and a row; on production the file is unrecoverable.');
        $this->assertSame('property_images', $entry->target_table);
        $this->assertSame($imageId, $entry->target_id);
        $this->assertStringContainsString('properties/some-photo.jpg', $entry->description);
    }

    public function test_deleting_the_primary_image_records_which_one_replaced_it(): void
    {
        Storage::fake('public');

        $admin = $this->makeUser(role: 'admin');
        $property = $this->makeProperty();

        $primary = \App\Models\PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'properties/a.jpg', 'is_primary' => true,
        ]);
        $second = \App\Models\PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'properties/b.jpg', 'is_primary' => false,
        ]);

        $this->actingAs($admin)->delete("/admin/properties/images/{$primary->id}");

        $entry = StaffLog::where('action', 'deleted_property_image')->first();

        $this->assertStringContainsString('PRIMARY', $entry->description);
        // The promotion is a SIDE EFFECT — unlike the deleted row's own
        // attributes, it cannot be reconstructed after the fact.
        $this->assertStringContainsString("image #{$second->id} promoted", $entry->description);
        $this->assertTrue((bool) $second->fresh()->is_primary);
    }

    public function test_deleting_the_only_image_says_the_property_has_none_left(): void
    {
        Storage::fake('public');

        $admin = $this->makeUser(role: 'admin');
        $property = $this->makeProperty();
        $only = \App\Models\PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'properties/only.jpg', 'is_primary' => true,
        ]);

        $this->actingAs($admin)->delete("/admin/properties/images/{$only->id}");

        $this->assertStringContainsString('no primary image',
            StaffLog::where('action', 'deleted_property_image')->first()->description);
    }

    // ══════════════════════════════════════════════════════════════
    // F9 — no audit write may sit inside a transaction
    // ══════════════════════════════════════════════════════════════

    /**
     * A structural check, because the failure it guards against is
     * structural. The walk-in booking was the only `record()` call placed
     * inside a transaction callback, and the cost of it drifting back is a
     * booking that can be rolled back by its own logging.
     */
    public function test_no_audit_write_sits_inside_a_transaction(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($files as $file) {
            if (! str_ends_with((string) $file, '.php')) {
                continue;
            }

            $lines = explode("\n", file_get_contents((string) $file));
            $open = null;
            $depth = 0;

            foreach ($lines as $n => $line) {
                if (preg_match('/DB::transaction\s*\(|reserveSlot\s*\(/', $line)) {
                    $open = $n + 1;
                    $depth = 0;
                }

                if ($open === null) {
                    continue;
                }

                $depth += substr_count($line, '{') - substr_count($line, '}');

                if (str_contains($line, 'StaffLog::record')) {
                    $offenders[] = basename((string) $file).':'.($n + 1);
                }

                if ($depth <= 0 && $n + 1 > $open + 1) {
                    $open = null;
                }
            }
        }

        $this->assertSame([], $offenders,
            'An audit entry describes something that HAPPENED; inside a transaction it can still be rolled back.');
    }

    /**
     * PINS THE REASON F9 IS ABOUT LOCK SCOPE AND NOT ABOUT ORPHAN ROWS.
     *
     * The intuitive argument for moving the walk-in audit write out of
     * reserveSlot()'s callback is that a rollback would strand a row
     * describing a booking that never existed. That argument is WRONG, and
     * this test exists to stop it being reintroduced as a justification: the
     * audit row is written on the same connection inside the same
     * transaction, so it rolls back with everything else. It never outlives
     * what it describes.
     *
     * The real cost of the old placement is that the callback runs under
     * `Property::whereKey(...)->lockForUpdate()` — the single serialization
     * point every booking path queues on — so an audit INSERT in there makes
     * every concurrent booking wait for it. See FrontDeskController.
     */
    public function test_an_audit_row_written_inside_a_transaction_rolls_back_with_it(): void
    {
        $user = $this->makeUser();

        $bookingsCreated = 0;

        try {
            DB::transaction(function () use ($user, &$bookingsCreated) {
                $booking = \App\Models\Booking::create([
                    'user_id' => $user->id,
                    'status' => 'confirmed',
                ]);
                $bookingsCreated++;

                StaffLog::record('walkin_booking', 'bookings', $booking->id, 'Walk-in booking created');

                // DomainException, not RuntimeException: QueryException
                // extends RuntimeException, so the broader catch silently
                // absorbed a broken fixture and this test passed without
                // ever creating a booking.
                throw new \DomainException('something later in the callback failed');
            });
        } catch (\DomainException $e) {
            // expected
        }

        $this->assertSame(1, $bookingsCreated, 'The transaction must actually have written a booking to roll back.');

        $this->assertSame(0, \App\Models\Booking::count(), 'The booking rolled back, as it should.');
        $this->assertSame(0, StaffLog::where('action', 'walkin_booking')->count(),
            'And so did its audit row — measured, not assumed.');
    }

    public function test_an_audit_row_written_after_the_commit_survives_a_later_failure(): void
    {
        $user = $this->makeUser();

        $booking = DB::transaction(fn () => \App\Models\Booking::create([
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]));

        // Where the walk-in now records: outside the lock, after the commit.
        StaffLog::record('walkin_booking', 'bookings', $booking->id, 'Walk-in booking created');

        try {
            throw new \DomainException('a broadcast failed afterwards');
        } catch (\DomainException $e) {
            // The real call site wraps its broadcasts in try/catch for
            // exactly this reason.
        }

        $this->assertSame(1, \App\Models\Booking::count());
        $this->assertSame(1, StaffLog::where('action', 'walkin_booking')->count());
    }

    // ══════════════════════════════════════════════════════════════
    // Primary-image promotion order
    // ══════════════════════════════════════════════════════════════

    public function test_deleting_the_primary_promotes_the_next_image_in_display_order(): void
    {
        Storage::fake('public');

        $admin = $this->makeUser(role: 'admin');
        $property = $this->makeProperty();

        // Deliberately adversarial: the image that should be promoted has the
        // HIGHEST id, so an unordered first() picks the wrong one. This is
        // the real shape from production, where a deleted upload leaves a gap
        // and later images carry lower sort_order than older ones.
        $primary = \App\Models\PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'p/primary.jpg',
            'is_primary' => true, 'sort_order' => 0,
        ]);
        $laterId = \App\Models\PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'p/shown-last.jpg',
            'is_primary' => false, 'sort_order' => 9,
        ])->id;
        $shownFirst = \App\Models\PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'p/shown-first.jpg',
            'is_primary' => false, 'sort_order' => 1,
        ]);

        $this->assertGreaterThan($laterId, $shownFirst->id, 'The fixture must be adversarial to be worth running.');

        $this->actingAs($admin)->delete("/admin/properties/images/{$primary->id}");

        $this->assertTrue((bool) $shownFirst->fresh()->is_primary,
            'The promoted image must be the one shown first, not the one with the lowest id.');
        $this->assertFalse((bool) \App\Models\PropertyImage::find($laterId)->is_primary);

        $this->assertStringContainsString("image #{$shownFirst->id} promoted",
            StaffLog::where('action', 'deleted_property_image')->first()->description);
    }

    public function test_the_images_relation_returns_them_in_display_order(): void
    {
        $property = $this->makeProperty();

        $third = \App\Models\PropertyImage::create(['property_id' => $property->id, 'image_path' => 'p/c.jpg', 'sort_order' => 5]);
        $first = \App\Models\PropertyImage::create(['property_id' => $property->id, 'image_path' => 'p/a.jpg', 'sort_order' => 1]);
        $second = \App\Models\PropertyImage::create(['property_id' => $property->id, 'image_path' => 'p/b.jpg', 'sort_order' => 3]);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $property->images()->pluck('id')->all(),
            'sort_order was written on every upload and read by nothing.'
        );
    }

    /**
     * DOCUMENTS INTENT; DOES NOT PROVE NECESSITY — said plainly so nobody
     * later reads it as proof.
     *
     * `sort_order` is not unique and has gaps, so without the `id` tiebreak
     * the order of two images sharing one `sort_order` is undefined by
     * specification. It is not, however, undefined in practice on either
     * engine today: removing the tiebreak leaves this test passing on SQLite,
     * and six rows sharing a `sort_order` on real MySQL come back in
     * primary-key order with or without it (measured — the plan is a full
     * scan, since nothing indexes `sort_order`).
     *
     * The tiebreak stays because determinism you can read off the query beats
     * determinism you inherited from a query plan. A future index on
     * `sort_order` is enough to change that plan.
     */
    public function test_images_with_the_same_sort_order_fall_back_to_id(): void
    {
        $property = $this->makeProperty();

        $a = \App\Models\PropertyImage::create(['property_id' => $property->id, 'image_path' => 'p/a.jpg', 'sort_order' => 2]);
        $b = \App\Models\PropertyImage::create(['property_id' => $property->id, 'image_path' => 'p/b.jpg', 'sort_order' => 2]);

        $this->assertSame([$a->id, $b->id], $property->images()->pluck('id')->all());
    }

    // ── Fixtures ───────────────────────────────────────────────────

    private function makeUser(string $role = 'customer', ?string $email = null, int $status = 1): User
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'full_name' => "User {$n}",
            'email' => $email ?? "audituser{$n}@example.test",
            'password' => Hash::make('the-real-password'),
            'role' => $role,
            'status' => $status,
        ]);

        $user->markEmailAsVerified();

        return $user;
    }

    private function makeProperty(): \App\Models\Property
    {
        return \App\Models\Property::create([
            'property_name' => 'Villa Elena (Whole Villa)',
            'type' => 'villa',
            'max_capacity' => 20,
            'base_price' => 4000,
            'weekend_price' => 6000,
        ]);
    }

    private function makeTables(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->tinyInteger('status')->default(1);
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('profile_image')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function ($table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('trusted_devices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token_hash');
            $table->string('device_label')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // NOTE: the string columns here are deliberately left unconstrained,
        // matching SQLite's behaviour rather than MySQL's. SQLite ignores
        // VARCHAR lengths entirely, which is exactly why F1 could not be
        // reproduced on this connection — see the class docblock.
        Schema::create('staff_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('target_table')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('description')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('login_activities', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_label')->nullable();
            $table->boolean('via_new_device_otp')->default(false);
            $table->timestamps();
        });

        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('data_type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('link')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->string('status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // The admin sidebar renders two live KPI badges on every admin page,
        // so reaching the audit view at all needs these two tables — a
        // reminder that a feature test of an admin page is never testing only
        // that page. `deleted_at` is required: Booking uses SoftDeletes, and
        // DashboardStats::pendingBookings() therefore filters on it.
        // Booking has a `creating` hook that generates `booking_ref` and a
        // `saving` hook that maintains `slot_hold`, so a two-column fixture
        // cannot hold one. That shortfall surfaced as a QueryException —
        // which extends RuntimeException, so a `catch (RuntimeException)`
        // in a test swallowed it and the test passed having exercised
        // nothing. Both were fixed together.
        Schema::create('bookings', function ($table) {
            $table->id();
            $table->string('booking_ref')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('source')->nullable();
            $table->string('slot_hold')->nullable();
            $table->integer('num_guests')->nullable();
            $table->text('special_requests')->nullable();
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('properties', function ($table) {
            $table->id();
            $table->string('property_name')->nullable();
            $table->string('type')->default('villa');
            $table->integer('max_capacity')->default(10);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('weekend_price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->text('amenities')->nullable();
            $table->integer('floor_area_sqm')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('status')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('availability_blocks', function ($table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('property_images', function ($table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('image_path')->nullable();
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('issue_reports', function ($table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
}
