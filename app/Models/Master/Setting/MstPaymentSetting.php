<?php

namespace App\Models\Master\Setting;

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MstPaymentSetting extends BaseModel
{
    //

    protected static function booted()
    {
        static::deleting(function () {
            throw new \Exception('Payment settings cannot be deleted.');
        });

        static::updated(function () {
            if (Schema::hasTable('mst_payment_settings') && Cache::has('mst_payment_settings')) {
                Cache::forget('mst_payment_settings');
            }
        });

        static::saved(function () {
            try {
                if (Schema::hasTable('mst_payment_settings') && Cache::has('mst_payment_settings')) {
                    Cache::forget('mst_payment_settings');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });
    }

    protected $fillable = [
        'setting_code',

        'payment_in_mode',
        'payment_out_mode',

        'min_payout_amount',
        'max_payout_amount',
        'payout_cycle',

        'min_cart_order_amount',
        'max_cart_order_amount',
        'max_payment_attempts',
        'cart_expiry_minutes',


        'refund_window_days',

    ];

    // casts
    protected $casts = [
        'min_payout_amount' => 'decimal:2',
        'max_payout_amount' => 'decimal:2',
        'min_cart_order_amount' => 'decimal:2',
        'max_cart_order_amount' => 'decimal:2',
        'refund_window_days' => 'integer',
        'max_payment_attempts' => 'integer',
        'cart_expiry_minutes' => 'integer',
    ];

    // Default settings
    public static function getOrCreate()
    {
        if (!Schema::hasTable('mst_payment_settings')) {
            return null;
        }

        return Cache::rememberForever('mst_payment_settings', function () {
            return self::firstOrCreate([
                'setting_code' => 'SETTING_001',
            ], [
                'payment_in_mode' => PaymentMethodEnum::RAZORPAY->value,
                'payment_out_mode' => PaymentMethodEnum::MANUAL->value,
                'min_cart_order_amount' => 2500,
                'max_cart_order_amount' => 15000,
                'min_payout_amount' => 100,
                'max_payout_amount' => 15000,
                'payout_cycle' => 'weekly',
                'refund_window_days' => 7,
                'max_payment_attempts' => 2,
                'cart_expiry_minutes' => 120, // 2 hours

            ]);
        });
    }

    // Create Helper function to clear cache after save

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


    public static function minPayOutAmount(): int
    {
        $settings = self::getOrCreate();
        return $settings->min_payout_amount;
    }

    public static function maxPayOutAmount(): int
    {
        $settings = self::getOrCreate();
        return $settings->max_payout_amount;
    }

    public static function minCartOrderAmount(): int
    {
        return  self::getOrCreate()?->min_cart_order_amount ?? 2500;
    }

    public static function maxCartOrderAmount(): int
    {
        return  self::getOrCreate()?->max_cart_order_amount ?? 15000;
    }

    public static function payoutCycle(): string
    {
        $settings = self::getOrCreate();
        return $settings->payout_cycle;
    }

    public static function refundWindowDays(): int
    {
        $settings = self::getOrCreate();
        return $settings->refund_window_days;
    }

    public static function maxPaymentAttempts(): int
    {
        $settings = self::getOrCreate();
        return $settings->max_payment_attempts;
    }

    public static function cartExpiryMinutes(): int
    {
        $settings = self::getOrCreate();
        return $settings->cart_expiry_minutes;
    }


    //
}
