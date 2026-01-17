<?php

namespace App\Models\Setting;

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends BaseModel
{
    /**
     * Prevent deletion + auto clear cache
     */
    protected static function booted()
    {
        static::deleting(function () {
            throw new \Exception('App settings cannot be deleted.');
        });

        static::saved(fn() => Cache::forget('app_settings'));
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

        // Payment modes
        'payment_in_mode',
        'payment_out_mode',
        'min_payout',

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
        'min_payout' => 'float',
    ];

    /* =====================================================
     | SINGLE SOURCE OF TRUTH
     =====================================================*/
    public static function getOrCreate(): Model
    {
        return Cache::rememberForever('app_settings', function () {
            return self::firstOrCreate(
                ['app_name' => 'Green Bazar'],
                [
                    'timezone' => 'Asia/Kolkata',
                    'locale' => 'en',
                    'fallback_locale' => 'en',

                    'currency' => 'INR',
                    'currency_symbol' => '₹',

                    'payment_in_mode' => PaymentMethodEnum::RAZORPAY->value,
                    'payment_out_mode' => PaymentMethodEnum::MANUAL->value,
                    'min_payout' => 100,

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

    /* =====================================================
     | CONVENIENCE GETTERS (SAFE EVERYWHERE)
     =====================================================*/

    /* =====================================================
 | SAFE GETTERS (NEVER RETURN NULL)
 =====================================================*/

    public static function currency(): string
    {
        return self::getOrCreate()->currency
            ?? 'INR';
    }

    public static function currencySymbol(): string
    {
        return self::getOrCreate()->currency_symbol
            ?? '₹';
    }

    public static function payInMode(): string
    {
        return self::getOrCreate()->payment_in_mode
            ?? PaymentMethodEnum::RAZORPAY->value;
    }

    public static function payOutMode(): string
    {
        return self::getOrCreate()->payment_out_mode
            ?? PaymentMethodEnum::MANUAL->value;
    }

    public static function minPayoutAmount(): float
    {
        return (float) (
            self::getOrCreate()->min_payout
            ?? 100
        );
    }

    public static function isMaintenanceMode(): bool
    {
        return (bool) (
            self::getOrCreate()->is_maintenance_mode
            ?? false
        );
    }
}
