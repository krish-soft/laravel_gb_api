<?php

namespace App\Models\Buyer\Order;

use App\Models\BaseModel;
use App\Models\Buyer\Cart\DemandCart;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemandOrder extends BaseModel
{
    //
    use SoftDeletes;


    protected static function booted()
    {
        static::creating(function ($order) {
            do {
                $sequence = MstSeqCodeGenerator::getNextOrderNo();
                $orderNumber = 'DMD-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            } while (Order::withTrashed()->where('order_number', $orderNumber)->exists());
            $order->order_number = $orderNumber;
        });
    }

    protected $fillable = [
        'demand_cart_id',
        'buyer_id',
        'depot_id',

        'shipping_fulfillment_location_id',

        'order_number',
        'order_status',
        'delivery_status',

        'order_date',
        'expected_ship_date',

        'base_amount', // base amount without tax and charges, we can use it for accounting and settlement items total only
        'subtotal', // subtotal is base amount + charge amount, we can use it for accounting and settlement total amount
        'tax_amount',
        'total_amount',
        'credit_amount', // balance of credit used in this order if any
        'currency',

        'payment_method', // payment method
        'payment_status', // payment status
        'payment_reference', // payment reference

        'reference', // internal reference // payment_code

        'bill_addr_code',
        'ship_addr_code',

        'is_buyer_pickup',
        'pickup_addr_code', // If buyer pickup selected

        'is_partial',
        'is_paid',
        'is_locked',
        'is_manual', // Manually created order

        'remarks',

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



    public function demandOrderItems()
    {
        return $this->hasMany(DemandOrderItem::class, 'demand_order_id', 'id');
    }

    public function demandOrderCharges()
    {
        return $this->hasMany(DemandOrderCharge::class, 'demand_order_id', 'id');
    }

    public function demandOrderFulfillments()
    {
        return $this->hasMany(DemandOrderFulfillment::class, 'demand_order_id', 'id');
    }


    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    // shipping Depot for this order
    public function depot()
    {
        return $this->belongsTo(MstDepot::class, 'depot_id', 'id');
    }


    public function demandCart()
    {
        return $this->belongsTo(DemandCart::class, 'demand_cart_id', 'id');
    }

    // for invoice
    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'bill_addr_code', 'address_code');
    }

    // for invoice
    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'ship_addr_code', 'address_code');
    }

    // if buyer want to pickup
    public function pickupAddress()
    {
        return $this->belongsTo(Address::class, 'pickup_addr_code', 'address_code');
    }

    // actual shipping location
    public function shippingFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'shipping_fulfillment_location_id', 'id');
    }

    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'demand_order_id', 'id');
    }

    //

}
