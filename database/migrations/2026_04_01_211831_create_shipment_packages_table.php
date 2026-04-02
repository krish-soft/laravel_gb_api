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

            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->cascadeOnDelete();

            $table->foreignId('seller_package_id')->nullable()->constrained('seller_packages')->nullOnDelete();

            $table->string('source')->nullable(); // Like Order, DemandOrder, MarketOrder
            $table->unsignedBigInteger('source_id')->nullable(); // id of the source

            $table->string('source_item')->nullable(); // Like Order, DemandOrder, MarketOrder
            $table->unsignedBigInteger('source_item_id')->nullable(); // id of the source item

            $table->string('source_pkg')->nullable(); // Like Order, DemandOrder, MarketOrder
            $table->unsignedBigInteger('source_pkg_id')->nullable(); // id of the source package

            // Product details for easier access, can be fetched from seller package but denormalized here for easier access and to avoid issues when product details change after package creation
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete(); // dispatch owner
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete(); // pickup owner
            $table->foreignId('market_id')->nullable()->constrained('mst_markets')->nullOnDelete(); // pickup owner

            $table->foreignId('product_listing_package_id')->nullable()->constrained('product_listing_packages')->cascadeOnDelete();
            $table->foreignId('product_listing_item_id')->nullable()->constrained('product_listing_items')->cascadeOnDelete();
            $table->foreignId('product_listing_id')->nullable()->constrained('product_listings')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();

            $table->decimal('qty', 15, 2)->default(1);
            $table->decimal('pack_size', 15, 2)->nullable();
            $table->string('pack_unit', 20)->nullable();
            $table->decimal('pack_price', 15, 2)->nullable();
            $table->string('pack_type_unit', 50)->nullable();

            // Identifiers
            $table->string('shipment_package_number', 20)->unique(); // HARD unique label
            $table->string('shipment_trace_code', 100)->nullable(); // HARD unique label


            $table->string('package_number', 20); // 
            $table->string('package_number_buyer', 20)->nullable(); // 
            $table->string('package_number_seller', 20)->nullable(); // 
            $table->string('package_number_market', 20)->nullable(); // 

            // Status flow
            $table->string('status', 50)->default('pending');

            // To Eliminate in grouping confusion between seller drop-off and buyer pickup
            $table->boolean('is_seller_dropoff')->default(false)->nullable();
            $table->boolean('is_buyer_pickup')->default(false)->nullable();

            $table->timestamps();
            $table->softDeletes();
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
