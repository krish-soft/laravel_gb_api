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
        Schema::create('product_listing_invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_listing_id')
                ->nullable()
                ->constrained('product_listings')
                ->cascadeOnDelete();

            $table->string('invoice_number', 20)->unique();
            $table->date('invoice_date'); // what is order date that time invoice generated

            $table->string('invoice_path')->nullable(); // file path of invoice

            $table->string('status', 20)->default('generated')->nullable();

            $table->tinyInteger('count')->default(0)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_listing_invoices');
    }
};
