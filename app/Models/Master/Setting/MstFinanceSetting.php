<?php

namespace App\Models\Master\Setting;

use App\Models\BaseModel;
use App\Models\Master\MstFinancialYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MstFinanceSetting extends BaseModel
{
    //

    protected static function booted()
    {
        static::deleting(function () {
            throw new \RuntimeException('Finance settings cannot be deleted.');
        });


        static::saved(function () {
            Cache::forget('current_fy_id'); //

            try {
                if (Schema::hasTable('mst_finance_settings') && Cache::has('mst_finance_settings')) {
                    Cache::forget('mst_finance_settings');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });

        static::updated(function () {
            if (Schema::hasTable('mst_finance_settings') && Cache::has('mst_finance_settings')) {
                Cache::forget('mst_finance_settings');
            }
        });
    }

    protected $fillable = [
        'setting_code',

        'currency',
        'currency_symbol',

        'currency_position',
        'thousand_separator',
        'decimal_separator',
        'decimal_places',

        'is_financial_year_logic_enabled',
        'financial_year_id',
    ];


    // Casts
    protected $casts = [
        'decimal_places' => 'integer',
        'is_financial_year_logic_enabled' => 'boolean',
    ];

    // Relationships
    public function financialYear()
    {
        return $this->belongsTo(MstFinancialYear::class, 'financial_year_id');
    }

    // Default settings

    public static function getOrCreate()
    {
        if (!Schema::hasTable('mst_finance_settings')) {
            return null;
        }

        // return Cache::rememberForever('mst_finance_settings', function () {
        return self::firstOrCreate([
            // Default values
            'setting_code' => 'SETTING_001',

        ], [
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'currency_position' => 'left',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
            'decimal_places' => 2,
            'financial_year_id' => MstFinancialYear::currentFinancialYear()->id ?? null,
        ]);
        // });
    }

    // Create Helper function to clear cache after save
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

    public static function currencyPosition(): string
    {
        return self::getOrCreate()->currency_position
            ?? 'left';
    }

    public static function decimalPlaces(): int
    {
        return self::getOrCreate()->decimal_places
            ?? 2;
    }

    public static function thousandSeparator(): string
    {
        return self::getOrCreate()->thousand_separator
            ?? ',';
    }

    public static function decimalSeparator(): string
    {
        return self::getOrCreate()->decimal_separator
            ?? '.';
    }


    public static function appFinancialYearId(): ?int
    {
        return self::getOrCreate()->financial_year_id
            ?? null;
    }


    public static function isFinancialYearEnabled()
    {
        return self::getOrCreate()->is_financial_year_logic_enabled ?? false;
    }
}
