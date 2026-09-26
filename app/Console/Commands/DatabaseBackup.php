<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Take a verified, compressed logical backup of a MySQL connection.
 *
 * WHY THIS EXISTS (Task 12 F1). There was no backup or restore procedure of any
 * kind: no command, no package, and not one mention of either in project.md or
 * CLAUDE.md. Meanwhile CLAUDE.md documents, as routine maintenance:
 *
 *     php artisan migrate:fresh --seed --force --database=aiven
 *
 * which drops every table in production. One mistyped --database was
 * unrecoverable. (That specific footgun is now also guarded in
 * AppServiceProvider; this command is the other half — recovery, not prevention.)
 *
 * Cost is not the obstacle: the database measured 26 tables, 1.63 MB, ~2,283
 * rows. A gzipped dump is a few hundred kilobytes.
 *
 * FOUR THINGS HERE ARE LOAD-BEARING.
 *
 * 1. THE PASSWORD NEVER APPEARS IN THE COMMAND LINE. `mysqldump -p<secret>` is
 *    readable by any other process on the machine for as long as the dump runs
 *    (`ps`, Task Manager, `wmic process get CommandLine`). It goes into a
 *    defaults-extra-file that is deleted in a finally block instead.
 *
 * 2. THE DUMP IS VERIFIED, NOT JUST WRITTEN. mysqldump can exit non-zero having
 *    already produced a partial file, and a truncated dump looks perfectly
 *    plausible until the day you restore it. So the output must end with
 *    mysqldump's own "Dump completed" trailer and contain at least as many
 *    CREATE TABLE statements as the connection has tables. An unverified backup
 *    is not a backup, it is a file.
 *
 * 3. TLS IS REQUIRED FOR A REMOTE HOST. A dump is the entire database crossing
 *    the network in cleartext otherwise — every guest name, email and payment.
 *    See --allow-unverified-tls for the one deliberate exception.
 *
 * 4. BOTH VERSIONS ARE PRINTED. The client here is MySQL 8.0.x, Aiven runs
 *    8.4.x, and dumping a newer server with an older client can fail on syntax
 *    the client does not know. When it does, the reason should be on screen.
 */
class DatabaseBackup extends Command
{
    protected $signature = 'db:backup
                            {--database= : Connection to dump (default: the app default; use "aiven" for production)}
                            {--path= : Directory to write into (default: config backup.path)}
                            {--prune : Delete the oldest backups, keeping config backup.keep}
                            {--allow-unverified-tls : Encrypt but do not verify the server certificate — see the note in handle()}';

    protected $description = 'Write a verified, gzipped mysqldump of a database connection';

    public function handle(): int
    {
        $name = $this->option('database') ?: config('database.default');
        $config = config("database.connections.{$name}");

        if (! $config) {
            $this->error("There is no '{$name}' database connection.");

            return self::FAILURE;
        }

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("Connection '{$name}' is a {$config['driver']} connection; this command only dumps MySQL.");

            return self::FAILURE;
        }

        $dump = $this->locateMysqldump();

        if (! $dump) {
            $this->error('mysqldump was not found.');
            $this->line('  Set MYSQLDUMP_PATH in .env, or add the path to config/backup.php.');
            $this->line('  On this machine it ships with XAMPP at C:\xampp\mysql\bin\mysqldump.exe');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: config('backup.path');

        if (! is_dir($directory) && ! @mkdir($directory, 0o755, true)) {
            $this->error("Could not create {$directory}");

            return self::FAILURE;
        }

        $target = rtrim($directory, '/\\').DIRECTORY_SEPARATOR
            .'villa-elena-'.$name.'-'.now()->format('Y-m-d_His').'.sql.gz';

        // Count the tables first, so the verification below has something real
        // to compare against rather than just "the file is not empty".
        try {
            $expectedTables = (int) DB::connection($name)
                ->selectOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE()')
                ->c;
            $serverVersion = (string) DB::connection($name)->selectOne('SELECT VERSION() AS v')->v;
        } catch (\Throwable $e) {
            $this->error("Could not reach the '{$name}' database: ".$e::class);
            $this->line('  '.mb_substr($e->getMessage(), 0, 200));

            if ($name === 'aiven') {
                $this->newLine();
                $this->warn('  The `aiven` connection verifies the server certificate since v7.39.');
                $this->line('  If this is a TLS failure, storage/aiven-ca.pem is probably still the CA for');
                $this->line('  a DIFFERENT Aiven project — see project.md §15.4. Download the current one.');
            }

            return self::FAILURE;
        }

        if (! $this->flavoursMatch($dump, $serverVersion)) {
            return self::FAILURE;
        }

        $this->line("  connection : {$name}  (database {$config['database']})");
        $this->line("  server     : {$serverVersion}");
        $this->line('  client     : '.$this->clientInfo($dump)['raw']);
        $this->line("  tables     : {$expectedTables}");
        $this->line("  writing to : {$target}");
        $this->newLine();

        $defaults = $this->writeDefaultsFile($config);

        try {
            $command = $this->buildCommand($dump, $defaults, $config);
            $exit = $this->runDump($command, $target);
        } finally {
            // The credentials file goes, whatever happened.
            @unlink($defaults);
        }

        if ($exit !== 0) {
            @unlink($target);
            $this->error("mysqldump exited with code {$exit}. No backup was kept.");
            $this->line('  A partial dump is worse than none, so the file was deleted.');

            if (version_compare($serverVersion, '8.1', '>=')
                && version_compare($this->clientVersion($dump), '8.1', '<')) {
                $this->newLine();
                $this->warn('  The client is older than the server. That is a known cause of this:');
                $this->line('  install a mysqldump matching MySQL '.$serverVersion.' and set MYSQLDUMP_PATH.');
            }

            return self::FAILURE;
        }

        if (! $this->verify($target, $expectedTables)) {
            return self::FAILURE;
        }

        $this->info('  Backup verified: '.$this->humanSize(filesize($target)));
        $this->line('  '.$target);
        $this->newLine();
        $this->warn('  This is ONE copy, on this machine. Copy it somewhere else —');
        $this->line('  a backup that lives only next to the thing it backs up is not a backup.');

        if ($this->option('prune')) {
            $this->prune($directory, $name);
        }

        return self::SUCCESS;
    }

    /**
     * A defaults-extra-file, so the password is never an argument.
     *
     * mysqldump reads [client]. The file is created with 0600 where the platform
     * honours it; on Windows the ACL is inherited, which is why it is deleted
     * immediately after the dump rather than left in place.
     */
    private function writeDefaultsFile(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vedump');
        @chmod($path, 0o600);

        $lines = [
            '[client]',
            'user="'.$config['username'].'"',
            'password="'.$config['password'].'"',
            'host="'.$config['host'].'"',
            'port='.($config['port'] ?: 3306),
        ];

        // The CA the connection itself uses, so the backup is held to the same
        // standard as the app. array_filter() in config/database.php means the
        // key is simply absent when no CA is configured (local MySQL).
        $ca = $config['options'][\PDO::MYSQL_ATTR_SSL_CA] ?? null;

        if ($ca) {
            $lines[] = 'ssl-ca="'.$ca.'"';
            $lines[] = $this->option('allow-unverified-tls')
                // Encrypted, but the certificate is not checked. The ONLY reason
                // this option exists: being unable to take a backup at all is
                // itself a risk, and "the CA file is wrong" (v7.39 F4) should not
                // stand between an operator and a copy of the data in an
                // emergency. It is not a default and it says so on screen.
                ? 'ssl-mode=REQUIRED'
                : 'ssl-mode=VERIFY_CA';
        }

        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);

        if ($ca && $this->option('allow-unverified-tls')) {
            $this->warn('  --allow-unverified-tls: the transport is encrypted but the server');
            $this->line('  certificate is NOT being checked. Use this for an emergency copy, then');
            $this->line('  fix the CA (project.md §15.4) rather than leaving it in a script.');
        }

        return $path;
    }

    private function buildCommand(string $dump, string $defaults, array $config): string
    {
        return implode(' ', array_filter([
            escapeshellarg($dump),
            '--defaults-extra-file='.escapeshellarg($defaults),
            // Consistent snapshot without locking the whole database: everything
            // here is InnoDB, and the app keeps serving while this runs.
            '--single-transaction',
            '--quick',
            // Aiven has GTID enabled and the default GTID_PURGED preamble makes
            // the dump refuse to load into a different server — which is exactly
            // what restoring into a scratch database is.
            //
            // MySQL-only. MariaDB has no such option and errors out with
            // `unknown variable`, which is how the MariaDB client shipped with
            // XAMPP announced itself the first time this ran.
            $this->clientInfo($dump)['flavour'] === 'mysql' ? '--set-gtid-purged=OFF' : null,
            // Needs the PROCESS privilege otherwise. The scoped `villa_app` user
            // from v7.39 §15.8 does not have it, and neither should it.
            '--no-tablespaces',
            // Events and routines are not used by this schema, but ask for
            // triggers explicitly so a future one is not silently missed.
            '--triggers',
            '--routines',
            '--add-drop-table',
            escapeshellarg($config['database']),
        ]));
    }

    /**
     * Run the dump, gzipping as it streams so the plaintext SQL never lands on
     * disk. stderr is captured rather than shown, because mysqldump writes
     * warnings there that are not failures.
     */
    private function runDump(string $command, string $target): int
    {
        $errorFile = tempnam(sys_get_temp_dir(), 'vedumperr');
        $handle = popen($command.' 2>'.escapeshellarg($errorFile), 'rb');

        if (! $handle) {
            @unlink($errorFile);
            $this->error('Could not start mysqldump.');

            return 1;
        }

        $gz = gzopen($target, 'wb9');

        while (! feof($handle)) {
            $chunk = fread($handle, 1 << 16);

            if ($chunk === false || $chunk === '') {
                continue;
            }

            gzwrite($gz, $chunk);
        }

        gzclose($gz);
        $exit = pclose($handle);

        $stderr = trim((string) @file_get_contents($errorFile));
        @unlink($errorFile);

        if ($stderr !== '') {
            // Shown at every exit code: a warning on a successful dump can still
            // be the thing that explains a bad restore later.
            $this->line('  mysqldump said:');
            foreach (array_slice(explode("\n", $stderr), 0, 8) as $line) {
                $this->line('    '.mb_substr(trim($line), 0, 160));
            }
        }

        return $exit;
    }

    /**
     * Is this actually a restorable dump?
     *
     * mysqldump can fail partway and leave a file that looks fine. The trailer
     * is only written when it finished, and the CREATE TABLE count catches a
     * dump that stopped after two tables of twenty-six.
     */
    private function verify(string $target, int $expectedTables): bool
    {
        $contents = '';
        $gz = gzopen($target, 'rb');

        while ($gz && ! gzeof($gz)) {
            $contents .= gzread($gz, 1 << 18);
        }

        if ($gz) {
            gzclose($gz);
        }

        $creates = preg_match_all('/^CREATE TABLE /mi', $contents);

        if (! str_contains($contents, 'Dump completed')) {
            @unlink($target);
            $this->error('The dump has no "Dump completed" trailer — mysqldump did not finish.');
            $this->line('  The file was deleted; a truncated dump that looks valid is the worst outcome.');

            return false;
        }

        if ($creates < $expectedTables) {
            @unlink($target);
            $this->error("The dump holds {$creates} CREATE TABLE statements but the database has {$expectedTables} tables.");
            $this->line('  The file was deleted.');

            return false;
        }

        $this->line("  verified   : {$creates} tables, trailer present");

        return true;
    }

    private function prune(string $directory, string $name): void
    {
        $keep = max(1, (int) config('backup.keep'));
        $files = glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR."villa-elena-{$name}-*.sql.gz") ?: [];

        sort($files); // filenames are timestamped, so lexical order is chronological
        $stale = array_slice($files, 0, max(0, count($files) - $keep));

        foreach ($stale as $file) {
            @unlink($file);
            $this->line('  pruned     : '.basename($file));
        }

        $this->line('  kept       : '.min(count($files), $keep).' of '.count($files));
    }

    private function locateMysqldump(): ?string
    {
        $configured = config('backup.mysqldump_path');

        if ($configured && is_file($configured)) {
            return $configured;
        }

        foreach ((array) config('backup.mysqldump_candidates') as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Version AND flavour, because they are different questions.
     *
     * XAMPP ships MariaDB's mysqldump, which reports
     * `Ver 10.4.32-MariaDB for Win32`. Treating that as "an old MySQL" is how a
     * MariaDB client ends up dumping a MySQL 8 server.
     *
     * @return array{version: string, flavour: string, raw: string}
     */
    private function clientInfo(string $dump): array
    {
        static $cache = [];

        if (isset($cache[$dump])) {
            return $cache[$dump];
        }

        exec(escapeshellarg($dump).' --version 2>&1', $out);
        $raw = implode(' ', $out);

        return $cache[$dump] = [
            'version' => preg_match('/\b(\d+\.\d+\.\d+)\b/', $raw, $m) ? $m[1] : 'unknown',
            'flavour' => stripos($raw, 'mariadb') !== false ? 'mariadb' : 'mysql',
            'raw' => trim($raw),
        ];
    }

    private function clientVersion(string $dump): string
    {
        return $this->clientInfo($dump)['version'];
    }

    /**
     * Refuse to dump a MySQL server with a MariaDB client, or vice versa.
     *
     * Not pedantry: the two diverged years ago. MySQL 8's default collation
     * (utf8mb4_0900_ai_ci), its JSON functions and its account syntax are all
     * unknown to MariaDB 10.4, and the failure shows up when you restore, which
     * is the worst possible moment to discover it.
     */
    private function flavoursMatch(string $dump, string $serverVersion): bool
    {
        $client = $this->clientInfo($dump)['flavour'];
        $server = stripos($serverVersion, 'mariadb') !== false ? 'mariadb' : 'mysql';

        if ($client === $server) {
            return true;
        }

        $this->error("Client/server mismatch: a {$client} mysqldump cannot be trusted to dump a {$server} server.");
        $this->newLine();
        $this->line('  client : '.$this->clientInfo($dump)['raw']);
        $this->line("  server : {$serverVersion}");
        $this->newLine();
        $this->line('  XAMPP\'s mysqldump.exe is MariaDB, which is why this happens by default.');
        $this->line('  Point MYSQLDUMP_PATH at a matching client, e.g.');
        $this->line('    MYSQLDUMP_PATH="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe"');
        $this->newLine();
        $this->line('  Refusing rather than writing a dump that may not restore.');

        return false;
    }

    private function humanSize(int|false $bytes): string
    {
        $bytes = (int) $bytes;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 2).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
