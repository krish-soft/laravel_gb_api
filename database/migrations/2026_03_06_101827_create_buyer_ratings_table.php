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
        Schema::create('buyer_ratings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id')->nullable(); // For which order
            $table->unsignedBigInteger('buyer_id')->nullable(); // For which buyer
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
        Schema::dropIfExists('buyer_ratings');
    }
};
