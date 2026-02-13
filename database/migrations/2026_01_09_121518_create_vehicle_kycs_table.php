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
        Schema::create('vehicle_kycs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('mst_vehicle_id')
                ->nullable()
                ->constrained('mst_vehicles')
                ->nullOnDelete();


            $table->string('user_code', 20)->nullable();
            $table->string('picture')->nullable();

            // Reference
            $table->string('vehicle_kyc_code', 20)->unique();

            $table->string('license_plate_number', 30);
            $table->string('driving_license_number', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('insurance_policy_number', 100)->nullable();

            $table->string('vehicle_color', 30)->nullable();


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

            $table->string('remarks', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_kycs');
    }
};
