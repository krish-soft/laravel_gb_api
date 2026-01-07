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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();

            // App identity
            $table->string('app_name')->default('Green Bazar')->nullable();

            // Localization
            $table->string('timezone')->default('Asia/Kolkata')->nullable();
            $table->string('locale')->default('en');
            $table->string('fallback_locale')->default('en')->nullable();

            // Formatting
            $table->string('currency')->default('INR')->nullable();
            $table->string('date_format')->default('Y-m-d')->nullable();
            $table->string('time_format')->default('H:i')->nullable();

            // App behavior
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();

            // UI / frontend
            $table->boolean('registration_enabled')->default(true);
            $table->boolean('debug_enabled')->default(false);

            // Meta
            $table->string('app_version', 10)->nullable();
            $table->string('mobile_app_version', 10)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
