<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 'cottage' and 'hall' were leftover options from an earlier, more
     * generic multi-property-type design — no code path anywhere in the
     * app (portal, booking, pricing) ever handles them, and no existing
     * property row uses either value. Narrowing to just the two types
     * that are actually meaningful: 'villa' (exactly one — the whole
     * bookable resort) and 'room' (display-only sub-units).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE properties MODIFY type ENUM('villa', 'room') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE properties MODIFY type ENUM('villa', 'cottage', 'room', 'hall') NOT NULL");
    }
};
