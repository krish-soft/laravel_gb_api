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
        Schema::create('settlement_account_ledgers', function (Blueprint $table) {
            $table->id();

            // unique number 

            $table->string('settlement_ledger_txn_code', 50)->unique();

            $table->foreignId('settlement_batch_id')
                ->constrained('settlement_batches')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('settlement_account_id')
                ->constrained('settlement_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('account_ledger_id')
                ->constrained('account_ledgers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('credit', 15, 2)->default(0.00);
            $table->decimal('debit', 15, 2)->default(0.00);
            

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_account_ledgers');
    }
};
