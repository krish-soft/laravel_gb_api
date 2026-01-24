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
            throw new \Exception('Finance settings cannot be deleted.');
        });


        static::saved(function () {
            try {
                if ( Schema::hasTable('mst_finance_settings') && Cache::has('mst_finance_settings')) {
                    Cache::forget('mst_finance_settings');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });

    }

    protected $fillable = [
        'currency',
        'currency_symbol',

        'currency_position',
        'thousand_separator',
        'decimal_separator',
        'decimal_places',

        'financial_year_id',
    ];


    // Casts
    protected $casts = [
        'decimal_places' => 'integer',
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

        return Cache::rememberForever('mst_finance_settings', function () {
            return self::firstOrCreate([
                // Default values
                'currency' => 'INR',
                'currency_symbol' => '₹',
            ], [
                'currency_position' => 'left',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
                'decimal_places' => 2,
                'financial_year_id' => MstFinancialYear::currentFinancialYear()->id ?? null,
            ]);
        });


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

    public static function currencyPosition(): string  {
        return self::getOrCreate()->currency_position
            ?? 'left';
    }

    public static function decimalPlaces(): int
    {
        return self::getOrCreate()->decimal_places
            ?? 2;
    }

    public static function thousandSeparator(): string {
        return self::getOrCreate()->thousand_separator
            ?? ',';
    }

    public static function decimalSeparator(): string {
        return self::getOrCreate()->decimal_separator
            ?? '.';
    }


    public static function appFinancialYearId(): ?int {
        return self::getOrCreate()->financial_year_id
            ?? null;
    }



}


