<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class OrderCharge extends BaseModel
{
    //

    protected $fillable = [
        'order_id',
        'order_number',

        'charge_name',
        'charge_code',

        'rule_type',
        'rule_no',
        'rule_desc',

        'taxable_amount',
        'tax_amount',
        'total_amount',
    ];

    // casts
    protected $casts = [
        'taxable_amount' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }


    //
}
