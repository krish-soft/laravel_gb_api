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
        Schema::create('mst_seq_code_generators', function (Blueprint $table) {
            $table->id();
            // Generate All Kind Of Sequnce
            $table->unsignedBigInteger('seq_no')->default(0); // General Sequence
            $table->unsignedBigInteger('ref_no')->default(0); // General Sequence

            $table->unsignedBigInteger('order_no')->default(0); // For Orders
            $table->unsignedBigInteger('invoice_no')->default(0); // For Invoices
            $table->unsignedBigInteger('market_order_no')->default(0); // For Orders
            $table->unsignedBigInteger('doc_no')->default(0); // For any documents

            $table->unsignedBigInteger('rule_no')->default(0); // for pricing rules
            $table->unsignedBigInteger('other_no')->default(0); // for other uses

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_seq_code_generators');
    }
};
