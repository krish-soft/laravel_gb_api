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
    ];

    // casts
    protected $casts = [
        'order_qty' => 'integer',
        'ship_qty' => 'integer',

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


    protected $appends = [
        'seller',
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

    //
}
