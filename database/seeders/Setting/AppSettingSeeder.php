<?php

namespace Database\Seeders\Setting;

use App\Models\Master\Setting\MstAppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        MstAppSetting::create([
            // App identity

            'setting_code' => 'SETTING_001',
            'app_name' => 'Green Bazar',

            // Localization
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'fallback_locale' => 'en',

            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',

            // App behavior
            'is_maintenance_mode' => false,
            'maintenance_message' => null,

            // UI / frontend
            'is_registration_enabled' => true,


            // Meta
            'app_version' => '1.0.0',
            // Mobile app versioning
            'mobile_app_android_version' => '1.0.0',
            'is_force_app_android_update' => false,

            'mobile_app_ios_version' => '1.0.0',
            'is_force_app_ios_update' => false,
        ]);
    }
}
