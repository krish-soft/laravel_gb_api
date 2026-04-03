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
        Schema::create('mst_cutoff_settings', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique();   // inbound
            $table->time('buyer_start_time')->nullable();   // inbound
            $table->time('buyer_end_time')->nullable(); // outbound

            $table->time('seller_start_time')->nullable();   // inbound
            $table->time('seller_end_time')->nullable(); // outbound

            $table->boolean('is_buyer_auto_cutoff')->default(false)->nullable();
            $table->boolean('is_seller_auto_cutoff')->default(false)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_cutoff_settings');
    }
};
