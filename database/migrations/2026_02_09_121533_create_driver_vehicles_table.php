<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_vehicles', function (Blueprint $table) {
            $table->id();

            $table->string('picture')->nullable();

            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('mst_vehicles')
                ->nullOnDelete();

            $table->string('driver_vehicle_code', 20)->unique();

            $table->string('license_plate_number', 30)->nullable();
            $table->string('registration_number', 100)->nullable();

            $table->string('vehicle_color', 30)->nullable();

            $table->decimal('max_load_capacity_kg', 8, 2)->nullable();
            $table->decimal('max_volume_capacity_cft', 8, 2)->nullable();
            $table->decimal('max_number_of_packages', 8, 2)->nullable();

            $table->boolean('is_active')->default(true)->nullable();    // block/unblock login
            $table->string('inactive_reason', 100)->nullable();

            $table->boolean('is_available_for_delivery')->default(false)->nullable(); // for driver to stay online or offline for delivery

            // last known location of vehicle for better assignment and tracking
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_vehicles');
    }
};
