<?php

namespace App\Models\Master\Setting;

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MstAppSetting extends BaseModel
{
    /**
     * Prevent deletion + auto clear cache
     */
    protected static function booted()
    {
        static::deleting(function () {
            throw new \Exception('App settings cannot be deleted.');
        });


        static::saved(function () {
            try {
                if (Cache::has('mst_app_settings')) {
                    Cache::forget('mst_app_settings');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });

    }

    protected $fillable = [

        // App identity
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

    /* =====================================================
     | SINGLE SOURCE OF TRUTH
     =====================================================*/
    public static function getOrCreate(): Model
    {
        return Cache::rememberForever('mst_app_settings', function () {
            return self::firstOrCreate(
                ['app_name' => 'Green Bazar'],
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
}
