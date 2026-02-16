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
        Schema::create('market_order_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('market_order_id')
                ->constrained('market_orders')
                ->cascadeOnDelete();

            $table->string('order_number', 20)->nullable();

            $table->string('document_type', 30); // invoice, packing_slip, etc.
            $table->string('document_path'); // URL to the stored document

            $table->string('reference', 100)->nullable(); // internal reference

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
        Schema::dropIfExists('market_order_documents');
    }
};
