<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('paymongo_session_id')->nullable()->after('source');
            $table->string('paymongo_payment_type')->nullable()->after('paymongo_session_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->after('payment_date');
            $table->unsignedBigInteger('received_by')->nullable()->after('reference_number');
        });
    }

    public function down(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['paymongo_session_id','paymongo_payment_type']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['reference_number','received_by']);
        });
    }
};