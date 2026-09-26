<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded account passwords
    |--------------------------------------------------------------------------
    |
    | Passwords for the three accounts AdminSeeder creates. They live here
    | rather than in the seeder because the seeder is committed and this reads
    | from the environment, so no credential ends up in git.
    |
    | THERE ARE DELIBERATELY NO DEFAULTS. A default would be a published
    | password on every deployment that did not override it, which is precisely
    | the bug this replaced — `AdminSeeder` used to carry `Admin@1234` as a
    | literal. AdminSeeder::seedAccount() throws a RuntimeException naming the
    | missing variable rather than inventing a value.
    |
    | They are only consulted when an account is CREATED. An existing account's
    | password is never overwritten, so changing one of these values does not
    | rotate a live password — do that through the app's own password form.
    |
    | This indirection is also what makes the guard work inside the container:
    | docker/start.sh runs `php artisan config:cache`, and Laravel then skips
    | loading .env altogether, so env() called from a seeder would return null.
    | Config files are read before that cache is written, so they still see the
    | real environment.
    |
    */

    'passwords' => [
        'admin' => env('SEED_ADMIN_PASSWORD'),
        'staff' => env('SEED_STAFF_PASSWORD'),
        'customer' => env('SEED_CUSTOMER_PASSWORD'),
    ],

];
