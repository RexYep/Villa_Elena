<?php
// ============================================================
// FILE: database/seeders/AdminSeeder.php
// ============================================================
namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        $this->seedAccount('admin', 'admin@villaelenareosrt.com', [
            'full_name' => 'Villa Elena Admin',
            'role'      => 'admin',
            'phone'     => '09000000000',
            'status'    => 1,
        ]);

        // Create a sample Staff account
        $this->seedAccount('staff', 'staff@villaelenareosrt.com', [
            'full_name' => 'Front Desk Staff',
            'role'      => 'staff',
            'phone'     => '09111111111',
            'status'    => 1,
        ]);

        // Create a sample Customer account
        $this->seedAccount('customer', 'guest@example.com', [
            'full_name' => 'Sample Guest',
            'role'      => 'customer',
            'phone'     => '09222222222',
            'status'    => 1,
        ]);

        // Seed default system settings
        $settings = [
            ['setting_key' => 'resort_name',        'setting_value' => 'Villa Elena Private Rental Resort', 'data_type' => 'string',  'description' => 'Resort display name'],
            ['setting_key' => 'resort_email',        'setting_value' => 'info@villaelenareosrt.com',         'data_type' => 'string',  'description' => 'Main contact email'],
            ['setting_key' => 'resort_phone',        'setting_value' => '09000000000',                      'data_type' => 'string',  'description' => 'Main contact phone'],
            ['setting_key' => 'resort_address',      'setting_value' => 'Villa Elena, Philippines',         'data_type' => 'string',  'description' => 'Resort address'],
            ['setting_key' => 'booking_hold_minutes','setting_value' => '15',                               'data_type' => 'integer', 'description' => 'Minutes to hold a pending booking'],
            ['setting_key' => 'deposit_percentage',  'setting_value' => '30',                               'data_type' => 'integer', 'description' => 'Deposit % required to confirm booking'],
            ['setting_key' => 'cancellation_hours',  'setting_value' => '48',                               'data_type' => 'integer', 'description' => 'Hours before check-in for free cancellation'],
            ['setting_key' => 'booking_cooldown_threshold',   'setting_value' => '3',                       'data_type' => 'integer', 'description' => 'Auto-cancelled (unpaid) bookings within the window that trigger a booking cooldown'],
            ['setting_key' => 'booking_cooldown_window_days', 'setting_value' => '30',                      'data_type' => 'integer', 'description' => 'Lookback window (days) for counting auto-cancelled bookings'],
            ['setting_key' => 'booking_cooldown_hours',       'setting_value' => '24',                      'data_type' => 'integer', 'description' => 'Hours a guest is blocked from booking again after hitting the cooldown threshold'],
            ['setting_key' => 'currency',            'setting_value' => 'PHP',                              'data_type' => 'string',  'description' => 'Currency code'],
            ['setting_key' => 'currency_symbol',     'setting_value' => '₱',                               'data_type' => 'string',  'description' => 'Currency symbol'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                $setting
            );
        }

        $this->command->info('✅ Admin, Staff, Customer accounts and Settings created.');
    }

    /**
     * Create the account if it is missing; refresh its profile fields if it is
     * not. THE PASSWORD IS ONLY EVER SET ON CREATION.
     *
     * Two separate problems were fixed here, and they need different remedies.
     *
     * 1. THE PASSWORDS WERE LITERALS IN THIS FILE — a hashed constant for each
     *    of the admin, staff and sample customer accounts. This file is
     *    committed, so anyone who could read the repository held the production
     *    admin password. They now come from config, which reads SEED_*_PASSWORD
     *    out of the environment, so no password lives in git.
     *
     *    The old values are deliberately not quoted anywhere in this file, not
     *    even to describe what was removed: a credential in a comment is just
     *    as published as one in code. SecretsAndDatabaseTest asserts that over
     *    the whole file, comments included.
     *
     * 2. `updateOrCreate()` PUT THE PASSWORD IN THE *UPDATE* ARRAY, so a
     *    re-run silently reset a rotated password back to the value above.
     *    That is the worse half: rotating the admin password by hand looked
     *    like it worked, and the next `db:seed` quietly undid it with no
     *    output saying so. Hence the split below — profile fields are still
     *    refreshed on every run (that is what makes the seeder idempotent),
     *    but an existing row's password is never touched.
     *
     * Read through config(), NOT env(): `docker/start.sh` runs
     * `php artisan config:cache`, and once a cached config exists Laravel skips
     * LoadEnvironmentVariables entirely, so env() returns null in the
     * container. A guard keyed on env() would have thrown on exactly the
     * deployment it was written to protect. See config/seeding.php.
     */
    private function seedAccount(string $key, string $email, array $attributes): void
    {
        $existing = User::where('email', $email)->first();

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        $password = (string) config("seeding.passwords.{$key}", '');

        if ($password === '') {
            $env = 'SEED_'.strtoupper($key).'_PASSWORD';

            throw new RuntimeException(
                "Refusing to create {$email} with no password configured. Set {$env} "
                .'to a password you have generated, then re-run the seeder. It is '
                .'deliberately not defaulted: a default in this file is a published '
                .'credential, which is the bug this guard exists to prevent.'
            );
        }

        User::create($attributes + ['email' => $email, 'password' => Hash::make($password)]);
    }
}