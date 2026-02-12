<?php

namespace App\Models\Delivery;

use App\Models\BaseModel;
use App\Models\Common\User\UserDepot;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Vehicle\MstVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverVehicle extends BaseModel
{
    //
    use SoftDeletes;


    protected $fillable = [
        'picture',
        'driver_id',
        'vehicle_id',

        'driver_vehicle_code',
        'license_plate_number',
        'vehicle_color',
        'max_load_capacity_kg',
        'max_volume_capacity_cft',
        'max_number_of_packages',
        'is_active',
        'inactive_reason',
        'is_available_for_delivery',

        'last_latitude',
        'last_longitude',
    ];

    // casts
    protected $casts = [
        'is_active' => 'boolean',
        'is_available_for_delivery' => 'boolean',
        'max_load_capacity_kg' => 'decimal:2',
        'max_volume_capacity_cft' => 'decimal:2',
        'max_number_of_packages' => 'decimal:2',

        'last_latitude' => 'decimal:7',
        'last_longitude' => 'decimal:7',
    ];

    // scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // relationships
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id')->select('id', 'name', 'user_code', 'nickname');
    }

    public function vehicle()
    {
        return $this->belongsTo(MstVehicle::class, 'vehicle_id');
    }

    public function depots()
    {
        return $this->driver->depots();
    }

    //
}
