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
        Schema::create('mst_zones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('state_id')->nullable()->constrained('mst_states')->cascadeOnUpdate()->nullOnDelete();

            $table->string('picture')->nullable();

            $table->string('name', 150);
            $table->string('code', 50);

            $table->boolean('is_active')->default(true)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_zones');
    }
};
