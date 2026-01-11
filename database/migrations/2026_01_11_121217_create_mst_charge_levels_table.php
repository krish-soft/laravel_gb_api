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
        Schema::create('mst_charge_levels', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 50);

            $table->string('description')->nullable();
            $table->string('user_role_type', 50)->nullable(); // Buyer/seller/Delivery

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
        Schema::dropIfExists('mst_charge_levels');
    }
};
