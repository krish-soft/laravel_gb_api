<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemandOrderCharge extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'demand_order_id',
        'order_number',

        'charge_name',
        'charge_code',
        'qty',

        'rule_type',
        'rule_no',
        'rule_desc',

        'taxable_amount',
        'tax_amount',
        'total_amount',
    ];

    // casts
    protected $casts = [
        'qty' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Relationships
    public function demandOrder()
    {
        return $this->belongsTo(DemandOrder::class, 'demand_order_id', 'id');
    }
}
