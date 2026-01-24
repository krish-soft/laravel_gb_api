<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mst_finance_settings', function (Blueprint $table) {
            $table->id();

            $table->string('setting_code', 20)->unique(); // To Pick First not base on id

            // Currency & formatting
            $table->string('currency')->default('INR');
            $table->string('currency_symbol', 10)->default('₹');
            $table->string('currency_position', 10)->default('left');

            $table->string('thousand_separator')->default(',')->nullable();
            $table->string('decimal_separator')->default('.')->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2)->nullable();


            $table->boolean('is_financial_year_logic_enabled')->default(false)->nullable();

            // Financial year reference
            $table->foreignId('financial_year_id')->nullable()
                ->constrained('mst_financial_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_finance_settings');
    }
};
