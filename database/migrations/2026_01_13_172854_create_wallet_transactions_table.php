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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            $table->string('user_code', 50)->nullable(); // optional user code

            $table->string('wallet_txn_code', 50)->unique();

            $table->decimal('amount', 15, 2); //

            $table->string('type', 50); // e.g., 'credit', 'debit' / in,out
            $table->string('status', 50); // eg., 'pending', 'completed', 'cancelled','hold'
            $table->string('description',150)->nullable();

            // Reference class
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code', 50)->nullable();

            // Internal like order or other reference
            $table->string('reference',150)->nullable();


            // Payment Gateway Related or any ref number to store
            $table->string('payment_reference',150)->nullable();
            $table->string('gateway', 50)->nullable();


            $table->string('remark')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'source_id']);
            $table->index(['wallet_id', 'status']);
            $table->index('wallet_txn_code');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
