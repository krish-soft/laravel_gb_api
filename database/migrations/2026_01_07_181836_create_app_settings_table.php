<?php

use App\Enum\Common\Payment\PaymentMethodEnum;
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
            $table->string('currency_symbol', 10)->default('₹')->nullable();

            // Payment modes
            $table->string('payment_in_mode', 30)->default(PaymentMethodEnum::RAZORPAY->value)->nullable();
            $table->string('payment_out_mode', 30)->default(PaymentMethodEnum::MANUAL->value)->nullable();
            $table->decimal('min_payout', 10, 2)->default('100')->nullable();

            $table->string('date_format')->default('Y-m-d')->nullable();
            $table->string('time_format')->default('H:i')->nullable();

            // App behavior
            $table->boolean('is_maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();

            // UI / frontend
            $table->boolean('is_registration_enabled')->default(true);

            // Meta
            $table->string('app_version', 10)->nullable();

            // Mobile app versioning
            $table->string('mobile_app_android_version', 10)->nullable();
            $table->boolean('is_force_app_android_update')->default(false);

            $table->string('mobile_app_ios_version', 10)->nullable();
            $table->boolean('is_force_app_ios_update')->default(false);

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
