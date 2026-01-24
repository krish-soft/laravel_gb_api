<?php

namespace App\Models\Common\Fulfillment;

use App\Models\BaseModel;
use App\Models\Master\Depot\MstDepot;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FulfillmentLocationDepot extends BaseModel
{
    //

    protected $fillable = [
        'fulfillment_location_id',
        'depot_id',
        'is_primary',
        'is_active',
    ];


    // casts
    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    // scope

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // relationships

    public function fulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'fulfillment_location_id', 'id');
    }

    public function depot()
    {
        return $this->belongsTo(MstDepot::class, 'depot_id', 'id');
    }
}
