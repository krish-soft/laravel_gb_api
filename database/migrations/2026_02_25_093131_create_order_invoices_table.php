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
        Schema::create('order_invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('invoice_number', 20)->unique();
            $table->date('invoice_date'); // what is order date that time invoice generated

            $table->string('invoice_path')->nullable(); // file path of invoice
            $table->string('order_type')->nullable(); // order/refund

            $table->string('status', 20)->default('generated')->nullable();

            // To keep record on which company we use for billing, in case of multiple companies
            $table->string('business_bill_addr_code')->nullable(); // file path of invoice


            $table->tinyInteger('count')->default(0)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_invoices');
    }
};
