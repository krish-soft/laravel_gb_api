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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /* =====================================================
     | IDENTIFICATION
     ===================================================== */
            $table->uuid('payment_uuid')->unique();              // internal unique id
            $table->string('payment_code')->unique();            // PAY-2026-000001

            /* =====================================================
     | SOURCE (WHAT THIS PAYMENT IS FOR)
     | Order / Wallet / Subscription / Anything
     ===================================================== */
            $table->string('source_type');                       // Order::class, Wallet::class
            $table->unsignedBigInteger('source_id');             // order_id, wallet_id
            $table->string('source_code')->nullable();           // order_number, wallet_txn_code

            /* =====================================================
     | USER
     ===================================================== */


            /* =====================================================
     | AMOUNT DETAILS
     ===================================================== */
            $table->string('currency', 3)->default('INR');
            $table->decimal('amount', 12, 2);                    // total amount
            $table->decimal('tax_amount', 12, 2)->default(0)->nullable();
            $table->decimal('fee_amount', 12, 2)->default(0)->nullable();      // gateway/platform fee
            $table->decimal('net_amount', 12, 2)->nullable();                // amount - fees

            /* =====================================================
     | PAYMENT CONTEXT
     ===================================================== */
            $table->string('payment_type', 30);                  // checkout | wallet_topup
            $table->string('payment_method', 30)->nullable();    // razorpay | wallet | cod
            $table->string('gateway', 30)->nullable();           // razorpay
            $table->string('status', 30)->index();               // initiated | pending | paid | failed | refunded

            /* =====================================================
     | GATEWAY REFERENCES
     ===================================================== */
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_signature')->nullable();

            /* =====================================================
     | IDEMPOTENCY & RETRIES
     ===================================================== */
            $table->unsignedInteger('attempt_no')->default(1);
            $table->boolean('is_final')->default(false);          // webhook-confirmed

            /* =====================================================
        | FAILURE / REFUND INFO
        ===================================================== */
            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();

            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->boolean('is_refunded')->default(false);

            /* =====================================================
            | AUDIT / META
            ===================================================== */
            $table->json('meta')->nullable();                     // webhook payload, notes
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
