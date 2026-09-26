<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `trusted_devices.token` held the trusted-device token in plaintext, and that
 * token is not a reference to a credential — it IS one. It is the exact string
 * the browser keeps in its `trusted_device` cookie, and presenting it skips the
 * emailed 2FA code outright for 60 days.
 *
 * So anyone able to read this table — a DB backup, an Aiven snapshot, a
 * SELECT by anyone holding production credentials — could paste any row's value
 * into a cookie and inherit that person's second-factor bypass, without ever
 * learning their password. `users.password` next door is bcrypt-hashed for
 * precisely this reason; the same threat applied here and the same defence was
 * not used.
 *
 * Two deliberate choices:
 *
 *   - **SHA-256, not bcrypt.** These are 64 characters of `Str::random()`, not
 *     a memorable secret. There is no dictionary to try, so a slow hash buys
 *     nothing and would be paid on every login instead. The column is already
 *     `string(64)`, and hex SHA-256 is exactly 64 characters, so the width is
 *     unchanged.
 *
 *   - **Existing rows are hashed in place, not deleted.** Every current cookie
 *     keeps working, because the cookie holds the raw token and the app now
 *     hashes it before comparing. Nobody gets logged out and nobody has to
 *     re-verify. If these grants should instead be treated as already exposed,
 *     the stronger move is a truncate — one line, and the cost is one emailed
 *     code per device.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trusted_devices') || Schema::hasColumn('trusted_devices', 'token_hash')) {
            return;
        }

        // Hash before renaming: the rows are read through the old column name.
        DB::table('trusted_devices')
            ->select('id', 'token')
            ->orderBy('id')
            ->chunkById(500, function ($devices) {
                foreach ($devices as $device) {
                    DB::table('trusted_devices')
                        ->where('id', $device->id)
                        ->update(['token' => hash('sha256', $device->token)]);
                }
            });

        Schema::table('trusted_devices', function ($table) {
            // The name is the point. `token` reads like something you could put
            // in a cookie; `token_hash` cannot be mistaken for that.
            $table->renameColumn('token', 'token_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trusted_devices') || ! Schema::hasColumn('trusted_devices', 'token_hash')) {
            return;
        }

        Schema::table('trusted_devices', function ($table) {
            $table->renameColumn('token_hash', 'token');
        });

        // The plaintext tokens are gone for good — a hash does not reverse.
        // Every device has to verify by email once more after a rollback.
        DB::table('trusted_devices')->delete();
    }
};
