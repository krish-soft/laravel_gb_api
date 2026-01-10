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
        Schema::create('mst_vehicles', function (Blueprint $table) {
            $table->id();

            $table->string('picture')->nullable();

            $table->string('vehicle_code', 50)->unique(); // Rickshaw, Tempo, Truck
            $table->string('vehicle_name', 100); // Rickshaw, Tempo, Truck
            $table->string('description')->nullable();

            $table->string('body_type', 50)->nullable();
            $table->string('capacity_class', 30)->nullable();

            $table->decimal('max_weight_kg', 8, 2)->nullable();
            $table->decimal('max_volume_cft', 8, 2)->nullable();
            $table->unsignedInteger('max_crates')->nullable();

            $table->unsignedInteger('priority_order')->default(1)->nullable();
            $table->boolean('is_active')->default(true)->nullable();

            $table->text('notes')->nullable();

            $table->string('custchar1', 100)->nullable();
            $table->string('custchar2', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_vehicles');
    }
};
