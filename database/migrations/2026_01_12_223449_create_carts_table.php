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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Buyer
            $table->foreignId('buyer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Fulfillment (derived from listing, locked after first item)
            $table->foreignId('fulfillment_location_id')
                ->nullable()
                ->constrained('fulfillment_locations')
                ->restrictOnDelete();

            $table->uuid('cart_uuid')->unique();

            $table->string('status', 30);

            $table->json('meta')->nullable(); // like last preview data

            $table->timestamp('locked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['buyer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
