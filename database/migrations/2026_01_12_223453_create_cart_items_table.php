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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            // Cart
            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            // Seller snapshot (important)
            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            // Listing references
            $table->foreignId('product_listing_item_id')
                ->constrained('product_listing_items')
                ->restrictOnDelete();

            $table->foreignId('product_listing_package_id')
                ->constrained('product_listing_packages')
                ->restrictOnDelete();

            /**
             * ORDER QUANTITY
             * Number of packages ordered
             */
            $table->unsignedInteger('order_qty');

            // Package snapshot
            $table->decimal('pack_size', 10, 2);
            $table->string('pack_unit', 20);
            $table->string('pack_type_unit', 50)->nullable();

            // Price snapshot
            $table->decimal('pack_price', 15, 2);
            $table->decimal('per_unit_price', 15, 2);

            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('discount_type', 30)->nullable();

            $table->decimal('total_price', 15, 2);

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate same package in same cart
            $table->unique(['cart_id', 'product_listing_package_id', 'deleted_at'], 'cart_package_unique');

            // Indexes for faster lookups
            $table->index(['product_listing_item_id']);
            $table->index(['product_listing_package_id']);
            $table->index(['seller_id']);

            $table->index(['cart_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
