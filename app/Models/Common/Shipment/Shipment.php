<?php

namespace App\Models\Common\Shipment;

use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Delivery\DriverShipment;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Market\MstMarket;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Shipment extends BaseModel
{
    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->shipment_number)) {
                $model->shipment_number = self::generateUniqueShipmentNumber();
            }
        });
    }

    protected $fillable = [
        'shipment_number',

        'shipment_date',
        'shipment_type',

        'buyer_id',
        'seller_id',

        'origin_type',
        'origin_flmnt_location_id',
        'origin_depot_id',
        'origin_market_id',

        'destination_type',
        'destination_flmnt_location_id',
        'destination_depot_id',
        'destination_market_id',


        'status', // pending | grouped | assigned | in_transit | completed | cancelled
        'remarks',

        'is_seller_dropoff',
        'is_buyer_pickup',
    ];

    protected $casts = [
        'shipment_date' => 'date:Y-m-d',

        'is_seller_dropoff' => 'boolean',
        'is_buyer_pickup' => 'boolean',
    ];

    // Make a scope pending or not cancelled  for grouping
    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [ShipmentStatusEnum::PENDING->value, ShipmentStatusEnum::GROUPED->value]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */




    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')
            ->select(['id', 'name', 'user_code', 'nickname']);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')
            ->select(['id', 'name', 'user_code', 'nickname']);
    }

    public function originFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'origin_flmnt_location_id');
    }

    public function destinationFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'destination_flmnt_location_id');
    }

    public function originDepot()
    {
        return $this->belongsTo(MstDepot::class, 'origin_depot_id');
    }

    public function destinationDepot()
    {
        return $this->belongsTo(MstDepot::class, 'destination_depot_id');
    }

    public function originMarket()
    {
        return $this->belongsTo(MstMarket::class, 'origin_market_id');
    }

    public function destinationMarket()
    {
        return $this->belongsTo(MstMarket::class, 'destination_market_id');
    }

    public function driverShipment()
    {
        return $this->hasOne(DriverShipment::class, 'shipment_id', 'id');
    }

    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'shipment_id', 'id');
    }



    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private static function generateUniqueShipmentNumber(): string
    {
        $lastShipment = self::withTrashed()
            ->where('shipment_number', 'like', 'SHP-%')
            ->orderByDesc('id')
            ->first();

        if (!$lastShipment) {
            return 'SHP-000001';
        }

        $lastNumber = (int) str_replace('SHP-', '', $lastShipment->shipment_number);

        $newNumber = $lastNumber + 1;

        return 'SHP-' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }


    // Appends totals 

    protected $appends = [

        // NEW
        'from_address',
        'to_address',

        // 
        'total_packages',
        'total_weight',
    ];



    public function getTotalPackagesAttribute()
    {
        return round($this->shipmentPackages()->count(), 2);
    }

    public function getTotalWeightAttribute()
    {
        return round($this->shipmentPackages()->sum(DB::raw('pack_size * qty')), 2);
    }


    /*
|--------------------------------------------------------------------------
| NORMALIZED ORIGIN ADDRESS
|--------------------------------------------------------------------------
*/

    public function getFromAddressAttribute()
    {
        $address = null;

        if ($this->shipment_type === ShipmentTypeEnum::PICKUP->value) {
            $address = $this->originFulfillmentLocation?->address;
        } elseif ($this->shipment_type === ShipmentTypeEnum::MARKET_PICKUP->value) {
            $address = $this->originMarket?->fulfillmentLocation?->address ?? null;
        } elseif ($this->shipment_type === ShipmentTypeEnum::DISPATCH->value) {
            $address = $this->originDepot?->address ?? null;
        } elseif ($this->shipment_type === ShipmentTypeEnum::MARKET_DISPATCH->value) {
            $address = $this->originDepot?->address ?? null;
        } elseif ($this->shipment_type === ShipmentTypeEnum::TRANSFER->value) {
            $address = $this->originDepot?->address ?? null;
        }

        if (!$address) {
            return null;
        }

        return [
            'addr_name' => $address->addr_name,
            'line1' => $address->address_line1,
            'line2' => $address->address_line2,
            'village' => $address->village,
            'taluka' => $address->taluka,
            'city'  => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'contact_name' => $address->contact_name,
            'phone' => $address->phone_number,
            'lat' => $address->latitude,
            'lng' => $address->longitude,
        ];
    }

    /*
|--------------------------------------------------------------------------
| NORMALIZED DESTINATION ADDRESS
|--------------------------------------------------------------------------
*/

    public function getToAddressAttribute()
    {
        $address = null;

        if ($this->shipment_type === ShipmentTypeEnum::PICKUP->value) {
            $address = $this->destinationDepot?->address ?? null;
        } elseif ($this->shipment_type === ShipmentTypeEnum::MARKET_PICKUP->value) {
            $address = $this->destinationDepot?->address ?? null;
        } elseif ($this->shipment_type === ShipmentTypeEnum::DISPATCH->value) {
            $address = $this->destinationFulfillmentLocation?->address;
        } elseif ($this->shipment_type === ShipmentTypeEnum::MARKET_DISPATCH->value) {
            $address = $this->destinationMarket?->fulfillmentLocation?->address ?? null;
        } elseif ($this->shipment_type === ShipmentTypeEnum::TRANSFER->value) {
            $address = $this->destinationDepot?->address ?? null;
        }

        if (!$address) {
            return null;
        }

        return [
            'addr_name' => $address->addr_name,
            'line1' => $address->address_line1,
            'line2' => $address->address_line2,
            'village' => $address->village,
            'taluka' => $address->taluka,
            'city'  => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'contact_name' => $address->contact_name,
            'phone' => $address->phone_number,
            'lat' => $address->latitude,
            'lng' => $address->longitude,
        ];
    }
}
