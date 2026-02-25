<?php

namespace App\Models\Seller\Product;

use App\Models\BaseModel;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductListingInvoice extends BaseModel
{
    //

    use SoftDeletes;


    protected $fillable = [
        'product_listing_id',
        'invoice_number',
        'invoice_date',
        'invoice_path',
        'status',
        'count',
    ];

    // casts
    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'count' => 'integer',
    ];


    // boot method to generate unique invoice number
    protected static function booted()
    {
        static::creating(function ($invoice) {
            do {
                $sequence = MstSeqCodeGenerator::getNextInvoiceNo();
                $invoiceNumber = 'INV-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            } while (ProductListingInvoice::withTrashed()->where('invoice_number', $invoiceNumber)->exists());
            $invoice->invoice_number = $invoiceNumber;
        });
    }
}
