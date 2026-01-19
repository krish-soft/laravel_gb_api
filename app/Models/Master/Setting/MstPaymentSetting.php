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


        static::saved(function () {
            try {
                if( Schema::hasTable('mst_payment_settings') && Cache::has('mst_payment_settings')) {
                    Cache::forget('mst_payment_settings');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });

    }

    protected $fillable = [
        'payment_in_mode',
        'payment_out_mode',

        'min_payout',
        'max_payout',

        'min_cart_order',
        'max_cart_order',

        'payout_cycle',
        'refund_window_days' .

        'max_payment_attempts',
        'cart_expiry_minutes',

    ];

    // casts
    protected $casts = [
        'min_payout' => 'decimal:2',
        'max_payout' => 'decimal:2',
        'min_cart_order' => 'decimal:2',
        'max_cart_order' => 'decimal:2',
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
                'payment_in_mode' => PaymentMethodEnum::RAZORPAY->value,
                'payment_out_mode' => PaymentMethodEnum::MANUAL->value,
                'min_cart_order' => 2500,
                'max_cart_order' => 15000
            ], [
                'min_payout' => 100,
                'max_payout' => 15000,
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


    public static function minPayOut(): int
    {
        $settings = self::getOrCreate();
        return $settings->min_payout;
    }

    public static function maxPayOut(): int
    {
        $settings = self::getOrCreate();
        return $settings->max_payout;
    }

    public static function minCartOrder(): int
    {
        $settings = self::getOrCreate();
        return $settings->min_cart_order;
    }

    public static function maxCartOrder(): int
    {
        $settings = self::getOrCreate();
        return $settings->max_cart_order;
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
