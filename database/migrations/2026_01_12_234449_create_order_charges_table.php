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
        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('order_number', 20)->nullable();

            $table->string('charge_code', 50)->nullable();
            $table->string('charge_name', 100);

            $table->string('rule_type', 50)->nullable();
            $table->string('rule_no', 30)->nullable();
            $table->string('rule_desc', 150)->nullable();

            $table->decimal('taxable_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0)->nullable();
            $table->decimal('total_amount', 15, 2);

            $table->timestamps();
            $table->softDeletes();

            // indexes
            $table->index(['order_id', 'charge_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_charges');
    }
};
