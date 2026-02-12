<?php

namespace App\Models\Common\Shipment;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Depot\MstDepot;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
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

        'destination_type',
        'destination_flmnt_location_id',
        'destination_depot_id',

        'status', // pending | grouped | assigned | in_transit | completed | cancelled
        'remarks',
    ];

    protected $casts = [
        'shipment_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function shipmentGroups()
    {
        return $this->hasMany(ShipmentPackageGroup::class);
    }

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




    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private static function generateUniqueShipmentNumber(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while (
            self::withTrashed()
            ->where('shipment_number', $code)
            ->exists()
        );

        return $code;
    }

    // Appends totals 

    protected $appends = [
        'total_packages',
        'total_weight',
        'group_number',

        // NEW
        'from_address',
        'to_address',
    ];



    public function getTotalPackagesAttribute()
    {
        return $this->shipmentGroups()->count();
    }

    public function getTotalWeightAttribute()
    {
        return $this->shipmentGroups()
            ->with('shipmentPackage:id,pack_size,qty')
            ->get()
            ->sum(fn($g) => ($g->shipmentPackage->pack_size ?? 0) * ($g->shipmentPackage->qty ?? 0));
    }



    public function getGroupNumberAttribute()
    {
        // if relation already loaded (fast)
        if ($this->relationLoaded('shipmentGroups')) {
            return $this->shipmentGroups->first()?->group_number;
        }

        // fallback query
        return $this->shipmentGroups()
            ->value('group_number');
    }


    /*
|--------------------------------------------------------------------------
| NORMALIZED ORIGIN ADDRESS
|--------------------------------------------------------------------------
*/

    public function getFromAddressAttribute()
    {
        $address = null;

        if ($this->shipment_type === 'pickup') {
            $address = $this->originFulfillmentLocation?->address;
        } elseif ($this->shipment_type === 'dispatch') {
            $address = $this->originDepot?->address ?? null;
        } elseif ($this->shipment_type === 'transfer') {
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

        if ($this->shipment_type === 'pickup') {
            $address = $this->destinationDepot?->address ?? null;
        } elseif ($this->shipment_type === 'dispatch') {
            $address = $this->destinationFulfillmentLocation?->address;
        } elseif ($this->shipment_type === 'transfer') {
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
