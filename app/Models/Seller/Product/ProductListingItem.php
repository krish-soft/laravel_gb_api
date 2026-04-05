<?php

namespace App\Models\Seller\Product;

use App\Models\BaseModel;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Market\MarketOrderItem;
use App\Models\Master\Product\MstProduct;
use App\Models\Master\Product\MstProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function marketOrderItems()
    {
        return $this->hasMany(MarketOrderItem::class, 'product_listing_item_id');
    }

    


    // helpers
    public function isOrganic()
    {
        return $this->is_organic;
    }


    protected $appends = [
        // 'product_name',
        // 'variant_name',

        'total_qty',
        'total_sold_qty',
        'total_available_qty',

        'total_weight',
        'total_sold_weight',
        'total_available_weight',
    ];

    public function getProductNameAttribute()
    {
        $productName = $this->product ? $this->product->name : null;
        $this->unsetRelation('product'); // Unset the relation to prevent it from being included in the JSON response
        return $productName;
    }

    public function getVariantNameAttribute()
    {

        $variantName = $this->productVariant ? $this->productVariant->name : null;
        $this->unsetRelation('productVariant'); // Unset the relation to prevent it from being included in the JSON response
        return $variantName;
    }

    // get total qty 
    public function getTotalQtyAttribute()
    {
        return $this->listingPackages()->sum('qty');
    }

    // get total sold qty
    public function getTotalSoldQtyAttribute()
    {
        return $this->listingPackages()->sum('sold_qty');
    }

    // get total available qty
    public function getTotalAvailableQtyAttribute()
    {
        return $this->listingPackages()->selectRaw('SUM(qty - sold_qty) as available_qty')->value('available_qty');
    }

    // get total weight
    public function getTotalWeightAttribute()
    {
        return $this->listingPackages()->sum(DB::raw('qty * pack_size'));
    }

    // get total sold weight
    public function getTotalSoldWeightAttribute()
    {
        return $this->listingPackages()->sum(DB::raw('sold_qty * pack_size'));
    }

    // get total available weight
    public function getTotalAvailableWeightAttribute()
    {
        return $this->listingPackages()->selectRaw('SUM((qty - sold_qty) * pack_size) as available_weight')->value('available_weight');
    }
}
