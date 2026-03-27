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
        Schema::create('mst_product_price_rules', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('rule_no')->unique();
            
            $table->foreignId('charge_level_id')
                ->nullable()
                ->constrained('mst_charge_levels')
                ->nullOnDelete();

            $table->string('user_type', 20)->nullable(); // buyer, seller, all
            $table->string('pack_unit', 20)->default('kg')->nullable(); // 
            $table->string('calc_type', 50)->default('percentage');   // 

            $table->decimal('1', 10, 2)->default(0)->nullable(); // 1 Kg Pkg price
            $table->decimal('2', 10, 2)->default(0)->nullable(); // 2 Kg Pkg price
            $table->decimal('3', 10, 2)->default(0)->nullable(); // 3 Kg Pkg price
            $table->decimal('5', 10, 2)->default(0)->nullable(); // 5 Kg Pkg price
            $table->decimal('10', 10, 2)->default(0)->nullable(); // 10 Kg Pkg price
            $table->decimal('20', 10, 2)->default(0)->nullable(); // 20 Kg Pkg price

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
        Schema::dropIfExists('mst_product_price_rules');
    }
};
