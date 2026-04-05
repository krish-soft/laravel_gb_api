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
        Schema::create('market_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('market_order_id')
                ->nullable()
                ->constrained('market_orders')
                ->cascadeOnDelete();

            $table->string('market_order_number', 20)->nullable();

            // Listing references
            $table->foreignId('product_listing_id')
                ->nullable()
                ->constrained('product_listings')
                ->nullOnDelete();

            $table->foreignId('product_listing_item_id')
                ->nullable()
                ->constrained('product_listing_items')
                ->nullOnDelete();

            $table->foreignId('product_listing_package_id')
                ->nullable()
                ->constrained('product_listing_packages')
                ->nullOnDelete();

            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Per fulfillment location (if multiple locations are used)
            $table->foreignId('pickup_fulfillment_location_id')
                ->nullable()
                ->constrained('fulfillment_locations')
                ->restrictOnDelete();

            $table->string('listing_code', 50)->nullable(); // To Trace all way back to listing

            $table->foreignId('product_id')->nullable()->constrained('mst_products')->nullOnDelete();
            $table->string('product_code', 20)->nullable();
            $table->string('product_name', 100);

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('mst_product_variants')
                ->nullOnDelete();
                
            $table->string('variant_code', 20)->nullable();
            $table->string('variant_name', 100)->nullable();

            $table->decimal('order_qty', 15, 2);
            $table->decimal('ship_qty', 15, 2)->default(0);

            $table->decimal('pack_size', 15, 2);
            $table->string('pack_unit', 20);
            $table->string('pack_type_unit', 50)->nullable();

            $table->decimal('pack_price', 15, 2);
            $table->decimal('per_unit_price', 15, 2);

            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('discount_type', 30)->nullable();

            $table->decimal('taxable_amount', 15, 2); // ship_qty * per_unit_price - discount_amount = taxable_amount
            $table->decimal('tax_amount', 15, 2)->default(0)->nullable();
            $table->decimal('total_amount', 15, 2);

            $table->string('reference', 100)->nullable();
            $table->string('remarks', 100)->nullable();

            $table->boolean('is_reverse')->default(false)->nullable();
            $table->string('reverse_reference', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_order_items');
    }
};
