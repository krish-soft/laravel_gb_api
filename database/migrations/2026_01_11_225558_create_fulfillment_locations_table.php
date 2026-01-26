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
        Schema::create('fulfillment_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('picture')->nullable();
            $table->string('fl_code', 20); //  Address Code (shipping / pickup / return / holding)

            $table->string('name');
            $table->string('type', 30);

            $table->string('addr_code', 50)->nullable(); //  Address Code (shipping / pickup / return / holding)

            // Status & control
            $table->boolean('is_active')->default(true)->nullable();
            $table->string('inactive_reason', 100)->nullable();


            // Verification audit
            $table->string('status', 20)->default('pending')->nullable();    // pending | needs_update | verified | expired
            $table->string('verification_mode', 20)->nullable();    // manual | system
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by', 100)->nullable();
            $table->foreignId('verified_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('review_comment')->nullable();


            // Optional meta
            $table->string('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fl_code', 'deleted_at']);
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_locations');
    }
};
