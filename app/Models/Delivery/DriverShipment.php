<?php

namespace App\Models\Delivery;

use App\Models\BaseModel;
use App\Models\Common\Shipment\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DriverShipment extends BaseModel
{
    //


    protected $fillable = [
        'shipment_id',
        'driver_id',
        'driver_vehicle_id',
        'assigned_by',
        'assigned_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'vehicle_number',
    ];

    // casts 
    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // relationships
    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function driverShipmentPackages()
    {
        return $this->shipment()->with('shipmentGroups.driverShipmentPackages');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id')->select('id', 'name', 'user_code', 'nickname');
    }

    public function driverVehicle()
    {
        return $this->belongsTo(DriverVehicle::class, 'driver_vehicle_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by')->select('id', 'name', 'user_code', 'nickname');
    }


    //
}
