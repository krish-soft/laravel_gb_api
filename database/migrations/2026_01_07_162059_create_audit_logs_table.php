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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who did the action
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->nullOnDelete();

            // What was changed
            $table->string('auditable_type', 100); // Model class name
            $table->unsignedBigInteger('auditable_id');

            // Action type
            $table->string('action', 20);
            // created | updated | deleted | verified | approved | rejected

            // Change tracking
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Optional reason
            $table->string('reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
