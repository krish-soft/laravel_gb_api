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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->nullable()
                ->constrained('carts')
                ->nullOnDelete();

            // Seller snapshot (important)
            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            // Seller snapshot (important)
            $table->foreignId('depot_id')
                ->nullable()
                ->constrained('mst_depots')
                ->nullOnDelete();


            $table->foreignId('shipping_fulfillment_location_id')
                ->nullable()
                ->constrained('fulfillment_locations')
                ->restrictOnDelete();

            $table->string('order_number', 20)->unique();

            $table->string('order_status', 30)->default('pending');
            $table->string('delivery_status', 30)->default('pending')->nullable();

            $table->date('order_date');
            $table->date('expected_ship_date')->nullable();

            $table->decimal('base_amount', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0)->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0)->nullable();
            $table->string('currency', 10)->default('INR');

            // For Invoice Billing & Shipping
            $table->string('bill_addr_code', 20)->nullable(); // Billing Address
            $table->string('ship_addr_code', 20)->nullable(); // Shipping Address

            // Pickup info
            $table->boolean('is_buyer_pickup')->default(false)->nullable();
            $table->string('pickup_addr_code', 20)->nullable(); // Pickup Address for Seller Pickup

            // payment
            $table->string('payment_method', 30);
            $table->string('payment_status', 30)->nullable();
            $table->string('payment_reference', 100)->nullable();

            $table->string('reference', 100)->nullable(); // internal reference


            $table->boolean('is_partial')->default(false)->nullable();
            $table->boolean('is_paid')->default(false)->nullable();
            $table->boolean('is_locked')->default(false)->nullable(); // No one can modify after this
            $table->boolean('is_manual')->default(false)->nullable(); // when send to market and then we have to create order manually


            $table->string('remarks', 100)->nullable(); // internal reference

            $table->timestamps();
            $table->softDeletes();

            // indexes
            $table->index(['buyer_id', 'order_status']);
            $table->index(['order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
