<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

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

    protected $appends = ['invoice_url'];

    public function getInvoiceUrlAttribute()
    {

        $url = $this->invoice_path
            ? URL::temporarySignedRoute(
                'files.view',
                now()->addMinutes(5),
                [
                    'path' => $this->invoice_path,
                    'download' => 0
                ]
            )
            : null;

        // this one default append on each model retrieval, so we can not increment count here, otherwise it will increment count on each retrieval, we should increment count only when the url is accessed, so we can handle that in the route that serves the file, and we can pass the invoice id in the route and increment count there when the file is accessed.
        // if ($url) {
        //     $this->increment('count');
        // }

        return $url;
    }


    //
}
