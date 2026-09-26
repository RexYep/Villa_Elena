<?php

namespace Tests\Feature;

use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 11 — secrets, database credentials and data protection.
 *
 * F1  seeded passwords were literals in AdminSeeder, and updateOrCreate() put
 *     them in the UPDATE array so a re-run reset a rotated password.
 * F2  render.yaml must warn that the app user is not Aiven's superuser.
 * F3  a blank MYSQL_ATTR_SSL_CA silently connected in cleartext.
 * F4  the `aiven` connection disabled certificate verification.
 */
class SecretsAndDatabaseTest extends TestCase
{
    // ── F1: the seeder ───────────────────────────────────────────────

    public function test_seeder_refuses_to_create_an_account_with_no_password_configured(): void
    {
        $this->makeSeederTables();
        config(['seeding.passwords' => ['admin' => null, 'staff' => null, 'customer' => null]]);

        try {
            $this->runSeeder();
            $this->fail('The seeder created an account with no password configured.');
        } catch (RuntimeException $e) {
            // The message has to name the variable — an operator who hits this
            // is mid-deploy and should not have to read the seeder to proceed.
            $this->assertStringContainsString('SEED_ADMIN_PASSWORD', $e->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeder_creates_accounts_with_the_configured_password(): void
    {
        $this->makeSeederTables();
        $this->configureSeedPasswords();

        $this->runSeeder();

        foreach ([
            'admin@villaelenareosrt.com' => 'configured-admin-pw',
            'staff@villaelenareosrt.com' => 'configured-staff-pw',
            'guest@example.com' => 'configured-customer-pw',
        ] as $email => $password) {
            $hash = \DB::table('users')->where('email', $email)->value('password');
            $this->assertNotNull($hash, "{$email} was not created.");
            $this->assertTrue(Hash::check($password, $hash), "{$email} did not get the configured password.");
        }
    }

    /**
     * The regression that matters. Before v7.39 the password sat in
     * updateOrCreate()'s update array, so `db:seed` silently undid a rotation
     * — the admin would change their password, the next seed would put it
     * back, and nothing in the output said so.
     */
    public function test_reseeding_does_not_reset_a_rotated_password(): void
    {
        $this->makeSeederTables();
        $this->configureSeedPasswords();
        $this->runSeeder();

        // The admin rotates their password by hand, as the app's own form does.
        \DB::table('users')->where('email', 'admin@villaelenareosrt.com')
            ->update(['password' => Hash::make('rotated-by-a-human')]);

        // Someone re-seeds — and the configured value is different again, so a
        // pass here cannot be an accident of the two values matching.
        config(['seeding.passwords.admin' => 'a-third-different-password']);
        $this->runSeeder();

        $hash = \DB::table('users')->where('email', 'admin@villaelenareosrt.com')->value('password');

        $this->assertTrue(Hash::check('rotated-by-a-human', $hash),
            'Re-seeding overwrote a password that had been rotated by hand.');
        $this->assertFalse(Hash::check('a-third-different-password', $hash),
            'Re-seeding applied the configured password to an existing account.');
        $this->assertFalse(Hash::check('configured-admin-pw', $hash),
            'Re-seeding reverted the password to the originally seeded value.');
    }

    /**
     * ...while still being idempotent, which is what updateOrCreate() was for.
     * Dropping that would have been a different regression.
     */
    public function test_reseeding_still_refreshes_profile_fields(): void
    {
        $this->makeSeederTables();
        $this->configureSeedPasswords();
        $this->runSeeder();

        \DB::table('users')->where('email', 'staff@villaelenareosrt.com')
            ->update(['full_name' => 'Wrong Name', 'status' => 0]);

        $this->runSeeder();

        $row = \DB::table('users')->where('email', 'staff@villaelenareosrt.com')->first();
        $this->assertSame('Front Desk Staff', $row->full_name);
        $this->assertSame(1, (int) $row->status);
        $this->assertSame(3, \DB::table('users')->count(), 'Re-seeding duplicated accounts.');
    }

    public function test_no_password_literal_is_left_in_the_seeder(): void
    {
        $source = file_get_contents(base_path('database/seeders/AdminSeeder.php'));

        // The three values that were published, plus the shape in general: a
        // Hash::make() of anything other than a variable is a literal password.
        foreach (['Admin@1234', 'Staff@1234', 'Guest@1234'] as $leaked) {
            $this->assertStringNotContainsString($leaked, $source,
                "AdminSeeder still contains the published password {$leaked}.");
        }

        $this->assertDoesNotMatchRegularExpression(
            "/Hash::make\(\s*['\"]/",
            $source,
            'AdminSeeder hashes a string literal — passwords must come from config.'
        );
    }

    public function test_seed_passwords_have_no_default_in_config(): void
    {
        $source = file_get_contents(config_path('seeding.php'));

        // A default here would be a published credential on every deployment
        // that did not override it — the exact bug this replaced.
        $this->assertMatchesRegularExpression("/env\('SEED_ADMIN_PASSWORD'\)/", $source);
        $this->assertDoesNotMatchRegularExpression(
            "/env\('SEED_[A-Z_]+_PASSWORD',/",
            $source,
            'A SEED_*_PASSWORD has a fallback value; it must have none.'
        );
    }

    // ── F3: production cannot boot without database TLS ──────────────

    public function test_production_refuses_to_build_a_config_without_a_tls_ca(): void
    {
        // Measured: PDO MySQL does NO opportunistic TLS, and array_filter()
        // drops a blank value, so without this guard a blank variable moved
        // every query onto the public internet in cleartext, silently.
        foreach (['unset' => null, 'blank' => ''] as $label => $ca) {
            $thrown = $this->loadDatabaseConfig('production', $ca);

            $this->assertInstanceOf(RuntimeException::class, $thrown,
                "A production config was built with a {$label} MYSQL_ATTR_SSL_CA.");
            $this->assertStringContainsString('cleartext', $thrown->getMessage());
        }
    }

    public function test_production_refuses_a_tls_ca_path_that_does_not_exist(): void
    {
        // The other half: MYSQL_ATTR_SSL_CA is set but AIVEN_CA_CERT was blank,
        // so docker/start.sh never wrote the file.
        $thrown = $this->loadDatabaseConfig('production', '/no/such/aiven-ca.pem');

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('AIVEN_CA_CERT', $thrown->getMessage());
    }

    public function test_production_with_a_real_ca_file_is_accepted_and_sends_it(): void
    {
        // The control. A guard that rejects everything proves nothing about
        // whether it rejects the right thing.
        $ca = base_path('composer.json'); // any file that certainly exists

        $this->assertNull($this->loadDatabaseConfig('production', $ca),
            'The guard rejected a production config that has a usable CA file.');

        $options = $this->loadDatabaseConfig('production', $ca, returnOptions: 'mysql');
        $this->assertSame($ca, $options[\PDO::MYSQL_ATTR_SSL_CA] ?? null,
            'The CA was accepted but not actually passed to PDO.');
    }

    public function test_the_guard_does_not_fire_outside_production(): void
    {
        // Local dev talks to localhost MySQL over a loopback socket and must
        // not be forced to own a CA file.
        foreach (['local', 'testing'] as $env) {
            $this->assertNull($this->loadDatabaseConfig($env, null),
                "The TLS guard fired in the {$env} environment.");
        }
    }

    // ── F4: the maintenance connection verifies the certificate ──────

    public function test_the_aiven_connection_does_not_disable_certificate_verification(): void
    {
        $options = config('database.connections.aiven.options');

        $this->assertArrayNotHasKey(\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT, $options,
            'The aiven connection disables TLS certificate verification. With that flag '
            .'mysqlnd skips peer verification entirely, so the CA is decorative and the '
            .'connection is encrypted but authenticated against nothing.');

        // And in the source, so the flag cannot come back on a connection this
        // test does not read — the `mariadb` block, say.
        //
        // Comments are STRIPPED first. Asserting on the raw file failed, for the
        // dullest possible reason: the warning comment I added to database.php
        // spells the flag out, so a plain substring search matched my own
        // "never put this back" note and reported the guard as broken. Grepping
        // prose to make a claim about code is the mistake; this tokenises.
        $code = $this->sourceWithoutComments(config_path('database.php'));

        $this->assertStringNotContainsString('MYSQL_ATTR_SSL_VERIFY_SERVER_CERT', $code,
            'config/database.php has live code referencing the verification flag.');

        // The explanation must survive too, in the comments this time.
        $this->assertStringContainsString(
            'NEVER put MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false back here',
            file_get_contents(config_path('database.php'))
        );
    }

    public function test_the_aiven_connection_always_sends_a_ca_even_when_blank(): void
    {
        // Fail closed: an empty path makes PDO throw "Cannot connect to MySQL
        // using SSL", whereas filtering the key out would connect in cleartext.
        $options = $this->loadDatabaseConfig('local', null, returnOptions: 'aiven', aivenCa: '');

        $this->assertArrayHasKey(\PDO::MYSQL_ATTR_SSL_CA, $options,
            'A blank AIVEN_DB_SSL_CA drops the TLS option, which connects in cleartext.');
        $this->assertSame('', $options[\PDO::MYSQL_ATTR_SSL_CA]);
    }

    // ── F2: the superuser warning is written down ────────────────────

    public function test_render_yaml_warns_that_the_app_must_not_use_the_aiven_superuser(): void
    {
        $yaml = file_get_contents(base_path('render.yaml'));

        $this->assertStringContainsString('DB_USERNAME MUST NOT BE `avnadmin`', $yaml);
        $this->assertStringContainsString('§15.8', $yaml,
            'The warning must point at the section holding the CREATE USER / GRANT statements.');

        // And that section has to actually exist, with runnable SQL in it.
        $doc = file_get_contents(base_path('project.md'));
        $this->assertStringContainsString('### 15.8 Database users and privileges', $doc);
        $this->assertStringContainsString("CREATE USER 'villa_app'@'%'", $doc);
    }

    public function test_render_yaml_explains_why_run_migrations_stays_false(): void
    {
        // A scoped user has no DDL, so flipping RUN_MIGRATIONS would fail the
        // deploy. That has to be discoverable at the variable, not only in a doc.
        $yaml = file_get_contents(base_path('render.yaml'));

        $this->assertMatchesRegularExpression(
            '/no CREATE\/ALTER\/DROP.*\n.*RUN_MIGRATIONS|RUN_MIGRATIONS/',
            $yaml
        );
        $this->assertStringContainsString('--database=aiven', $yaml);
    }

    public function test_no_live_credentials_are_left_in_project_md(): void
    {
        $doc = file_get_contents(base_path('project.md'));

        // These were written out as the CURRENT admin/staff/customer passwords.
        foreach (['AdminTest123!', 'StaffTest123!', '`Admin@1234`', '`Staff@1234`', '`Guest@1234`'] as $leaked) {
            $this->assertStringNotContainsString($leaked, $doc,
                "project.md still publishes the credential {$leaked}.");
        }
    }

    // ── F5: dead government-ID schema is inert, and the notice is honest ──

    public function test_government_id_columns_are_not_mass_assignable(): void
    {
        // Nothing in the app writes these, so nothing should be able to write
        // them by accident either. Both entry points: the constructor and fill().
        $viaConstructor = new \App\Models\User(['full_name' => 'A Guest', 'id_type' => 'Passport', 'id_number' => 'P1234567']);
        $viaFill = (new \App\Models\User)->fill(['id_type' => 'Passport', 'id_number' => 'P1234567']);

        foreach (['constructor' => $viaConstructor, 'fill()' => $viaFill] as $how => $user) {
            $this->assertNull($user->id_type, "id_type was mass-assignable via {$how}.");
            $this->assertNull($user->id_number, "id_number was mass-assignable via {$how}.");
        }

        // Control: a field that IS meant to be fillable still is, so this is not
        // passing because mass assignment is broken outright.
        $this->assertSame('A Guest', $viaConstructor->full_name);
    }

    public function test_government_id_columns_never_serialize(): void
    {
        // $hidden makes this structural rather than a property of each query —
        // a future `with('user')` that widens again still cannot leak them.
        $user = new \App\Models\User;
        $user->forceFill(['id_type' => 'Passport', 'id_number' => 'P1234567', 'full_name' => 'A Guest']);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('id_type', $array);
        $this->assertArrayNotHasKey('id_number', $array);
        $this->assertStringNotContainsString('P1234567', $user->toJson());
        $this->assertArrayHasKey('full_name', $array, 'Control: ordinary fields must still serialise.');
    }

    public function test_audit_log_redacts_identifier_values(): void
    {
        // The log should record THAT an identifier changed, never the value —
        // staff_logs is append-only and nothing prunes it.
        foreach (['id_number' => ['P1234567', 'P7654321'], 'account_number' => ['09171234567', '09180000000']] as $field => [$before, $after]) {
            $this->assertContains($field, \App\Models\StaffLog::REDACTED_KEYS,
                "{$field} is not redacted, so its value would be stored verbatim.");

            $diff = $this->auditDiff([$field => $before], [$field => $after]);

            $this->assertArrayHasKey($field, $diff, "The change to {$field} was not recorded at all.");
            $this->assertSame(['[redacted]', '[changed]'], $diff[$field]);
            $this->assertStringNotContainsString($before, json_encode($diff));
            $this->assertStringNotContainsString($after, json_encode($diff));
        }

        // Control: an ordinary field is recorded with its real values, so the
        // redaction above is selective rather than blanket.
        $diff = $this->auditDiff(['full_name' => 'Old Name'], ['full_name' => 'New Name']);
        $this->assertSame(['Old Name', 'New Name'], $diff['full_name']);
    }

    public function test_privacy_policy_does_not_claim_to_collect_a_government_id(): void
    {
        // A privacy notice has to describe what actually happens. Nothing reads
        // or writes id_type/id_number, so the claim was removed.
        // Compiled, not raw. The raw file DOES contain the phrase — inside the
        // `{{-- --}}` note recording that the claim was removed and why. What
        // matters is what reaches the guest, and Blade strips its comments at
        // compile time, so this asserts the guest-visible text. (Third time this
        // session that a grep over source matched my own explanatory comment;
        // asserting on output instead of source is the actual lesson.)
        $compiled = \Illuminate\Support\Facades\Blade::compileString(
            file_get_contents(resource_path('views/portal/legal/privacy.blade.php'))
        );

        $this->assertStringNotContainsString('ID type and ID number', $compiled);
        $this->assertStringContainsString('do not ask for or store a copy of any government ID', $compiled);

        // Control: the comment really is in the raw file, so the assertion above
        // is passing because comments are stripped, not because the note vanished.
        $this->assertStringContainsString('ID type and ID number',
            file_get_contents(resource_path('views/portal/legal/privacy.blade.php')));
    }

    public function test_nothing_in_the_app_reads_or_writes_the_id_columns(): void
    {
        // The premise the three tests above rest on. If someone builds identity
        // capture, this fails and they are sent to the notes explaining what
        // else has to change ($fillable, $hidden, REDACTED_KEYS, the policy).
        $hits = [];

        foreach (['app', 'routes', 'resources/views', 'database/seeders'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));
            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                // Two declarations, not uses: User's $fillable/$hidden notes, and
                // StaffLog::REDACTED_KEYS naming the field it must redact. Both
                // exist precisely BECAUSE nothing else touches it.
                $path = str_replace('\\', '/', $file->getPathname());
                if (str_ends_with($path, 'app/Models/User.php') || str_ends_with($path, 'app/Models/StaffLog.php')) {
                    continue;
                }
                // Comments stripped: a note *about* the column is not a use of
                // it, and this file is full of such notes by design.
                if (preg_match('/\bid_number\b/', $this->sourceWithoutComments($file->getPathname()))) {
                    $hits[] = $path;
                }
            }
        }

        $this->assertSame([], $hits,
            'id_number is now referenced outside the model. If identity capture is being built, '
            .'read the notes on User::$fillable — it needs explicit validation, an encrypted cast, '
            .'a REDACTED_KEYS entry, and a privacy-policy clause saying what is kept and for how long.');
    }

    // ── F6: the diagnostics endpoint returns no infrastructure detail ──

    public function test_cache_diagnostics_returns_no_hostnames(): void
    {
        // The two blocks the finding is about, called directly rather than
        // through measure(): measure() also times the dashboard-stats and
        // settings queries, so it reaches `bookings`, `payments`, `properties`
        // and more. Standing up that much schema to assert the shape of a
        // three-key array would make the test mostly fixture. The route itself
        // was checked with a real request.
        $this->assertSame(['app_env', 'cache_store', 'redis_client'], array_keys($this->diagnosticsEnvironment()),
            'The environment block changed. It is returned over HTTP by /diagnostics/cache — '
            .'add timings and settings, never hostnames or credentials.');

        // measure() must still be the thing that composes it, or these tests
        // would quietly be checking a method nothing calls.
        $source = $this->sourceWithoutComments(base_path('app/Services/CacheDiagnostics.php'));
        $this->assertStringContainsString("'environment' => self::environment()", $source);
        $this->assertStringContainsString("'redis' => self::redisStatus()", $source);
    }

    public function test_cache_diagnostics_reports_a_redis_failure_by_class_not_message(): void
    {
        // Measured: a predis failure message carries "tcp://host:port". This
        // array is returned over HTTP, so only the class may travel.
        config([
            'database.redis.client' => 'predis',
            'database.redis.cache.host' => '127.0.0.1',
            'database.redis.cache.port' => 6399,
            'database.redis.cache.timeout' => 0.3,
            'database.redis.cache.read_write_timeout' => 0.3,
        ]);
        app()->forgetInstance('redis');

        $redis = $this->diagnosticsRedisStatus();

        $this->assertFalse($redis['reachable'], 'Control: port 6399 was expected to be dead.');
        $this->assertSame('ConnectionException', $redis['error']);
        $this->assertStringNotContainsString('6399', json_encode($redis));
        $this->assertStringNotContainsString('tcp://', json_encode($redis));
    }

    public function test_the_diagnostics_route_no_longer_makes_a_stale_promise(): void
    {
        // The old comment asserted a property of code in another file, and was
        // false. It is replaced by a list of what is actually returned.
        $source = file_get_contents(base_path('routes/cron.php'));

        $this->assertStringNotContainsString('Returns\ntimings only', $source);
        $this->assertStringNotContainsString('timings only, never config values or credentials. Safe', $source);
        $this->assertStringContainsString('WHAT THIS RETURNS', $source);
    }

    // ── F7: the guest's words are capped wherever they are logged ────

    public function test_every_chatbot_log_site_caps_the_guest_message(): void
    {
        // A sweep, not one assertion: this site was the outlier precisely
        // because the other two were written correctly and nothing compared them.
        $source = $this->sourceWithoutComments(base_path('app/Http/Controllers/Portal/ChatbotController.php'));

        // Keyed on the VARIABLE, not the array key. Matching on `'message' =>`
        // swept up the request's own validation rule
        // (`'message' => 'required|string|max:500'`) and reported it as an
        // uncapped log site — a false positive that would have had me "fixing"
        // a validation rule. A bare `=> $userMessage` is unambiguously a
        // context value being passed straight through.
        $this->assertDoesNotMatchRegularExpression('/=>\s*\$userMessage\b/', $source,
            "A chatbot log site passes the guest's message through uncapped. "
            .'Wrap it in mb_substr($userMessage, 0, 300) like the others.');

        // And that there are still sites doing it the right way, so this cannot
        // pass by the logging having been deleted.
        $this->assertGreaterThanOrEqual(3, preg_match_all('/mb_substr\(\$userMessage/', $source),
            'Expected at least three capped guest-message log sites.');
    }

    // ── F8: public pages do not load whole user rows ─────────────────

    public function test_public_review_queries_never_eager_load_a_whole_user_row(): void
    {
        // A sweep over the public controller, so the next one somebody writes is
        // caught too. `with('user')` on an unauthenticated page pulls email,
        // phone, address and the government-ID columns into view data.
        $source = $this->sourceWithoutComments(base_path('app/Http/Controllers/Portal/PortalController.php'));

        preg_match_all("/with\(\s*\[?([^)]*?)\]?\s*\)/s", $source, $matches);

        $bare = [];
        foreach ($matches[1] as $args) {
            foreach (explode(',', $args) as $relation) {
                $relation = trim($relation, " \t\n'\"");
                if ($relation === 'user' || str_starts_with($relation, 'user.')) {
                    $bare[] = $relation;
                }
            }
        }

        $this->assertSame([], $bare,
            'PortalController eager-loads the whole users row on a public page. '
            ."Name the columns instead: with('user:id,full_name,profile_image').");

        // And that all three public review queries are still narrowed.
        //
        // This counted occurrences rather than merely checking for one because
        // the inverse tamper caught me out: replacing ONE site's narrowed load
        // with `->without('user')` left the other two matching, so a
        // assertStringContainsString() vacuity guard stayed green. Dropping a
        // relation is not itself a security regression — it exposes less, not
        // more — but a guard that cannot tell three sites from one is not
        // measuring what its name claims.
        $this->assertSame(3, preg_match_all('/user:id,full_name,profile_image/', $source),
            'Expected exactly three narrowed user eager-loads in PortalController '
            .'(home, reviews, propertyDetail). If a public query was added or removed, '
            .'update this count deliberately rather than loosening the check.');
    }

    // ── helpers ──────────────────────────────────────────────────────

    /**
     * StaffLog::diff() is private, and its redaction is the behaviour under
     * test rather than an implementation detail of a controller.
     */
    private function auditDiff(array $before, array $after): array
    {
        return (function () use ($before, $after) {
            return self::diff($before, $after);
        })->call(new \App\Models\StaffLog);
    }

    /**
     * Re-evaluate config/database.php under a chosen environment.
     *
     * Returns the RuntimeException the guard threw, or null when the config
     * built cleanly; with $returnOptions it returns that connection's PDO
     * options instead. env() reads $_SERVER/$_ENV live, so overriding them here
     * is enough — and the finally block puts them back, because a leaked
     * APP_ENV=production would break unrelated tests in the same process.
     */
    private function loadDatabaseConfig(
        ?string $appEnv,
        ?string $mysqlCa,
        ?string $returnOptions = null,
        ?string $aivenCa = null,
    ): RuntimeException|array|null {
        $previous = [];

        $set = function (string $key, ?string $value) use (&$previous) {
            $previous[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null];
            if ($value === null) {
                unset($_SERVER[$key], $_ENV[$key]);
            } else {
                $_SERVER[$key] = $value;
                $_ENV[$key] = $value;
            }
        };

        try {
            $set('APP_ENV', $appEnv);
            $set('MYSQL_ATTR_SSL_CA', $mysqlCa);
            $set('AIVEN_DB_SSL_CA', $aivenCa);

            $config = require config_path('database.php');

            return $returnOptions === null
                ? null
                : $config['connections'][$returnOptions]['options'];
        } catch (RuntimeException $e) {
            return $e;
        } finally {
            foreach ($previous as $key => [$server, $env]) {
                $server === null ? $_SERVER = array_diff_key($_SERVER, [$key => null]) : $_SERVER[$key] = $server;
                $env === null ? $_ENV = array_diff_key($_ENV, [$key => null]) : $_ENV[$key] = $env;
            }
        }
    }

    /**
     * A PHP file's code with every comment removed, so a claim about what the
     * code does cannot be satisfied — or broken — by prose describing it.
     */
    private function sourceWithoutComments(string $path): string
    {
        $kept = array_filter(
            token_get_all(file_get_contents($path)),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
        );

        return implode('', array_map(fn ($t) => is_array($t) ? $t[1] : $t, $kept));
    }

    private function configureSeedPasswords(): void
    {
        config(['seeding.passwords' => [
            'admin' => 'configured-admin-pw',
            'staff' => 'configured-staff-pw',
            'customer' => 'configured-customer-pw',
        ]]);
    }

    /**
     * Through Artisan rather than calling the seeder directly: Seeder::$command
     * is null otherwise and run()'s closing $this->command->info() fatals.
     */
    private function runSeeder(): void
    {
        $this->artisan('db:seed', ['--class' => AdminSeeder::class, '--no-interaction' => true]);
    }

    /**
     * The migrations are MySQL-only and do not run on the SQLite test database,
     * so tests build the tables they need — same convention as
     * RateLimitingTest::makeUsersTable().
     */
    /** CacheDiagnostics::environment(), which is private and static. */
    private function diagnosticsEnvironment(): array
    {
        return (fn () => self::environment())->call(new \App\Services\CacheDiagnostics);
    }

    /** CacheDiagnostics::redisStatus(), same. */
    private function diagnosticsRedisStatus(): array
    {
        return (fn () => self::redisStatus())->call(new \App\Services\CacheDiagnostics);
    }

    private function makeSeederTables(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('customer');
            $table->string('phone')->nullable();
            $table->tinyInteger('status')->default(1);
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
