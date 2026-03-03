<?php

namespace App\Models\Common\Invoice;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'source_id',

        'product_code',
        'product_name',

        'order_qty',
        'ship_qty',

        'pack_size',
        'pack_unit',
        'pack_type_unit',

        'pack_price',

        'discount_amount',

        'taxable_amount',
        'tax_amount',
        'total_amount',

        'reference',
    ];

    // casts    
    protected $casts = [
        'order_qty' => 'decimal:2',
        'ship_qty' => 'decimal:2',
        'pack_size' => 'decimal:2',
        'pack_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];


    // relationships

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

   

    //
}
