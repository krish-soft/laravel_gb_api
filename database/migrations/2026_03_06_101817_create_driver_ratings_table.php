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
        Schema::create('driver_ratings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('driver_shipment_id')->nullable(); // For which shipment
            $table->unsignedBigInteger('driver_id')->nullable(); // For which driver
            $table->unsignedBigInteger('user_id'); // by whom

            $table->unsignedTinyInteger('rating');
            $table->text('review')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_ratings');
    }
};
