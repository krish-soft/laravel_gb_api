<?php

namespace App\Models\Seller\Product;

use App\Models\BaseModel;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Market\MarketOrderItem;
use Illuminate\Database\Eloquent\Model;

class ProductListingPackage extends BaseModel
{
    //

    protected $fillable = [
        'picture',

        'product_listing_item_id',
        'listing_code',
        'qty',
        'sold_qty',
        'pack_size',
        'pack_unit',
        'pack_type_unit',
        'pack_price',
        'per_kg_price',

        'quality_grade', // A, B, C, etc. (optional)


        'discount_amount',
        'discount_type',

        'is_partial',
        'is_sold',
        'is_locked',
    ];

    // Casts

    protected $casts = [
        'qty' => 'decimal:2',
        'sold_qty' => 'decimal:2',
        'pack_size' => 'decimal:2',
        'pack_price' => 'decimal:2',
        'per_kg_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'is_partial' => 'boolean',
        'is_sold' => 'boolean',
        'is_locked' => 'boolean',
    ];


    // scope
    public function scopeAvailable($query)
    {
        return $query->where('is_sold', false)
            ->where('is_locked', false)
            ->whereRaw('qty > sold_qty');
    }


    // Relationships


    public function productListingItem()
    {
        return $this->belongsTo(ProductListingItem::class, 'product_listing_item_id');
    }

    public function product()
    {
        return $this->productListingItem->product();
    }

    public function productVariant()
    {
        return $this->productListingItem->productVariant();
    }

    public function seller()
    {
        return $this->productListingItem->productListing->seller();
    }

    public function pickupFulfillmentLocation()
    {
        return $this->productListingItem->productListing->fulfillmentLocation();
    }


    // Order Item relationship through order item package
    public function orderItem()
    {
        return $this->hasOne(OrderItem::class, 'product_listing_package_id');
    }

    public function marketOrderItem()
    {

        return $this->hasOne(MarketOrderItem::class, 'product_listing_package_id');
    }


    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'product_listing_package_id');
    }


    //
}
