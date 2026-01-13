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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete(); // ✅ KEEP: link to users table
            $table->string('user_code', 50)->nullable()->index(); // ✅ KEEP: your business reference

            $table->string('wallet_number', 20)->unique(); // ✅ INDUSTRY STD: unique identifier like "WALLET123456"

            // balances
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('hold_balance', 15, 2)->default(0);

            // limits
            $table->decimal('credit_limit', 15, 2)->default(1000);        // unpaid allowed
            $table->decimal('daily_amount_limit', 15, 2)->default(5000);  // Need Approval if crossed from Admin Actual what is making

            // compliance
            $table->string('currency', 3)->default('INR');

            $table->boolean('is_active')->default(true);
            $table->string('inactive_reason',150)->nullable();

            // metadata
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamp('last_ledger_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index('wallet_number');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
