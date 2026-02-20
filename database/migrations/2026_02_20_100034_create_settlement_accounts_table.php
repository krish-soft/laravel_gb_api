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
        Schema::create('settlement_accounts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('settlement_batch_id')->nullable()
                ->constrained('settlement_batches')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unsignedBigInteger('finance_year_id')->nullable();

            $table->foreignId('user_account_id')
                ->nullable()
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('platform_account_id')
                ->nullable()
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unsignedBigInteger('payout_id')->nullable();

            $table->decimal('amount', 15, 2)->default(0.00);

            $table->string('status', 30)->default('pending'); // pending, processed, failed

            $table->string('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_accounts');
    }
};
