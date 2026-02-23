<?php

namespace App\Models\Master\Setting;

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MstAppSetting extends BaseModel
{
    use SoftDeletes;
    /**
     * Prevent deletion + auto clear cache
     */
    protected static function booted()
    {
        static::deleting(function () {
            throw new \Exception('App settings cannot be deleted.');
        });

        static::saving(function () {
            Cache::forget('mst_app_settings');
        });

        static::updated(function () {
            if (Schema::hasTable('mst_app_settings') && Cache::has('mst_app_settings')) {
                Cache::forget('mst_app_settings');
            }
        });
    }

    protected $fillable = [

        // App identity
        'setting_code',
        'app_name',

        // Localization
        'timezone',
        'locale',
        'fallback_locale',

        // Date & time
        'date_format',
        'time_format',

        // App behavior
        'is_maintenance_mode',
        'maintenance_message',

        // UI
        'is_registration_enabled',

        // Versioning
        'app_version',
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
        'min_payout_amount' => 'float',
    ];

    protected $hidden = [
        'setting_code'
    ];

    /* =====================================================
     | SINGLE SOURCE OF TRUTH
     =====================================================*/
    public static function getOrCreate(): Model
    {
        return Cache::rememberForever('mst_app_settings', function () {
            return MstAppSetting::firstOrCreate(
                [
                    'app_name' => 'Green Bazar',
                    'setting_code' => 'SETTING_001'
                ],
                [
                    'timezone' => 'Asia/Kolkata',
                    'locale' => 'en',
                    'fallback_locale' => 'en',


                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i',

                    'is_maintenance_mode' => false,
                    'maintenance_message' => null,
                    'is_registration_enabled' => true,

                    'app_version' => '1.0.0',
                    'mobile_app_android_version' => '1.0.0',
                    'is_force_app_android_update' => false,
                    'mobile_app_ios_version' => '1.0.0',
                    'is_force_app_ios_update' => false,
                ]
            );
        });
    }




    public static function isMaintenanceMode(): bool
    {
        return (bool)(
            self::getOrCreate()->is_maintenance_mode
            ?? false
        );
    }

    public static function getMaintenanceMessage(): ?string
    {
        return self::getOrCreate()->maintenance_message;
    }


    public static function isForceAndroidUpdate(): bool
    {
        return (bool)(
            self::getOrCreate()->is_force_app_android_update
            ?? false
        );
    }


    public static function getAndroidAppVersion(): ?string
    {
        return self::getOrCreate()->mobile_app_android_version;
    }


    public static function isForceIosUpdate(): bool
    {
        return (bool)(
            self::getOrCreate()->is_force_app_ios_update
            ?? false
        );
    }
}
