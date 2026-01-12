<?php

namespace App\Models\Buyer\Order;

use App\Models\Address;
use App\Models\BaseModel;
use App\Models\Buyer\Cart\Cart;
use App\Models\Fulfillment\FulfillmentLocation;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends BaseModel
{
    //
    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($order) {
            do {
                $sequence = MstSeqCodeGenerator::getNextOrderNo();
                $orderNumber = 'ORD-' . str_pad($sequence, 8, '0', STR_PAD_LEFT);
            } while (Order::withTrashed()->where('order_number', $orderNumber)->exists());
            $order->order_number = $orderNumber;
        });
    }

    protected $fillable = [
        'cart_id',
        'buyer_id',

        'pickup_fulfillment_location_id',
        'shipping_fulfillment_location_id',


        'order_number',
        'order_status',
        'order_date',
        'expected_ship_date',

        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',

        'payment_method',
        'payment_status',

        'is_partial',
        'is_paid',

        'bill_addr_code',
        'ship_addr_code',
        'pick_addr_code',

    ];

    // Casts

    protected $casts = [
        'order_date' => 'date',
        'expected_ship_date' => 'date',
        'is_partial' => 'boolean',
        'is_paid' => 'boolean',
    ];

    // Relationships

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public function orderCharges()
    {
        return $this->hasMany(OrderCharge::class, 'order_id', 'id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function pickupFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'pickup_fulfillment_location_id', 'id');
    }

    public function shippingFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'shipping_fulfillment_location_id', 'id');
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
    }

    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'bill_addr_code', 'address_code');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'ship_addr_code', 'address_code');
    }

    public function pickupAddress()
    {
        return $this->belongsTo(Address::class, 'pick_addr_code', 'address_code');
    }


    //
}
