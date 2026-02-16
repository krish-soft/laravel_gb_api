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
            $table->unsignedBigInteger('origin_flmnt_location_id')->nullable(); // origin entity id // alwaays this
            $table->unsignedBigInteger('origin_depot_id')->nullable(); // optional link to depot for easier querying

            $table->string('destination_type', 50); // fulfillment_location | user
            $table->unsignedBigInteger('destination_flmnt_location_id')->nullable(); // destination entity id
            $table->unsignedBigInteger('destination_depot_id')->nullable(); // optional link to depot for easier querying

            $table->unsignedBigInteger('market_id')->nullable(); // optional link to market for easier querying



            $table->string('status', 30)->default('pending')->index(); // pending | grouped | assigned | in_transit | completed | cancelled

            $table->text('remarks')->nullable(); // internal note

            $table->timestamps();
            $table->softDeletes();

            $table->index(['origin_type', 'origin_flmnt_location_id']); // fast origin filter
            $table->index(['destination_type', 'destination_flmnt_location_id']); // fast destination filter
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
