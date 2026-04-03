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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // All buyers,seller,delivery,return,refund
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->foreignId('demand_order_id')
                ->nullable()
                ->constrained('demand_orders')
                ->nullOnDelete();

            $table->foreignId('market_order_id')
                ->nullable()
                ->constrained('market_orders')
                ->nullOnDelete();

            $table->foreignId('product_listing_id')
                ->nullable()
                ->constrained('product_listings')
                ->nullOnDelete();

            $table->string('invoice_number', 20)->unique();
            $table->date('invoice_date'); // what is order date that time invoice generated
            $table->string('invoice_path')->nullable(); // file path of invoice

            $table->string('invoice_type', 50)->nullable(); // sale/refund/return/delivery

            $table->string('status', 20)->default('generated')->nullable();
            $table->string('payment_status', 30)->nullable();

            // platform, business, customer billing address code
            $table->string('platform_bill_addr_code', 20)->nullable(); // fix 

            $table->string('customer_bill_addr_code', 20)->nullable();
            $table->string('customer_ship_addr_code', 20)->nullable();

            $table->tinyInteger('revision_count')->default(0)->nullable();

            $table->decimal('base_amount', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0)->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('INR');

            $table->string('reference', 100)->nullable(); // internal reference
            $table->string('remarks')->nullable(); // internal reference

            $table->boolean('is_locked')->default(false)->nullable(); // to prevent changes after generation

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
