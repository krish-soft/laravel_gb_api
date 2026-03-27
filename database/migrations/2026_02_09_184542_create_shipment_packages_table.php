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
        Schema::create('shipment_packages', function (Blueprint $table) {
            $table->id();

            // Core relations (DENORMALIZED for fast access)
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->cascadeOnDelete();


            // Market order relations (if applicable)
            $table->foreignId('market_order_id')
                ->nullable()
                ->constrained('market_orders')
                ->cascadeOnDelete();

            $table->foreignId('market_order_item_id')
                ->nullable()
                ->constrained('market_order_items')
                ->cascadeOnDelete();

            // Demand order relations (if applicable)
            $table->foreignId('demand_order_id')
                ->nullable()
                ->constrained('demand_orders')
                ->cascadeOnDelete();

            $table->foreignId('demand_order_item_id')
                ->nullable()
                ->constrained('demand_order_items')
                ->cascadeOnDelete();

            // Direct access (avoid joining orders/users every time)
            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            // Per fulfillment location (if multiple locations are used)
            $table->foreignId('pickup_fulfillment_location_id')
                ->nullable()
                ->constrained('fulfillment_locations')
                ->nullOnDelete();

            $table->foreignId('shipping_fulfillment_location_id')
                ->nullable()
                ->constrained('fulfillment_locations')
                ->nullOnDelete();

            // depots

            $table->foreignId('pickup_depot_id')
                ->nullable()
                ->constrained('mst_depots')
                ->nullOnDelete();

            $table->foreignId('shipping_depot_id')
                ->nullable()
                ->constrained('mst_depots')
                ->nullOnDelete();

            $table->foreignId('market_id')
                ->nullable()
                ->constrained('mst_markets')
                ->nullOnDelete();

            $table->foreignId('product_listing_package_id')
                ->nullable()
                ->constrained('product_listing_packages')
                ->nullOnDelete();

            $table->foreignId('product_listing_id')
                ->nullable()
                ->constrained('product_listings')
                ->nullOnDelete();


            $table->string('order_type', 20)->nullable(); // normal, market, etc.
            $table->date('shipment_date')->nullable();

            $table->string('product_code', 50)->nullable();
            $table->string('product_name')->nullable();

            // One row = one physical package
            $table->decimal('qty', 15, 2)->default(1);
            $table->decimal('ship_qty', 15, 2)->default(0)->nullable();

            // Package content
            $table->decimal('pack_size', 15, 2)->nullable();
            $table->string('pack_unit', 20)->nullable();
            $table->decimal('pack_price', 15, 2)->nullable();
            $table->string('pack_type_unit', 50)->nullable();

            // Identifiers
            $table->string('shipment_package_number', 20)->unique(); // HARD unique label
            $table->string('package_number', 30); // A-1, AA-2 etc.

            // Status flow
            $table->string('status', 50)->default('pending');  // pending | packed | ready_for_pickup | shipped | delivered | returned | mising | cancelled
            $table->string('action_status', 50)->default('pending')->nullable();  // From Adming

            $table->string('seller_status', 50)->default('pending')->nullable();
            $table->string('buyer_status', 50)->default('pending')->nullable();
            $table->string('transfer_status', 50)->default('pending')->nullable();
            $table->string('other_status', 50)->default('pending')->nullable();

            // Courier
            $table->string('carrier', 50)->nullable();
            $table->string('tracking_number', 50)->nullable();

            $table->string('remarks')->nullable();

            $table->timestamp('packed_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // To Eliminate in grouping confusion between seller drop-off and buyer pickup
            $table->boolean('is_seller_dropoff')->default(false)->nullable();
            $table->boolean('is_buyer_pickup')->default(false)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes ONLY (NOT unique)
            $table->index(['order_id']);
            $table->index(['buyer_id', 'package_number']);
            $table->index(['shipping_depot_id', 'status']);
            $table->index(['pickup_depot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_packages');
    }
};
