<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('property_name', 150);
            $table->enum('type', ['villa', 'cottage', 'room', 'hall']);
            $table->text('description')->nullable();
            $table->integer('max_capacity');
            $table->decimal('base_price', 10, 2);
            $table->decimal('weekend_price', 10, 2)->nullable();
            $table->json('amenities')->nullable();
            $table->decimal('floor_area_sqm', 6, 2)->nullable();
            $table->tinyInteger('floor_level')->nullable();
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->tinyInteger('is_featured')->default(0);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};