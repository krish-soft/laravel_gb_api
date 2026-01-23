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
        Schema::create('mst_product_variants', function (Blueprint $table) {
            $table->id();

            $table->string('picture')->nullable();

            $table->foreignId('product_id')
                ->constrained('mst_products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('variant_code', 20)->unique();   // eg: TOM-ROMA
            $table->string('name');                // eg: Tomato Roma
            $table->text('description')->nullable();

            $table->string('sku', 20)->nullable();
            $table->string('upc', 20)->nullable();
            $table->string('hsn', 20)->nullable();

            $table->string('grade', 50)->nullable();         // A, B, Export
            $table->string('size', 50)->nullable();          // small, medium
            $table->string('origin', 100)->nullable();       // farm/region

            $table->boolean('is_active')->default(true)->nullable();

            $table->string('custchar1', 100)->nullable();
            $table->string('custchar2', 100)->nullable();


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_product_variants');
    }
};
