<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dagdagan ang payment_type ENUM ng 'partial' at 'refund' — ginagamit
     * na ito sa maraming parte ng application code (FrontDeskController,
     * Admin/PaymentController refund) pero hindi pa kasama sa dating
     * listahan ng allowed values sa database, kaya nagre-result ng
     * "Data truncated for column 'payment_type'" error.
     *
     * Panatilihin ang 'extra' kahit mukhang hindi pa ginagamit ngayon —
     * baka may planong gamitin ito sa susunod (extra charges payment type),
     * mas ligtas na hindi na lang burahin.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_type ENUM('deposit','full_payment','balance','extra','partial','refund') NOT NULL
        ");
    }

    public function down(): void
    {
        // Balik sa dating listahan — TANDAAN: kung may existing rows na
        // gumagamit ng 'partial' o 'refund', mag-e-error itong rollback
        // hangga't hindi muna na-clean ang mga row na iyon.
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_type ENUM('deposit','full_payment','balance','extra') NOT NULL
        ");
    }
};
