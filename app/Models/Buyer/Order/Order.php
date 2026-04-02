<?php

namespace App\Models\Buyer\Order;

use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\BaseModel;
use App\Models\Buyer\Cart\Cart;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Invoice\Invoice;
use App\Models\Common\Payment\Payment;
use App\Models\Common\Rating\OrderRating;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Depot\MstDepot;
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
                $orderNumber = 'ORD-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            } while (Order::withTrashed()->where('order_number', $orderNumber)->exists());
            $order->order_number = $orderNumber;
        });
    }

    protected $fillable = [
        'cart_id',
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
        'is_cutoff', // when order is processed for cutoff

        'flags',
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
        'is_cutoff' => 'boolean',
        'flags' => 'array',
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

    public function orderInvoices()
    {
        return $this->hasMany(Invoice::class, 'order_id', 'id');
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

    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'source_id', 'id')->where('source', self::class);
    }


    // actual shipping location
    public function shippingFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'shipping_fulfillment_location_id', 'id');
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
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

    public function payment()
    {
        return $this->hasOne(Payment::class, 'source_id', 'id')
            ->where('source_type', self::class)
            ->where(function ($query) {
                $query->where('payment_code', $this->reference)
                    ->orWhere('gateway_order_id', $this->payment_reference);
            });
    }

    public function orderRatings()
    {
        return $this->hasMany(OrderRating::class, 'order_id', 'id');
    }


    // assgned
    protected $appends = [
        'is_need_payment',
        'payment_url',
    ];

    public function getIsNeedPaymentAttribute()
    {
        // check payment url and payment status 
        $status = false;
        if (
            $this->payment
            &&
            (
                isset($this->payment->payment_url)
                && in_array($this->payment->status, [PaymentStatusEnum::INITIATED->value, PaymentStatusEnum::PROCESSING->value])
            )
        ) {
            $status = true;
        }
        $this->unsetRelation('payment'); // unset to prevent accidental use
        // Otherwise, payment is needed
        return $status;
    }

    public function getPaymentUrlAttribute()
    {
        $url = null;
        if ($this->payment) {
            $url = $this->payment->payment_url;
        }
        $this->unsetRelation('payment'); // unset to prevent accidental use
        return $url;
    }

    //
}
