<?php

namespace App\Models\Master;

use App\Models\BaseModel;

class MstFinancialYear extends BaseModel
{
    //

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
    public static function currentYear()
    {
        return self::latest()->where('is_active', true)->first();

        }
}
