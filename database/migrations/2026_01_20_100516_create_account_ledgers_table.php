<?php

use App\Enum\Accounting\LedgerStatusEnum;
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
        Schema::create('account_ledgers', function (Blueprint $table) {
            $table->id();

            // Owner account (seller / driver / platform / govt)
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('ledger_txn_code', 100)->unique();
            $table->date('ledger_date')->nullable();

            // Financial year
            $table->unsignedBigInteger('finance_year_id')->nullable();

            $table->text('description')->nullable();

            // Money movement (only one side non-zero)
            $table->decimal('credit', 15, 2)->default(0.00);
            $table->decimal('debit', 15, 2)->default(0.00);

            // Business meaning (free text, future-safe)
            $table->string('entry_type', 100)->nullable();

            // Reference
            $table->string('source_type')->nullable(); // ORDER / DELIVERY / PAYMENT / SYSTEM
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code', 50)->nullable();

            // for payment payouts
            $table->string('reference', 100)->nullable(); // intenal
            $table->string('payment_reference', 100)->nullable(); //
            $table->string('common_reference', 100)->nullable(); // intenal
            $table->string('other_reference', 100)->nullable(); // intenal

            // Recovery / adjustment linking
            $table->unsignedBigInteger('parent_ledger_id')->nullable();


            $table->string('status', 20)->default(LedgerStatusEnum::PENDING->value)->nullable();
            // Settlement metadata (only when SETTLED)
            $table->timestamp('settled_at')->nullable();

            // Tax flag (excluded from seller/driver payable)
            $table->boolean('is_tax')->default(false)->nullable();
            $table->boolean('is_open_balance')->default(false)->nullable();

            $table->string('remarks')->nullable();

            $table->string('settlement_batch_no', 20)->nullable();
            $table->unsignedBigInteger('settlement_batch_id')->nullable();



            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'finance_year_id']);
            $table->index(['status', 'is_tax']);
            $table->index('parent_ledger_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_ledgers');
    }
};
