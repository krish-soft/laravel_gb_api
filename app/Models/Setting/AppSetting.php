<?php

namespace App\Models\Setting;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends BaseModel
{
    //

    /**
     * Auto clear cache when settings change
     */
    protected static function booted()
    {
        static::deleting(function () {
            throw new \Exception('App settings cannot be deleted.');
        });

        static::saved(fn() => Cache::forget('app_settings'));
        static::deleted(fn() => Cache::forget('app_settings'));
    }

    protected $fillable = [

        // App identity
        'app_name',

        // Localization
        'timezone',
        'locale',
        'fallback_locale',

        // Formatting
        'currency',
        'currency_symbol',

        'date_format',
        'time_format',

        // App behavior
        'is_maintenance_mode',
        'maintenance_message',

        // UI / frontend
        'is_registration_enabled',

        // Meta
        'app_version', // Web app version

        // Mobile app versioning
        'mobile_app_android_version',
        'is_force_app_android_update',

        'mobile_app_ios_version',
        'is_force_app_ios_update',
    ];

    protected $guarded = [
        'timezone',
        'locale',
        'fallback_locale',
        'currency',
        'currency_symbol',
    ];

    protected $casts = [
        'is_maintenance_mode' => 'boolean',
        'is_registration_enabled' => 'boolean',
        'is_force_app_android_update' => 'boolean',
        'is_force_app_ios_update' => 'boolean',
    ];


    // Create functiosn to check all
    public function getOrCreate(): Model|null
    {
        return self::firstOrCreate([
            'app_name' => 'Green Bazar',

            // Localization
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'fallback_locale' => 'en',

            // Formatting
            'currency' => 'INR',
            'currency_symbol' => '₹',
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
