<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Cache;

class MstFinancialYear extends BaseModel
{
    //

    protected static function booted()
    {
        static::deleting(function () {
            throw new \Exception('Financial settings cannot be deleted.');
        });


        static::saved(function () {
            try {
                if (Cache::has('current_fy_id')) {
                    Cache::forget('current_fy_id');
                }
            } catch (\Throwable $e) {
                // ignore completely
            }
        });
    }



    protected $fillable = [
        'code',
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // current year
    public static function currentFinancialYear()
    {
        return self::latest()->where('is_active', true)->first();
    }

    // scope for active
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
