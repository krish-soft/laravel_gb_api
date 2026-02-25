<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderInvoice extends BaseModel
{
    //

    use SoftDeletes;


    protected $fillable = [
        'order_id',
        'invoice_number',
        'invoice_date',
        'invoice_path',
    ];

    // casts    
    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
    ];

    // Relationships

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // boot method to generate unique invoice number
    protected static function booted()
    {
        static::creating(function ($invoice) {
            do {
                $sequence = MstSeqCodeGenerator::getNextInvoiceNo();
                $invoiceNumber = 'INV-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            } while (OrderInvoice::withTrashed()->where('invoice_number', $invoiceNumber)->exists());
            $invoice->invoice_number = $invoiceNumber;
        });
    }



    //
}
