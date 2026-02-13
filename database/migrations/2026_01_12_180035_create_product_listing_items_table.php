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
        Schema::create('product_listing_items', function (Blueprint $table) {
            $table->id();
            
            $table->string('picture')->nullable();

            // Product Listing
            $table->foreignId('product_listing_id')
                ->nullable()
                ->constrained('product_listings')
                ->cascadeOnDelete();

            $table->string('listing_code', 50)->nullable();

            // Product
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('mst_products')
                ->restrictOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('mst_product_variants')
                ->restrictOnDelete();

            $table->boolean('is_organic')->default(false)->nullable()->index();           

            $table->timestamps();
            $table->softDeletes();

            // indexes / constraints
            $table->index('product_listing_id');
            $table->index(['product_id', 'product_variant_id']);

            // prevent duplicate same product in same listing
            $table->unique(
                ['product_listing_id', 'product_id', 'product_variant_id'],
                'uniq_listing_product_variant'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_listing_items');
    }
};
