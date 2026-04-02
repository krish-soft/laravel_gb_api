<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingItem;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends BaseModel
{
    //

    protected $fillable = [
        'order_id',
        'order_number',

        'pickup_fulfillment_location_id',

        'seller_id',
        'product_listing_item_id',
        'product_listing_package_id',

        'listing_code',

        'product_code',
        'product_name',

        'variant_code',
        'variant_name',

        'order_qty',
        'ship_qty',

        'pack_size',
        'pack_unit',
        'pack_type_unit',

        'pack_price',
        'per_unit_price',

        'discount_amount',
        'discount_type',

        'taxable_amount',
        'tax_amount',
        'total_amount',

        'reference',
        'remarks',

        'is_reverse',
        'reverse_reference',
    ];


    // casts
    protected $casts = [
        'order_qty' => 'decimal:2',
        'ship_qty' => 'decimal:2',

        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',

        'is_reverse' => 'boolean',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
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


    ### USED IN TOO MANY PLACES, SO ADDED HERE FOR EASY ACCESS
    // DO NOT ACCESS WITH RELATIONSHIP TO AVOID N+1 PROBLEMS
    protected $appends = [
        'seller',
        'pickup_depot',
    ];


    public function getSellerAttribute()
    {
        // Get seller info from the related product listing item -> product listing -> seller
        $sellerId = $this->productListingItem?->productListing?->seller_id;

        if (!$sellerId) {
            return null;
        }

        $data = \App\Models\User::select('id', 'name', 'user_code', 'nickname')
            ->find($sellerId);

        // unsetRelation
        $this->unsetRelation('productListingItem');
        $this->unsetRelation('productListing');
        // $this->unsetRelation('seller');

        return $data;
    }


    // Get Depot info from the related order
    public function getPickupDepotAttribute()
    {

        $data = $this->pickupFulfillmentLocation->primaryDepot ?? $this->pickupFulfillmentLocation->user->primaryDepot ?? null;

        $this->unsetRelation('pickupFulfillmentLocation');
        $this->unsetRelation('user');

        return $data;
    }




    //
}
