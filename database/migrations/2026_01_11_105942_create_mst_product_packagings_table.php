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
        Schema::create('mst_product_packagings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('mst_products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('picture')->nullable();

            $table->decimal('pack_size', 10, 2);
            $table->string('pack_unit', 20);
            $table->string('pack_type_unit', 50)->nullable(); // Bag, Box, Bottle

            $table->decimal('length_in', 10, 2)->nullable();
            $table->decimal('width_in', 10, 2)->nullable();
            $table->decimal('height_in', 10, 2)->nullable(); // inch

            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->decimal('volume_cu_in', 10, 2)->nullable();

            $table->boolean('is_active')->default(true)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_product_packagings');
    }
};
