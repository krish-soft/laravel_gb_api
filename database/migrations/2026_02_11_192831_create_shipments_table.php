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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->string('shipment_number', 30)->unique(); // logical shipment code

            $table->string('shipment_type', 20)->index(); // pickup | dispatch | direct
            $table->date('shipment_date')->nullable()->index(); // planning date

            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete(); // dispatch owner
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete(); // pickup owner

            $table->string('origin_type', 50); // fulfillment_location | user
            $table->unsignedBigInteger('origin_id'); // origin entity id

            $table->string('destination_type', 50); // fulfillment_location | user
            $table->unsignedBigInteger('destination_id'); // destination entity id

            $table->string('status', 30)->default('pending')->index(); // pending | grouped | assigned | in_transit | completed | cancelled

            $table->text('remarks')->nullable(); // internal note

            $table->timestamps();
            $table->softDeletes();

            $table->index(['origin_type', 'origin_id']); // fast origin filter
            $table->index(['destination_type', 'destination_id']); // fast destination filter
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
