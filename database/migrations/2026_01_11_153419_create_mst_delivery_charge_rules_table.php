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
        Schema::create('mst_delivery_charge_rules', function (Blueprint $table) {
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

            $table->string('calc_type', 50);
            $table->string('calc_condition', 10)->nullable();    // < or >         

            $table->decimal('measure_value', 15, 2)->nullable();
            $table->string('measure_unit', 50)->nullable();
            $table->string('pack_type_unit', 50)->nullable();

            $table->decimal('charge_amount', 15, 2);

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
        Schema::dropIfExists('mst_delivery_charge_rules');
    }
};
