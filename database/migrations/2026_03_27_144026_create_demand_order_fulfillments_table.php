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
        Schema::create('demand_order_fulfillments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('demand_order_id')
                ->constrained('demand_orders')
                ->cascadeOnDelete();

            $table->foreignId('demand_order_item_id')
                ->constrained('demand_order_items')
                ->cascadeOnDelete();

            $table->string('fulfillment_number', 20)->unique();

            // Seller snapshot (important)
            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('pickup_fulfillment_location_id')
                ->nullable()
                ->constrained('fulfillment_locations')
                ->nullOnDelete();

            // Listing references
            $table->foreignId('product_listing_item_id')
                ->nullable()
                ->constrained('product_listing_items')
                ->nullOnDelete();

            $table->foreignId('product_listing_package_id')
                ->nullable()
                ->constrained('product_listing_packages')
                ->nullOnDelete();

            // from seller or marketplace (important for reporting)

            $table->foreignId('market_id')
                ->nullable()
                ->constrained('mst_markets')
                ->nullOnDelete();

            $table->decimal('pack_size', 15, 2);
            $table->string('pack_unit', 20)->nullable();
            $table->string('pack_type_unit', 50)->nullable();
            $table->decimal('pack_price', 15, 2)->default(0);
            $table->decimal('per_unit_price', 15, 2)->default(0)->nullable();


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_order_fulfillments');
    }
};
