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

            $table->string('picture', 20)->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('mst_vehicle_id')
                ->nullable(); // table is not created yet, so we will add foreign key constraint later in a separate migration after the mst_vehicles table is created
            // ->constrained('mst_vehicles')
            // ->nullOnDelete();

            $table->string('user_code', 20)->nullable();

            // Reference
            $table->string('vehicle_kyc_code', 20)->unique();

            $table->string('license_plate_number', 30);
            $table->string('driving_license_number', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('insurance_policy_number', 100)->nullable();

            $table->string('vehicle_maker', 30)->nullable();     // Tata | Hyundai | Mahindra
            $table->string('vehicle_model', 30)->nullable();     // Ace | i20 | Bolero
            $table->string('vehicle_type', 20)->nullable();      // bike | car | truck | tempo | van
            $table->string('vehicle_color', 30)->nullable();
            $table->string('vehicle_fuel_type', 30)->nullable();
            $table->string('vehicle_category', 30)->nullable();   // goods | passenger

            $table->unsignedInteger('seating_capacity')->nullable();
            $table->unsignedInteger('load_capacity_kg')->nullable();
            $table->unsignedInteger('engine_cc')->nullable();
            $table->string('transmission_type', 20)->nullable();  // manual | automatic
            $table->string('vehicle_condition', 20)->nullable();  // good | average | poor

            $table->string('chassis_number', 50)->nullable();
            $table->string('engine_number', 50)->nullable();
            $table->string('vehicle_unique_mark', 150)->nullable(); // dents / stickers
            $table->string('vehicle_branding', 100)->nullable();    // company logo

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
