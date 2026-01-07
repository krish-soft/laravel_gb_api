<?php

namespace Database\Seeders\Setting;

use App\Models\Setting\AppSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        AppSetting::create([
            // App identity
            'app_name' => 'Green Bazar',

            // Localization
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'fallback_locale' => 'en',

            // Formatting
            'currency' => 'INR',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',

            // App behavior
            'maintenance_mode' => false,
            'maintenance_message' => null,

            // UI / frontend
            'registration_enabled' => true,
            'debug_enabled' => false,

            // Meta
            'app_version' => '1.0.0',
            'mobile_app_version' => '1.0.0',
        ]);
    }
}
