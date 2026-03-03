<?php

namespace App\Models\Seller\Product;

use App\Models\BaseModel;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Master\Product\MstProduct;
use App\Models\Master\Product\MstProductVariant;
use Illuminate\Database\Eloquent\Model;

class ProductListingItem extends BaseModel
{
    //

    protected $fillable = [
        'picture',
        'product_listing_id',
        'listing_code',
        'product_id',
        'product_variant_id',
        'is_organic', // organic, inorganic, service
    ];

    // casts
    protected $casts = [
        'is_organic' => 'boolean',
    ];

    // Relationships

    public function productListing()
    {
        return $this->belongsTo(ProductListing::class, 'product_listing_id');
    }

    public function product()
    {
        return $this->belongsTo(MstProduct::class, 'product_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(MstProductVariant::class, 'product_variant_id');
    }

    public function listingPackages()
    {
        return $this->hasMany(ProductListingPackage::class, 'product_listing_item_id');
    }

    public function seller()
    {
        return $this->productListing->seller;
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_listing_item_id');
    }



    // helpers
    public function isOrganic()
    {
        return $this->is_organic;
    }
}
