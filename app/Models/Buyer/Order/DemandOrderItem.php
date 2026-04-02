<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemandOrderItem extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'demand_order_id',
        'order_number',

        'product_id',

        'product_code',
        'product_name',

        'product_variant_id',
        'variant_code',
        'variant_name',

        'order_qty',
        'ship_qty',
        'seller_ship_qty',

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

        'is_fulfilled',
        'is_cancelled',
        'is_returned',
        'is_replaced',
    ];

    // casts
    protected $casts = [
        'order_qty' => 'decimal:2',
        'ship_qty' => 'decimal:2',
        'seller_ship_qty' => 'decimal:2',

        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',


        'is_fulfilled' => 'boolean',
        'is_cancelled' => 'boolean',
        'is_returned' => 'boolean',
        'is_replaced' => 'boolean',

    ];

    // Relationships
    public function demandOrder()
    {
        return $this->belongsTo(DemandOrder::class, 'demand_order_id', 'id');
    }
}
