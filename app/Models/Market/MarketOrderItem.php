<?php

namespace App\Models\Market;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Seller\Product\ProductListingItem;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketOrderItem extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'market_order_id',
        'market_order_number',

        'product_listing_item_id',
        'product_listing_package_id',
        'product_listing_id',

        'seller_id',
        'pickup_fulfillment_location_id',

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


    ];

    // casts
    protected $casts = [
        'order_qty' => 'decimal:2',
        'ship_qty' => 'decimal:2',
        'pack_size' => 'decimal:2',
        'pack_price' => 'decimal:2',
        'per_unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // relationships

    public function marketOrder()
    {
        return $this->belongsTo(MarketOrder::class, 'market_order_id');
    }

    public function productListingItem()
    {
        return $this->belongsTo(ProductListingItem::class, 'product_listing_item_id');
    }

    public function productListingPackage()
    {
        return $this->belongsTo(ProductListingPackage::class, 'product_listing_package_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function pickupFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'pickup_fulfillment_location_id');
    }
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

        return \App\Models\User::select('id', 'name', 'user_code', 'nickname')
            ->find($sellerId);
    }


    // Get Depot info from the related order
    public function getPickupDepotAttribute()
    {

        return $this->pickupFulfillmentLocation->primaryDepot ?? $this->pickupFulfillmentLocation->user->primaryDepot ?? null;
    }
}
