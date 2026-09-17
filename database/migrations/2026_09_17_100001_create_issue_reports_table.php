<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mga ulat ng problema sa villa ("aircon not working") — mula sa guest na
 * kasalukuyang naka-check-in, o mula sa staff.
 *
 * Hiwalay sa `housekeeping_tasks`: ang task ay utos ng admin, ang report ay
 * ulat mula sa loob ng villa. Parehong status (pending → in_progress →
 * completed / cancelled), pero magkaibang pinagmulan at magkaibang field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('reporter_role', ['customer', 'staff']);
            $table->enum('category', ['aircon', 'plumbing', 'electrical', 'cleanliness', 'wifi', 'pool', 'other']);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_reports');
    }
};
