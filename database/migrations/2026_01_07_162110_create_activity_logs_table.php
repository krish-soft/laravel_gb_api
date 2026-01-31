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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // What happened
            $table->string('event', 100);

            /* ===================== ACTOR ===================== */
            // Who performed the action
            $table->string('actor_type', 50)->nullable();  // user | admin | system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_code', 100)->nullable();

            /* ===================== SUBJECT ===================== */
            // What / whose data was affected
            $table->string('subject_type')->nullable(); // User | UserKyc | Order 
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_code', 100)->nullable();


            // Parent / related entity (if any)
            $table->string('related_type')->nullable()->index();
            $table->unsignedBigInteger('related_id')->nullable()->index();


            /* ===================== EXTRA CONTEXT ===================== */
            $table->json('meta')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('user_group', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['event']);
            $table->index(['actor_type', 'actor_id']);
            $table->index(['subject_type', 'subject_id']);
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
