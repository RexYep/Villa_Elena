<?php

namespace App\Support;

/**
 * Decides whether a schema-destroying artisan command may run (Task 12 F1).
 *
 * The decision lives here rather than inside the event listener in
 * AppServiceProvider so that it can be tested. The listener has to be skipped
 * under tests — the suite builds its schema by hand and must not be second-
 * guessed by a guard — and a guard that only exists in the environment where it
 * cannot be exercised is a guard nobody has ever seen work.
 */
final class DestructiveCommandGuard
{
    /** Commands that drop or roll back schema. */
    public const DESTRUCTIVE = [
        'migrate:fresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    /**
     * Hosts that are this developer's own machine. Anything else is treated as
     * remote — so a production-ish connection added later is protected by
     * default rather than by somebody remembering to list it.
     */
    public const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1', 'host.docker.internal', ''];

    /**
     * The reason to refuse, or null to allow.
     *
     * Pure: every input is a parameter, so the test does not have to stand up a
     * console command or a remote database to exercise it.
     */
    public static function refusalFor(
        string $command,
        string $connection,
        ?string $host,
        bool $overrideAllowed,
    ): ?string {
        if (! in_array($command, self::DESTRUCTIVE, true)) {
            return null;
        }

        if (in_array((string) $host, self::LOCAL_HOSTS, true)) {
            return null;
        }

        if ($overrideAllowed) {
            return null;
        }

        return "Refusing to run `{$command}` against the '{$connection}' connection: it is not a "
            ."local database. This command destroys the schema, and every booking and payment in it.\n\n"
            ."    Take a backup first:  php artisan db:backup --database={$connection}\n"
            ."    Then, for this one command only, set ALLOW_DESTRUCTIVE_MIGRATIONS=true\n\n"
            .'If you meant to target local, drop the --database option (see project.md §15.9).';
    }
}
