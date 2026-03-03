<?php

namespace App\Models\Common\Invoice;

use App\Models\BaseModel;
use App\Models\Buyer\Order\Order;
use App\Models\Market\MarketOrder;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use App\Models\Seller\Product\ProductListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_id',
        'market_order_id',
        'product_listing_id',

        'invoice_number',
        'invoice_date',
        'invoice_path',
        'invoice_type',

        'status',
        'payment_status',

        'platform_bill_addr_code', // fix of platform

        'customer_bill_addr_code', // Optional 
        'customer_ship_addr_code', // need

        'revision_count',

        'base_amount', // base amount without tax and charges, we can use it for accounting and settlement items total only
        'subtotal', // subtotal is base amount + tax amount, we can use it for accounting and settlement total amount
        'tax_amount',
        'total_amount',

        'currency',

        'reference',
        'remarks',
    ];

    // casts
    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'revision_count' => 'integer',
        'base_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // relationships

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function invoiceCharges()
    {
        return $this->hasMany(InvoiceCharge::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->select('id', 'name', 'user_code', 'nickname', 'charge_level_code', 'role');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function marketOrder()
    {
        return $this->belongsTo(MarketOrder::class);
    }

    public function productListing()
    {
        return $this->belongsTo(ProductListing::class);
    }

    // booted generate invoice number 
    protected static function booted()
    {
        static::creating(function ($invoice) {

            if (empty($invoice->invoice_number)) {

                do {
                    $nextNumber = str_pad(MstSeqCodeGenerator::getNextInvoiceNo(), 8, '0',  STR_PAD_LEFT);

                    $invoiceNumber = 'INV-' . $nextNumber;
                } while (self::where('invoice_number', $invoiceNumber)->exists());

                $invoice->invoice_number = $invoiceNumber;
            }
        });
    }





    //
}
