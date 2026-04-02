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
        Schema::create('seller_packages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->foreignId('product_listing_package_id')->nullable()->constrained('product_listing_packages')->cascadeOnDelete();
            $table->foreignId('product_listing_item_id')->nullable()->constrained('product_listing_items')->cascadeOnDelete();
            $table->foreignId('product_listing_id')->nullable()->constrained('product_listings')->cascadeOnDelete();

            $table->foreignId('product_id')->nullable()->constrained('mst_products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('mst_product_variants')->nullOnDelete();

            $table->date('package_date')->nullable(); // Date when the package was created or assigned

            $table->string('package_uid', 20)->unique();
            $table->string('package_number', 20);

            $table->boolean('is_used')->default(false)->nullable();
            $table->boolean('is_seller_dropoff')->default(false)->nullable();


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_packages');
    }
};
