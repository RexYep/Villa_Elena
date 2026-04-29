<?php
// ============================================================
// FILE: database/seeders/AdminSeeder.php
// ============================================================
namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        User::updateOrCreate(
            ['email' => 'admin@villaelenareosrt.com'],
            [
                'full_name' => 'Villa Elena Admin',
                'email'     => 'admin@villaelenareosrt.com',
                'password'  => Hash::make('Admin@1234'),
                'role'      => 'admin',
                'phone'     => '09000000000',
                'status'    => 1,
            ]
        );

        // Create a sample Staff account
        User::updateOrCreate(
            ['email' => 'staff@villaelenareosrt.com'],
            [
                'full_name' => 'Front Desk Staff',
                'email'     => 'staff@villaelenareosrt.com',
                'password'  => Hash::make('Staff@1234'),
                'role'      => 'staff',
                'phone'     => '09111111111',
                'status'    => 1,
            ]
        );

        // Create a sample Customer account
        User::updateOrCreate(
            ['email' => 'guest@example.com'],
            [
                'full_name' => 'Sample Guest',
                'email'     => 'guest@example.com',
                'password'  => Hash::make('Guest@1234'),
                'role'      => 'customer',
                'phone'     => '09222222222',
                'status'    => 1,
            ]
        );

        // Seed default system settings
        $settings = [
            ['setting_key' => 'resort_name',        'setting_value' => 'Villa Elena Private Rental Resort', 'data_type' => 'string',  'description' => 'Resort display name'],
            ['setting_key' => 'resort_email',        'setting_value' => 'info@villaelenareosrt.com',         'data_type' => 'string',  'description' => 'Main contact email'],
            ['setting_key' => 'resort_phone',        'setting_value' => '09000000000',                      'data_type' => 'string',  'description' => 'Main contact phone'],
            ['setting_key' => 'resort_address',      'setting_value' => 'Villa Elena, Philippines',         'data_type' => 'string',  'description' => 'Resort address'],
            ['setting_key' => 'booking_hold_minutes','setting_value' => '15',                               'data_type' => 'integer', 'description' => 'Minutes to hold a pending booking'],
            ['setting_key' => 'deposit_percentage',  'setting_value' => '30',                               'data_type' => 'integer', 'description' => 'Deposit % required to confirm booking'],
            ['setting_key' => 'cancellation_hours',  'setting_value' => '48',                               'data_type' => 'integer', 'description' => 'Hours before check-in for free cancellation'],
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
}