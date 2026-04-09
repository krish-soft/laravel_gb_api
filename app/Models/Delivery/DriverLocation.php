<?php

namespace App\Models\Delivery;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverLocation extends Model
{
    // No need BaseModel
    use SoftDeletes;

    protected $fillable = [
        'driver_id',
        'latitude',
        'longitude',
        'driver_shipment_id',
    ];

    // Relationships
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id')->safe();
    }

    public function driverShipment()
    {
        return $this->belongsTo(DriverShipment::class, 'driver_shipment_id');
    }
}
