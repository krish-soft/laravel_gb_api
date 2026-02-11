<?php

namespace App\Models\Common\Shipment;

use App\Models\BaseModel;
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
        'origin_id',

        'destination_type',
        'destination_id',

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

    public function origin()
    {
        return match ($this->origin_type) {

            'buyer'  => $this->belongsTo(User::class, 'origin_id')
                ->select(['id', 'name', 'user_code', 'nickname']),

            'seller' => $this->belongsTo(User::class, 'origin_id')
                ->select(['id', 'name', 'user_code', 'nickname']),

            'fulfillment_location' =>
            $this->belongsTo(\App\Models\Common\Fulfillment\FulfillmentLocation::class, 'origin_id')
                ->select(['id', 'name', 'fl_code']),

            default => null,
        };
    }

    public function destination()
    {
        return match ($this->destination_type) {

            'buyer'  => $this->belongsTo(User::class, 'destination_id')
                ->select(['id', 'name', 'user_code', 'nickname']),

            'seller' => $this->belongsTo(User::class, 'destination_id')
                ->select(['id', 'name', 'user_code', 'nickname']),

            'fulfillment_location' =>
            $this->belongsTo(\App\Models\Common\Fulfillment\FulfillmentLocation::class, 'destination_id')
                ->select(['id', 'name', 'fl_code']),

            default => null,
        };
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
        'origin_label',
        'destination_label',
        'group_number',
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


    public function getOriginLabelAttribute()
    {
        if ($this->origin_type === 'buyer' || $this->origin_type === 'seller') {
            return $this->origin?->name;
        }

        if ($this->origin_type === 'fulfillment_location') {
            return $this->origin?->fl_code . ' | ' . $this->origin?->name;
        }

        return null;
    }
    public function getDestinationLabelAttribute()
    {
        if ($this->destination_type === 'buyer' || $this->destination_type === 'seller') {
            return $this->destination?->name;
        }

        if ($this->destination_type === 'fulfillment_location') {
            return $this->destination?->fl_code . ' | ' . $this->destination?->name;
        }

        return null;
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
}
