<?php

namespace App\Models\Master\Setting;

use App\Models\BaseModel;
use App\Models\Common\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MstBusinessSetting extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::deleting(function () {
            throw new \RuntimeException('Business settings cannot be deleted.');
        });


        static::saved(function () {
            try {
                if (Schema::hasTable('mst_business_settings') && Cache::has('mst_business_settings')) {
                    Cache::forget('mst_business_settings');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });

        static::updated(function () {
            if (Schema::hasTable('mst_business_settings') && Cache::has('mst_business_settings')) {
                Cache::forget('mst_business_settings');
            }
        });
    }



    protected $fillable = [
        'setting_code',

        'picture',
        'legal_name',
        'trade_name',

        'gst_number',
        'cin_number',
        'pan_number',
        'tan_number',

        'email',
        'phone',

        'bill_addr_code',
        'addr_code',

        'website',
        'terms_url',
        'privacy_url',
        'notes',

        'is_active',

    ];

    // casts
    protected $casts = [
        'is_gst_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];


    public static function getOrCreate(): Model
    {
        // return Cache::rememberForever('mst_business_settings', function () {
        return self::firstOrCreate(
            [
                'setting_code' => 'SETTING_001',
            ],
            [
                'legal_name' => 'Krishna Software Pvt Ltd',
                'trade_name' => 'Khet Bajar'
            ]
        );
        // });
    }


    // Relation
    public function billAddress()
    {
        return $this->belongsTo(Address::class, 'bill_addr_code', 'addr_code');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'addr_code', 'addr_code');
    }


    // Helpers

    public static function legalName(): string
    {
        return self::getOrCreate()->legal_name;
    }

    public static function tradeName(): string
    {
        return self::getOrCreate()->trade_name;
    }

    public static function gstNumber(): ?string
    {
        return self::getOrCreate()->gst_number;
    }

    public static function isGstEnabled(): bool
    {
        return self::getOrCreate()->is_gst_enabled;
    }





    //
}
