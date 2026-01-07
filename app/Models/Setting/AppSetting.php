<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    //

    /**
     * Auto clear cache when settings change
     */
    protected static function booted()
    {
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
        'date_format',
        'time_format',

        // App behavior
        'maintenance_mode',
        'maintenance_message',

        // UI / frontend
        'registration_enabled',
        'debug_enabled',

        // Meta
        'app_version',
        'mobile_app_version',
    ];
}
