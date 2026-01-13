<?php

namespace App\Models\Buyer\Cart;

use App\Models\BaseModel;
use App\Models\Seller\Product\ProductListingItem;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartItem extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'cart_id',
        'seller_id',

        'product_listing_item_id',
        'product_listing_package_id',

        'order_qty',
        'pack_size',
        'pack_unit',
        'pack_type_unit',
        'pack_price',
        'per_unit_price',

        'discount_amount',
        'discount_type',

        'total_price',
    ];

    // Casts
    protected $casts = [
        //

    ];

    // Relationships

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function productListingItem()
    {
        return $this->belongsTo(ProductListingItem::class, 'product_listing_item_id');
    }

    public function productListingPackage()
    {
        return $this->belongsTo(ProductListingPackage::class, 'product_listing_package_id');
    }


    //
}
