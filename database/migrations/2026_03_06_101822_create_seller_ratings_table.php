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
        Schema::create('seller_ratings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_listing_id')->nullable(); // For which product listing
            $table->unsignedBigInteger('seller_id')->nullable(); // For which seller
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
        Schema::dropIfExists('seller_ratings');
    }
};
