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
        Schema::create('mst_app_settings', function (Blueprint $table) {
            $table->id();

            // App identity

            $table->string('setting_code', 20)->unique(); // To Pick First not base on id

            $table->string('app_name')->default('Green Bazar')->nullable();

            // Localization
            $table->string('timezone')->default('Asia/Kolkata')->nullable();
            $table->string('locale')->default('en');
            $table->string('fallback_locale')->default('en')->nullable();


            $table->string('date_format')->default('Y-m-d')->nullable();
            $table->string('time_format')->default('H:i')->nullable();

            $table->date('db_cut_off_date')->nullable(); //

            // App behavior
            $table->boolean('is_maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();

            // UI / frontend
            $table->boolean('is_registration_enabled')->default(true);

            // Meta
            $table->string('web_app_version', 10)->nullable();
            $table->string('mobile_app_version', 10)->nullable();

            // Mobile app versioning
            $table->string('mobile_app_android_version', 10)->nullable();
            $table->boolean('is_force_app_android_update')->default(false);

            $table->string('driver_mobile_app_android_version', 10)->nullable();
            $table->boolean('is_force_driver_app_android_update')->default(false);

            $table->string('mobile_app_ios_version', 10)->nullable();
            $table->boolean('is_force_app_ios_update')->default(false);
      
            $table->string('driver_mobile_app_ios_version', 10)->nullable();
            $table->boolean('is_force_driver_app_ios_update')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_app_settings');
    }
};
