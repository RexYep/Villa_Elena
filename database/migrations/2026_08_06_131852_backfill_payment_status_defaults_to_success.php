<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every Payment row is only ever created at the moment a transaction
     * actually completes (walk-in cash received, PayMongo gateway confirmed
     * "paid", or an admin/refund record) — several of those code paths never
     * set `status` explicitly, so they silently inherited the column's
     * `pending` default even though the payment was genuinely successful.
     * `status` isn't read by any business logic anywhere in the app (only
     * displayed), so this backfill is safe: every existing 'pending' row
     * predates the code fix and represents a completed transaction.
     */
    public function up(): void
    {
        DB::table('payments')->where('status', 'pending')->update(['status' => 'success']);
    }

    /**
     * Not meaningfully reversible — rolling back would also flip genuinely
     * new 'pending'-turned-'success' payments created after this ran.
     */
    public function down(): void
    {
        //
    }
};
