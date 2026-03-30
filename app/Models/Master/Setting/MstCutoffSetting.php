<?php

namespace App\Models\Master\Setting;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MstCutoffSetting extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::deleting(function () {
            throw new \RuntimeException('Cutoff settings cannot be deleted.');
        });


        static::saved(function () {
            try {
                if (Schema::hasTable('mst_cutoff_settings') && Cache::has('mst_cutoff_settings')) {
                    Cache::forget('mst_cutoff_settings');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });

        static::updated(function () {
            if (Schema::hasTable('mst_cutoff_settings') && Cache::has('mst_cutoff_settings')) {
                Cache::forget('mst_cutoff_settings');
            }
        });
    }

    protected $fillable = [

        'seller_start_time',
        'seller_end_time',

        'buyer_start_time',
        'buyer_end_time',

        'is_buyer_auto_cutoff',
        'is_seller_auto_cutoff',
    ];

    // casts    

    protected $casts = [
        'buyer_start_time' => 'datetime:H:i:s',
        'buyer_end_time' => 'datetime:H:i:s',
        'seller_start_time' => 'datetime:H:i:s',
        'seller_end_time' => 'datetime:H:i:s',

        'is_buyer_auto_cutoff' => 'boolean',
        'is_seller_auto_cutoff' => 'boolean',
    ];


    public static function getOrCreate(): Model
    {
        return Cache::rememberForever('mst_cutoff_settings', function () {

            return self::firstOrCreate(
                [
                    'seller_start_time' => '09:00:00', // 9 AM by default
                    'seller_end_time' => '15:00:00', // 3 PM by default

                    'buyer_start_time' => '09:00:00', // 9 AM by default
                    'buyer_end_time' => '23:59:59', // End of day by default
                ],
                [
                    'is_buyer_auto_cutoff' => false,
                    'is_seller_auto_cutoff' => false,
                ]
            );
        });
    }
}
