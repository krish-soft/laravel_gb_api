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
        Schema::create('activity_logs', function (Blueprint $table) { // org
            // Schema::connection('log_mysql')->create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->string('user_code', 20)->nullable();

            $table->string('event', 100);
            // login | logout | place_order | cancel_order | upload_document

            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->json('meta')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('user_group', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        // Schema::connection('log_mysql')->dropIfExists('activity_logs');
    }
};
