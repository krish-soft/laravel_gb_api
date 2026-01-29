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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->string('accnt_code', 20)->unique();
            $table->string('name', 80)->nullable();

            $table->string('owner_type', 50);   // SELLER / DRIVER / PLATFORM / GOVERNMENT
            $table->unsignedBigInteger('owner_id')->nullable();

            $table->string('type', 50)->nullable();   // e.g., MAIN, TAX, REVNUE, CLEARING, EXPENSE

            // 🔴 SNAPSHOT COLUMNS
            $table->decimal('available_balance', 15, 2)->default(0.00); // ready to payout / usable
            $table->decimal('hold_balance', 15, 2)->default(0.00);      // earned but not yet released

            $table->decimal('total_credit', 15, 2)->default(0.00);
            $table->decimal('total_debit', 15, 2)->default(0.00);

            $table->string('currency', 3)->default('INR');

            $table->boolean('is_active')->default(true)->nullable();
            $table->string('inactive_reason')->nullable();

            $table->string('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_type', 'owner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
