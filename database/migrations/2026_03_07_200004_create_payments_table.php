<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', [
                'gcash',
                'paymaya',
                'card',
                'cash',
                'bank_transfer'
            ]);
            $table->enum('payment_type', [
                'deposit',
                'full_payment',
                'balance',
                'extra'
            ]);
            $table->string('transaction_ref', 100)->nullable();
            $table->json('gateway_response')->nullable();
            $table->enum('status', [
                'pending',
                'success',
                'failed',
                'refunded'
            ])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('payment_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};