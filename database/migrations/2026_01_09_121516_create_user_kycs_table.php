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
        Schema::create('user_kycs', function (Blueprint $table) {
            $table->id();

            // Keep history even if user deleted
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('user_code', 20)->nullable();
            $table->string('picture')->nullable();

            // Reference
            $table->string('kyc_code', 20)->unique();

            // Declared identity
            $table->string('legal_name');
            $table->string('other_legal_name')->nullable();

            // Parental names
            $table->string('father_name', 100)->nullable();
            $table->string('mother_name', 100)->nullable();
            $table->string('other_name', 100)->nullable();

            // Masked identifiers
            $table->string('pan_card_number', 15)->nullable();
            $table->string('aadhaar_last4', 4)->nullable();
            $table->string('aadhaar_vid_last4', 4)->nullable();

            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();

            // Status
            $table->string('status', 20)->default('pending')->nullable();    // pending | needs_update | verified | expired

            // Verification audit
            $table->boolean('is_verified')->default(false)->nullable();
            $table->string('verification_mode', 20)->nullable();    // manual | system
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by', 100)->nullable();
            $table->foreignId('verified_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // USER-VISIBLE FEEDBACK (for re-upload)
            $table->text('review_comment')->nullable();
            // e.g. "PAN image is blurry, please re-upload"

            // Expiry (for yearly re-KYC)

            $table->boolean('is_expired')->default(false)->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->string('addr_code', 50)->nullable(); // Shipping Address Code

            $table->string('custchar1', 100)->nullable();
            $table->string('custchar2', 100)->nullable();

            $table->string('remarks', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // indexes
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_kycs');
    }
};
