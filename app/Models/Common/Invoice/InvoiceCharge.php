<?php

namespace App\Models\Common\Invoice;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceCharge extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'charge_name',
        'qty',
        'ship_qty',
        'taxable_amount',
        'tax_amount',
        'total_amount',
    ];

    // casts
    protected $casts = [
        'charge_amount' => 'decimal:2',
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
