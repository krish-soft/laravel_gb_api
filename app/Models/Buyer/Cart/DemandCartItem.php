<?php

namespace App\Models\Buyer\Cart;

use App\Models\BaseModel;
use App\Models\Master\Product\MstProduct;
use App\Models\Master\Product\MstProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemandCartItem extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'demand_cart_id',

        'product_id',
        'variant_id',

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

    public function demandCart()
    {
        return $this->belongsTo(DemandCart::class, 'demand_cart_id');
    }

    public function product()
    {
        return $this->belongsTo(MstProduct::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(MstProductVariant::class, 'variant_id');
    }
}
