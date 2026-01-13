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
        Schema::create('mst_charges', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('code', 50); // Enum values from ChargesEnum

            $table->string('description')->nullable();

            // GST applicability
            $table->boolean('is_taxable')->default(false);

            // GST slabs
            $table->decimal('cgst_percent', 8, 2)->default(0);
            $table->decimal('sgst_percent', 8, 2)->default(0);
            $table->decimal('utgst_percent', 8, 2)->default(0);
            $table->decimal('igst_percent', 8, 2)->default(0);

            // State rule (NULL = applicable for all states)
            $table->json('applicable_state_code')->nullable(); // e.g. GJ, MH

            $table->boolean('is_active')->default(true)->nullable();


            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'deleted_at']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_charges');
    }
};
