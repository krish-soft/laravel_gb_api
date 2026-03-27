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
        Schema::create('demand_cart_items', function (Blueprint $table) {
            $table->id();

            // Cart
            $table->foreignId('demand_cart_id')
                ->constrained('demand_carts')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('mst_products')
                ->cascadeOnDelete();


            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('mst_product_variants')
                ->cascadeOnDelete();


            $table->unsignedInteger('order_qty');

            // Package snapshot
            $table->decimal('pack_size', 15, 2);
            $table->string('pack_unit', 20);
            $table->string('pack_type_unit', 50)->nullable();

            // Price snapshot
            $table->decimal('pack_price', 15, 2);
            $table->decimal('per_unit_price', 15, 2)->nullable();

            $table->decimal('discount_amount', 15, 2)->default(0)->nullable();
            $table->string('discount_type', 30)->nullable();

            $table->decimal('total_price', 15, 2);

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate same package in same cart
            $table->unique(['demand_cart_id', 'product_id', 'variant_id', 'deleted_at'], 'demand_cart_item_unique');

            // Indexes for faster lookups
            $table->index(['product_id']);
            $table->index(['demand_cart_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_cart_items');
    }
};
