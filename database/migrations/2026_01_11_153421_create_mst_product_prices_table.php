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
                ->nullable()
                ->constrained('mst_products')
                ->nullOnDelete();

            $table->string('product_code', 20)->nullable();

            $table->date('price_date')->nullable();

            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('max_price', 15, 2)->default(0)->nullable();
            $table->decimal('min_price', 15, 2)->default(0)->nullable();


            // Future Use
            $table->foreignId('market_id')
                ->nullable()
                ->constrained('mst_markets')
                ->nullOnDelete();

            $table->foreignId('depot_id')
                ->nullable()
                ->constrained('mst_depots')
                ->nullOnDelete();


            $table->timestamps();
            $table->softDeletes();
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
