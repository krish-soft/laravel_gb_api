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
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('finance_year_id')->nullable();

            $table->string('batch_no', 20)->unique();
            $table->date('batch_date');
            $table->date('cutoff_date');

            $table->string('remarks')->nullable();

            $table->string('status', 30)->default('pending'); // pending, processing, completed, failed

            $table->decimal('total_credit', 15, 2)->default(0);
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_batches');
    }
};
