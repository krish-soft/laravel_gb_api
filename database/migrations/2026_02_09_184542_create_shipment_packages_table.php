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
        Schema::create('shipment_packages', function (Blueprint $table) {
            $table->id();

            // Core relations (DENORMALIZED for fast access)
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('order_number', 20)->nullable();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            // Direct access (avoid joining orders/users every time)
            $table->foreignId('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('seller_id')
                ->constrained('users')
                ->restrictOnDelete();

            // One row = one physical package
            $table->unsignedInteger('qty')->default(1);

            // Package content
            $table->decimal('pack_size', 10, 2)->nullable();
            $table->string('pack_unit', 20)->nullable();
            $table->string('pack_type_unit', 50)->nullable();

            // Identifiers
            $table->string('shipment_number', 20)->unique(); // HARD unique label
            $table->string('package_number', 30); // A-1, AA-2 etc.

            // Status flow
            $table->string('status', 50)->default('pending');
            // pending | packed | ready_for_pickup | shipped | delivered | returned

            // Courier
            $table->string('carrier', 50)->nullable();
            $table->string('tracking_number', 50)->nullable();

            $table->string('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes ONLY (NOT unique)
            $table->index(['order_id']);
            $table->index(['buyer_id', 'package_number']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_packages');
    }
};
