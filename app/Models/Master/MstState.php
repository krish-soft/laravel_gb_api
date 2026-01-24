<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstState extends BaseModel
{
    //

    use SoftDeletes;

    protected  $fillable = [
        'name',
        'iso_code',
        'language',
        'type',
        'country_iso_code',
        'is_ut',
        'is_active',
    ];

    // Casts
    protected $casts = [
        'is_active' => 'boolean',
        'is_ut' => 'boolean',
    ];


    // scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
