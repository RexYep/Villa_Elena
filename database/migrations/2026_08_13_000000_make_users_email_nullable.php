<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pumapayag ito sa staff walk-in "guest record lang, walang login
     * account" na flow — hindi lahat ng guest ay may/gustong email.
     * Ang `unique` ay hindi apektado (maraming NULL ang pinapayagan ng
     * MySQL sa isang unique column). Hindi ito nagpapahina sa online
     * registration — hiwalay na `required` validation rule pa rin ang
     * nagpapatupad noon sa AuthController::register().
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 100)->nullable(false)->change();
        });
    }
};
