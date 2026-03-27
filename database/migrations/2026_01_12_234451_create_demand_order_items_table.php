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
        Schema::create('demand_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('demand_order_id')
                ->constrained('demand_orders')
                ->cascadeOnDelete();

            $table->string('order_number', 20)->nullable();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('mst_products')
                ->nullOnDelete();

            $table->string('product_code', 20)->nullable();
            $table->string('product_name');

            $table->string('variant_code', 20)->nullable();
            $table->string('variant_name')->nullable();

            $table->decimal('order_qty', 15, 2);
            $table->decimal('ship_qty', 15, 2)->default(0);

            $table->decimal('pack_size', 15, 2);
            $table->string('pack_unit', 20)->nullable();
            $table->string('pack_type_unit', 50)->nullable();

            $table->decimal('pack_price', 15, 2)->default(0);
            $table->decimal('per_unit_price', 15, 2)->default(0)->nullable();

            $table->decimal('discount_amount', 15, 2)->default(0)->nullable();
            $table->string('discount_type', 30)->nullable();

            $table->decimal('taxable_amount', 15, 2); // ship_qty * per_unit_price - discount_amount = taxable_amount
            $table->decimal('tax_amount', 15, 2)->default(0)->nullable();
            $table->decimal('total_amount', 15, 2);

            $table->string('reference', 100)->nullable();
            $table->string('remarks', 100)->nullable();

            $table->boolean('is_fulfilled')->default(false)->nullable();
            $table->boolean('is_cancelled')->default(false)->nullable();
            $table->boolean('is_returned')->default(false)->nullable();
            $table->boolean('is_replaced')->default(false)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // indexes
            $table->index(['demand_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_order_items');
    }
};
