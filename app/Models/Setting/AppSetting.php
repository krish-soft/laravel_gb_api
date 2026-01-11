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


    protected $casts = [
        'is_maintenance_mode' => 'boolean',
        'is_registration_enabled' => 'boolean',
        'is_force_app_android_update' => 'boolean',
        'is_force_app_ios_update' => 'boolean',
    ];


    // Create functiosn to check all

    public function isMaintenanceMode(): bool
    {
        return $this->is_maintenance_mode;
    }

    public function getMaintenanceMessage(): ?string
    {
        return $this->maintenance_message;
    }

    public function getAndroidAppVersion(): ?string
    {
        return $this->mobile_app_android_version;
    }

    public function isForceAndroidUpdate(): bool
    {
        // Null or empty means no force update
        if (!$this->is_force_app_android_update || $this->mobile_app_android_version === null || $this->mobile_app_android_version === '') {
            return false;
        }

        return $this->is_force_app_android_update;
    }


    public function getIosAppVersion(): ?string
    {
        return $this->mobile_app_ios_version;
    }

    public function isForceIosUpdate(): bool
    {
        // Null or empty means no force update
        if (!$this->is_force_app_ios_update || $this->mobile_app_ios_version === null || $this->mobile_app_ios_version === '') {
            return false;
        }

        return $this->is_force_app_ios_update;
    }
}
