<?php

namespace App\Models\Master\Vehicle;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstVehicle extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'picture',

        'vehicle_name',
        'vehicle_code',

        'description',
        'body_type',
        'fuel_type',

        'capacity_class',

        'max_weight_kg',
        'max_volume_cft',
        'max_crates',

        'priority_order',
        'is_active',

        'notes',
        'custchar1',
        'custchar2',

    ];

    protected $guarded = ['vehicle_code'];

    protected $casts = [
        'is_active' => 'boolean',
    ];


    // scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Put logic if vehicle code coming keep it lower case repalce with underscore
    public function setVehicleCodeAttribute($value)
    {
        // Will affect validation ?
        $this->attributes['vehicle_code'] = strtolower(str_replace(' ', '_', $value));
    }


    //
}
