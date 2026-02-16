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
        Schema::create('mst_depots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('zone_id')->nullable()->constrained('mst_zones')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('market_id')->nullable()->constrained('mst_markets')->cascadeOnUpdate()->restrictOnDelete();

            $table->string('picture')->nullable();

            $table->string('name', 150);
            $table->string('code', 50);

            // Location
            $table->string('addr_code', 50)->nullable();

            // Capacity
            $table->integer('max_capacity_kg')->nullable();
            $table->integer('current_load_kg')->default(0)->nullable();


            // Cutoff times (PROFESSIONAL)
            $table->time('buyer_cutoff_time')->nullable();   // inbound
            $table->time('seller_cutoff_time')->nullable(); // outbound

            // Primary contact
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 100)->nullable();

            $table->boolean('is_active')->default(true)->nullable();

            $table->text('notes')->nullable();

            $table->string('custchar1', 100)->nullable();
            $table->string('custchar2', 100)->nullable();

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
        Schema::dropIfExists('mst_depots');
    }
};
