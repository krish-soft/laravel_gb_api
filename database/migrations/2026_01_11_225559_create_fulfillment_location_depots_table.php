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
        Schema::create('fulfillment_location_depots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fulfillment_location_id')->nullable()->constrained('fulfillment_locations')->cascadeOnDelete();
            $table->foreignId('depot_id')->nullable()->constrained('mst_depots')->cascadeOnDelete();

            $table->boolean('is_primary')->default(false)->nullable();
            $table->boolean('is_active')->default(true)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_location_depots');
    }
};
