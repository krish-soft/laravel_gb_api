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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            // WHO owes
            $table->morphs('from_entity'); // user / delivery / platform

            // WHO is owed
            $table->morphs('to_entity');   // user / delivery / platform

            $table->decimal('amount', 15, 2);

            // Why this exists
            $table->string('reason', 100); // order_payout, refund, penalty, commission

            // Source reference
            $table->string('source_type'); // Order, OrderItem, Payout, Dispute
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code', 50)->nullable();

            // Status lifecycle
            $table->string('status', 30);
            // pending | settled | cancelled | disputed

            // When money actually moved
            $table->timestamp('settled_at')->nullable();

            // Link to actual money movement
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
