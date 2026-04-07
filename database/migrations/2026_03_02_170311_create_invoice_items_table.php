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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('source_id')->nullable(); // order_item_id or return_item_id or refund_item_id

            $table->string('item_code', 20)->nullable();
            $table->string('item_name');

            $table->decimal('order_qty', 15, 2);
            $table->decimal('ship_qty', 15, 2)->default(0)->nullable();

            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('ship_unit_price', 15, 2)->default(0)->nullable();

            $table->decimal('discount_amount', 15, 2)->default(0)->nullable();
            $table->decimal('taxable_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0)->nullable();
            $table->decimal('total_amount', 15, 2);

            $table->string('reference', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
