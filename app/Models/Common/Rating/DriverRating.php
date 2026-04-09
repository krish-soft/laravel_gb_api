<?php

namespace App\Models\Common\Rating;

use App\Models\BaseModel;
use App\Models\Delivery\DriverShipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverRating extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'driver_shipment_id',
        'driver_id',
        'user_id',
        'rating',
        'review',
    ];

    // casts
    protected $casts = [
        'driver_shipment_id' => 'integer',
        'driver_id' => 'integer',
        'user_id' => 'integer',
        'rating' => 'integer',
    ];

    // relationships
    public function driverShipment()
    {
        return $this->belongsTo(DriverShipment::class, 'driver_shipment_id', 'id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id', 'id')->safe();
    }

    // mainly will given by buyer & seller
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->safe();
    }
}
