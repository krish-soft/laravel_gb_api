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
        Schema::create('mst_minimum_order_charge_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('charge_level_id')
                ->nullable()
                ->constrained('mst_charge_levels')
                ->restrictOnDelete();

            $table->foreignId('charge_id')
                ->nullable()
                ->constrained('mst_charges')
                ->restrictOnDelete();

            $table->unsignedInteger('rule_no')->unique();
            $table->string('description')->nullable();

            $table->string('calc_base', 50)->nullable();   // PRICE, QTY, WEIGHT
            $table->string('calc_type', 50);   // FIXED, PERCENTAGE
            $table->string('calc_condition', 10)->nullable();    // < or >         

            $table->decimal('min_order_price', 15, 2)->nullable(); // Platform Fee
            $table->decimal('min_order_qty', 15, 2)->nullable(); // Shiping Handling
            $table->decimal('min_order_weight', 15, 2)->nullable(); // Delivery Fee

            $table->decimal('charge_amount', 15, 2)->nullable();
            $table->boolean('is_active')->default(true)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['charge_level_id', 'calc_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_minimum_order_charge_rules');
    }
};
