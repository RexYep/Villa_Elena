<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 'deposit' and 'partial' were being used interchangeably across the
     * codebase to mean the same thing — a payment that's less than the
     * full amount due. Consolidating on 'partial' and dropping 'deposit'
     * from the ENUM. Existing 'deposit' rows are relabeled to 'partial'
     * first so the ENUM can be safely narrowed.
     */
    public function up(): void
    {
        DB::table('payments')
            ->where('payment_type', 'deposit')
            ->update(['payment_type' => 'partial']);

        DB::statement("
            ALTER TABLE payments
            MODIFY payment_type ENUM('full_payment','balance','extra','partial','refund') NOT NULL
        ");
    }

    /**
     * Not reversible for the relabeled data — we can't tell which
     * 'partial' rows used to be 'deposit'. Restores the wider ENUM only.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_type ENUM('deposit','full_payment','balance','extra','partial','refund') NOT NULL
        ");
    }
};
