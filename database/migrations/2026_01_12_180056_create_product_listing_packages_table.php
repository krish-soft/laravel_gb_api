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
        Schema::create('product_listing_packages', function (Blueprint $table) {
            $table->id();

            $table->string('picture')->nullable();
            $table->string('picture2')->nullable();
            $table->string('picture3')->nullable();

            // Product Listing Item
            $table->foreignId('product_listing_item_id')
                ->nullable()
                ->constrained('product_listing_items')
                ->cascadeOnDelete();

            $table->string('listing_code', 50)->nullable();

            // Package Quantity
            $table->decimal('qty', 15, 2); // total qty in the package
            $table->decimal('sold_qty', 15, 2)->default(0); // qty sold from the package
            $table->decimal('ship_qty', 15, 2)->default(0); // qty shipped from the package

            // for accounting 
            $table->decimal('reverse_qty', 15, 2)->default(0); // qty reversed back to available
            $table->decimal('reverse_amount', 15, 2)->default(0); // amount reversed back to available

            // when cutoff we have to store what actual qty listed in the package for future reference, as qty can be updated due to returns and cancellations
            $table->decimal('actual_qty', 15, 2)->nullable(); // total qty in the package

            // Package Details
            $table->decimal('pack_size', 15, 2);
            $table->string('pack_unit', 20); //  kg, g, L, ml, pcs
            $table->string('pack_type_unit', 50)->nullable(); // bag, crate
            $table->decimal('pack_base_price', 15, 2)->nullable(); // total_price for the package
            $table->decimal('pack_price', 15, 2); // total_price for the package // if discount
            $table->decimal('per_kg_price', 15, 2)->nullable(); // per Kg for future (pack_price / (pack_size in kg))

            $table->string('quality_grade', 20)->nullable();

            // Discount
            $table->decimal('discount_amount', 15, 2)->default(0)->nullable();
            $table->string('discount_type', 30)->nullable();

            $table->boolean('is_partial')->default(false)->nullable();
            $table->boolean('is_sold')->default(false)->nullable();
            $table->boolean('is_locked')->default(false)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // indexes
            $table->index('product_listing_item_id');
            $table->index(['is_sold', 'is_partial']);
            $table->index('listing_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_listing_packages');
    }
};
