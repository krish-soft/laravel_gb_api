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
        Schema::create('mst_product_prices', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_id')
                ->constrained('mst_products')
                ->cascadeOnUpdate()
                ->nullOnDelete()
                ->index();

            $table->string('product_code', 20)->nullable()->index();

            // Daily pricing reference
            $table->date('price_date')->nullable()->index();

            // Pricing
            $table->decimal('price', 12, 2);
            $table->decimal('max_price', 12, 2)->nullable();
            $table->decimal('min_price', 12, 2)->nullable();

            // Flag to identify copied price
            $table->boolean('is_auto_created')->default(false)->index();

            // Optional market segmentation
            $table->foreignId('market_id')
                ->nullable()
                ->constrained('mst_markets')
                ->nullOnDelete()
                ->index();

            $table->foreignId('depot_id')
                ->nullable()
                ->constrained('mst_depots')
                ->nullOnDelete()
                ->index();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for common queries
            $table->index(['product_id', 'price_date']);
            $table->index(['price_date', 'market_id']);
            $table->index(['price_date', 'depot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_product_prices');
    }
};
