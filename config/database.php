<?php

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Production database TLS — fail closed, not fail open
|--------------------------------------------------------------------------
|
| MYSQL_ATTR_SSL_CA is the ONLY thing that turns TLS on for the 'mysql'
| connection below. That is measured, not assumed: with no SSL option set,
| PHP's PDO MySQL connects in PLAINTEXT to a server advertising
| have_ssl=YES and tls_version=TLSv1.2,TLSv1.3 (Ssl_cipher came back empty).
| There is no opportunistic TLS to fall back on.
|
| Two more measurements make this a real hazard rather than a theoretical one:
|
|   - `array_filter()` with no callback DROPS an empty or null value, so a
|     blank MYSQL_ATTR_SSL_CA removed the option entirely and left no trace.
|   - The Aiven server does NOT require TLS: `require_secure_transport = OFF`.
|
| Together: blanking one Render environment variable would have moved every
| query — guest names, emails, payment amounts — onto the public internet in
| cleartext, with no error, no log line and no visible symptom.
|
| So production asserts it instead. This throws while the config is being
| built, which in the container means during `php artisan config:cache` in
| docker/start.sh: the deploy fails with this message rather than booting into
| an unencrypted state. The cert is written from $AIVEN_CA_CERT earlier in
| that same script, so by the time this runs the file must exist.
|
| There is deliberately NO opt-out flag. This deployment is Render reaching
| Aiven across the public internet; anything else is a change someone has to
| make on purpose, having read this. Belt and braces is to also switch
| `require_secure_transport` ON in the Aiven console, which closes the same
| hole from the server side no matter what a client sends.
|
*/

$mysqlSslCa = (string) env('MYSQL_ATTR_SSL_CA', '');

if (env('APP_ENV') === 'production') {
    if ($mysqlSslCa === '') {
        throw new RuntimeException(
            'MYSQL_ATTR_SSL_CA is empty, which would connect to the database in '
            .'cleartext instead of over TLS (PDO MySQL does no opportunistic TLS, '
            .'and Aiven does not require encryption). Set it to the CA path — on '
            .'Render that is /etc/ssl/certs/aiven-ca.pem, written from AIVEN_CA_CERT '
            .'by docker/start.sh.'
        );
    }

    if (! is_file($mysqlSslCa)) {
        throw new RuntimeException(
            "MYSQL_ATTR_SSL_CA points at '{$mysqlSslCa}', which does not exist, so the "
            .'database connection cannot be encrypted. On Render this file is written '
            .'from the AIVEN_CA_CERT environment variable by docker/start.sh — check '
            .'that AIVEN_CA_CERT is still set and holds the full PEM.'
        );
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            // $mysqlSslCa, not env() directly: the guard at the top of this file
            // has already refused to build a production config without a usable
            // CA, so there is exactly one place that decides what TLS means here.
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => $mysqlSslCa,
            ]) : [],
        ],

        // One-off local access to the Aiven (production) database, for
        // maintenance commands like `php artisan migrate:fresh --force
        // --database=aiven` — Render's free plan has no Shell tab, so this
        // is how commands get run against it from a developer machine
        // instead. Separate env vars on purpose: never touches the 'mysql'
        // connection above, which local dev keeps pointed at localhost.
        'aiven' => [
            'driver' => 'mysql',
            'host' => env('AIVEN_DB_HOST'),
            'port' => env('AIVEN_DB_PORT', '3306'),
            'database' => env('AIVEN_DB_DATABASE', 'defaultdb'),
            'username' => env('AIVEN_DB_USERNAME', 'avnadmin'),
            'password' => env('AIVEN_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            // NEVER put MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false back here.
            //
            // It was here until v7.39, on the belief — recorded in project.md
            // §15.4 — that "plain MYSQL_ATTR_SSL_CA alone wasn't enough from this
            // machine". That was a misdiagnosis, and the flag was hiding the real
            // fault rather than working around a platform quirk.
            //
            // What was actually wrong: storage/aiven-ca.pem was the CA for a
            // DIFFERENT Aiven project than the server it connects to.
            //
            //     our CA file      CN=b9e1130a-…-Project CA
            //     server cert's issuer  CN=c472a745-…-Project CA
            //
            // openssl reported `verify error:num=19:self-signed certificate in
            // certificate chain` — a chain failure, not a hostname failure. The
            // server cert's SAN does cover mysql-…-villaelena.e.aivencloud.com
            // and *.e.aivencloud.com, so verification succeeds once the CA for
            // the CURRENT project is in place.
            //
            // With the flag set, mysqlnd skipped peer verification entirely, so
            // the CA was decorative: the transport was encrypted (measured:
            // TLSv1.3 / TLS_AES_256_GCM_SHA384) but authenticated against
            // nothing. Anyone able to intercept this connection could present
            // their own certificate and collect the avnadmin password — on the
            // connection documented for `migrate:fresh --seed` against
            // production. Omitting the flag restores PHP's default of verifying.
            //
            // The CA is passed even when the variable is blank, rather than being
            // filtered out. An empty path makes PDO fail with "Cannot connect to
            // MySQL using SSL" (measured); dropping the key would instead connect
            // in cleartext and say nothing. Loud beats silent.
            'options' => extension_loaded('pdo_mysql') ? [
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => (string) env('AIVEN_DB_SSL_CA', ''),
            ] : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            // predis waits 5s on a dead server by default, per command, and
            // the `failover` cache store can only fall back once it gives up.
            // (max_retries/backoff below are phpredis-only; predis ignores them.)
            'timeout' => env('REDIS_TIMEOUT', 0.5),
            'read_write_timeout' => env('REDIS_TIMEOUT', 0.5),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'timeout' => env('REDIS_TIMEOUT', 0.5),
            'read_write_timeout' => env('REDIS_TIMEOUT', 0.5),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
