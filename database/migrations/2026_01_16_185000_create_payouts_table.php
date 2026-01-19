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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            $table->string('payout_code')->unique(); // PTO-2024-00001

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_bank_id');

            $table->decimal('amount', 12, 2);

            // Razorpay
            $table->string('razorpay_payout_id')->nullable();

            // Status
            $table->string('status'); // requested, approved, processing, paid, failed, rejected

            // Security
            $table->string('requested_by')->nullable(); // system or user
            $table->string('requested_ip')->nullable();

            $table->string('approved_by')->nullable(); // admin user who approved
            $table->timestamp('approved_at')->nullable();

            $table->text('remark')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_payouts');
    }
};
