<?php

namespace App\Models\Market;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Market\MstMarket;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketOrder extends BaseModel
{
    //

    use SoftDeletes;


    protected static function booted()
    {
        static::creating(function ($order) {
            do {
                $sequence = MstSeqCodeGenerator::getNextMarketOrderNo();
                $orderNumber = 'MKTORD-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            } while (self::withTrashed()->where('market_order_number', $orderNumber)->exists());
            $order->market_order_number = $orderNumber;
        });
    }


    protected $fillable = [

        'market_id',
        'depot_id',

        'shipping_fulfillment_location_id',

        'market_order_number',
        
        'order_status',
        'delivery_status',
        'order_date',

        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',

        'is_buyer_pickup',
        'pickup_addr_code',

        'payment_method',
        'payment_status',
        'payment_reference',

        'reference',

        'is_partial',
        'is_paid',
        'is_locked',
        'is_manual',

        'remarks',

    ];

    // casts
    protected $casts = [
        'order_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_partial' => 'boolean',
        'is_paid' => 'boolean',
        'is_locked' => 'boolean',
        'is_manual' => 'boolean',
    ];

    // relationships

    public function market()
    {
        return $this->belongsTo(MstMarket::class, 'market_id');
    }

    public function depot()
    {
        return $this->belongsTo(MstDepot::class, 'depot_id');
    }

    public function marketOrderItems()
    {
        return $this->hasMany(MarketOrderItem::class, 'market_order_id');
    }

    public function shippingFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'shipping_fulfillment_location_id', 'id');
    }










    // 
}
