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

    ];

    // Relationships
    public function demandOrder()
    {
        return $this->belongsTo(DemandOrder::class, 'demand_order_id', 'id');
    }
}
