<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Market\MstMarket;
use App\Models\Seller\Product\ProductListingItem;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DemandOrderFulfillment extends BaseModel
{
    //

    use SoftDeletes;


    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->fulfillment_number)) {
                $model->fulfillment_number = (string) Str::uuid();
            }
        });
    }


    protected $fillable = [
        'demand_order_id',
        'demand_order_item_id',

        'fulfillment_number', // shipping, pickup, etc
        'fulfillment_location_id', // depot or pickup location

        'seller_id',
        'pickup_fulfillment_location_id',

        'product_listing_item_id',
        'product_listing_package_id',

        'market_id',

        'pack_size',
        'pack_unit',
        'pack_type_unit',
        'pack_price',
        'per_unit_price',


        //
    ];



    public function demandOrder()
    {
        return $this->belongsTo(DemandOrder::class, 'demand_order_id', 'id');
    }

    public function demandOrderItem()
    {
        return $this->belongsTo(DemandOrderItem::class, 'demand_order_item_id', 'id');
    }



    public function productListingItem()
    {
        return $this->belongsTo(ProductListingItem::class, 'product_listing_item_id', 'id');
    }

    public function productListingPackage()
    {
        return $this->belongsTo(ProductListingPackage::class, 'product_listing_package_id', 'id');
    }

    public function pickupFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'pickup_fulfillment_location_id', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id')->select('id', 'user_code', 'nickname', 'charge_level_code');
    }

    public function market()
    {
        return $this->belongsTo(MstMarket::class, 'market_id', 'id');
    }


    ### USED IN TOO MANY PLACES, SO ADDED HERE FOR EASY ACCESS
    // DO NOT ACCESS WITH RELATIONSHIP TO AVOID N+1 PROBLEMS
    protected $appends = [
        'pickup_depot',
    ];





    // Get Depot info from the related order
    public function getPickupDepotAttribute()
    {

        $data = $this->pickupFulfillmentLocation->primaryDepot ?? $this->pickupFulfillmentLocation->user->primaryDepot ?? null;

        $this->unsetRelation('pickupFulfillmentLocation');
        $this->unsetRelation('user');

        return $data;
    }
}
