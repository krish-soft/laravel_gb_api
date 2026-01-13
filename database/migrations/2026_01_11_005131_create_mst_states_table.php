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
        Schema::create('mst_states', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150)->unique();
            $table->string('iso_code', 10)->unique();
            $table->string('language', 10)->nullable();
            $table->string('type', 10)->nullable();        // Legal necessity

            $table->boolean('is_active')->default(true)->nullable();
            $table->boolean('is_ut')->default(false)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_states');
    }
};
