<?php

return [

    /*
    |--------------------------------------------------------------------------
    | mysqldump
    |--------------------------------------------------------------------------
    |
    | Absolute path to the mysqldump binary. It is NOT on PATH on this machine —
    | XAMPP keeps it at C:\xampp\mysql\bin\mysqldump.exe — so `db:backup` looks
    | in the places below rather than assuming the shell can find it.
    |
    | VERSION SKEW IS A REAL HAZARD HERE. The XAMPP client is MySQL 8.0.x and
    | Aiven runs 8.4.x; dumping a newer server with an older client can fail
    | outright on syntax the client does not know. `db:backup` prints both
    | versions before it starts, so a mismatch is visible rather than mysterious.
    |
    */

    'mysqldump_path' => env('MYSQLDUMP_PATH'),

    /*
    | ORDER MATTERS, and not for the reason it looks like.
    |
    | XAMPP's `mysqldump.exe` is MariaDB 10.4.32 — a different product line, not
    | an older MySQL. It was first in this list and it was picked, and it failed
    | with `unknown variable 'set-gtid-purged=OFF'` because MariaDB has no such
    | option. Had a MySQL-only flag not happened to break it, it would have
    | produced a dump of a MySQL 8 server using a MariaDB 10.4 client — which is
    | not a restore path anyone should trust (utf8mb4_0900_ai_ci alone, MySQL 8's
    | default collation, means nothing to MariaDB 10.4).
    |
    | So the real MySQL clients come first, and db:backup refuses a cross-product
    | dump unless it is explicitly overridden.
    */
    'mysqldump_candidates' => [
        'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        '/opt/homebrew/bin/mysqldump',
        // Last resort: MariaDB's, which can dump the local MariaDB if that is
        // ever what is running, and is refused for a MySQL server.
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Where backups are written
    |--------------------------------------------------------------------------
    |
    | Default is storage/backups, which is gitignored via storage/ and is a
    | bind-mounted volume under Docker.
    |
    | THIS MUST NOT BE THE ONLY COPY. Render's filesystem is ephemeral — a
    | backup written inside the container is gone at the next restart, which is
    | why `db:backup` is a developer-machine command run against the `aiven`
    | connection rather than something the container does for itself. Copy the
    | file somewhere off this machine; a backup on the same disk as nothing is
    | still only one disk.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('backups')),

    /*
    | How many backups to keep when --prune is passed. The database measured
    | 1.63 MB across 26 tables, so a gzipped dump is a few hundred kilobytes and
    | keeping a month of them costs nothing.
    */
    'keep' => (int) env('BACKUP_KEEP', 30),

];
