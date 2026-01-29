<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use App\Models\Buyer\Cart\Cart;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use App\Models\User;
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

        'shipping_fulfillment_location_id',

        'order_number',
        'order_status',
        'order_date',
        'expected_ship_date',

        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',

        'payment_method', // payment method
        'payment_status', // payment status

        'is_partial',
        'is_paid',
        'is_locked',
        'is_manual', // Manually created order

        'wallet_txn_code', // For Refrence

        'bill_addr_code',
        'ship_addr_code',
        'pick_addr_code',

        'is_buyer_pickup',
        'pickup_addr_code', // If buyer pickup selected

        //
    ];

    // Casts

    protected $casts = [
        'order_date' => 'date',
        'expected_ship_date' => 'date',
        'is_partial' => 'boolean',
        'is_paid' => 'boolean',
        'is_locked' => 'boolean',
        'is_manual' => 'boolean',
        'is_buyer_pickup' => 'boolean',
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
