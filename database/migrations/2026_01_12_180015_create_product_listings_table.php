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
        Schema::create('product_listings', function (Blueprint $table) {
            $table->id();

            // Seller
            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete(); // Only Seller Can List Market Items

            // Pickup 
            $table->foreignId('fulfillment_location_id')
                ->nullable()
                ->constrained('fulfillment_locations')
                ->restrictOnDelete();

            $table->string('listing_code', 50)->unique();

            $table->unsignedBigInteger('doc_no'); // sequential number per seller
            $table->date('doc_date');

            $table->boolean('is_sell_to_market')->default(false)->nullable(); //             
            $table->boolean('is_seller_dropoff')->default(false)->nullable();

            $table->boolean('is_active')->default(true)->nullable(); // Active after 2 mins of creation
            $table->string('inactive_reason', 100)->nullable();

            $table->boolean('is_partial')->default(false)->nullable();
            $table->boolean('is_sold')->default(false)->nullable();
            $table->boolean('is_locked')->default(false)->nullable();

            // Freshness 24 hours
            $table->boolean('is_expired')->default(false)->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // indexes & uniques
            $table->unique(['seller_id', 'doc_no']);
            $table->index(['seller_id', 'is_active']);
            $table->index(['is_sell_to_market', 'is_active']);
            $table->index(['is_sold', 'is_partial']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_listings');
    }
};
