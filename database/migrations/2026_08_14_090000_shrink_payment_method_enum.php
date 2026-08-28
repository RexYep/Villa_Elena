<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Villa Elena no longer accepts card or bank transfer as a payment
     * method — only e-wallets (GCash/PayMaya) and cash going forward.
     * Existing 'card'/'bank_transfer' rows are relabeled to 'gcash' first
     * (closest e-wallet equivalent) so the ENUM can be safely narrowed —
     * MySQL would otherwise reject/truncate rows still using a value
     * that's being removed from the ENUM.
     */
    public function up(): void
    {
        DB::table('payments')
            ->whereIn('payment_method', ['card', 'bank_transfer'])
            ->update(['payment_method' => 'gcash']);

        DB::statement("
            ALTER TABLE payments
            MODIFY payment_method ENUM('gcash','paymaya','cash') NOT NULL
        ");
    }

    /**
     * Not reversible for the relabeled data — we can't tell which
     * 'gcash' rows used to be 'card'/'bank_transfer'. Restores the wider
     * ENUM only.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE payments
            MODIFY payment_method ENUM('gcash','paymaya','card','cash','bank_transfer') NOT NULL
        ");
    }
};
