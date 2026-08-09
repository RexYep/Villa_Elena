<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Room-type properties are now optional-name (they're just informational
     * sub-units of the single Villa) — Villa itself stays required at the
     * application/validation level.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE properties MODIFY property_name VARCHAR(150) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE properties MODIFY property_name VARCHAR(150) NOT NULL DEFAULT ''");
    }
};
