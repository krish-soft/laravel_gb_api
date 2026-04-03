<?php

namespace App\Models\Market;

use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Market\MstMarket;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
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
        'is_cutoff', // when order is processed for cutoff

        'flags',
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
        'is_cutoff' => 'boolean',
        'flags' => 'array',
    ];

    public function scopeEligibleForAccounting(Builder $query): Builder
    {
        return $query
            ->whereIn('order_status', [
                OrderStatusEnum::CONFIRMED->value,
                // OrderStatusEnum::ACCOUNTED->value,
                // OrderStatusEnum::INVOICED->value,
            ]);
        // ->where('delivery_status', OrderStatusEnum::DELIVERED->value)
        // ->where('payment_status', PaymentStatusEnum::PAID->value);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function updateDeliveryStatusFromPackages(): void
    {
        $packages = $this->shipmentPackages
            ->filter(function ($package) {
                return $package->shipment &&
                    in_array($package->shipment->shipment_type, [
                        ShipmentTypeEnum::DISPATCH->value,
                    ]);
            });

        $counts = $packages->countBy('status');

        $pending = $counts[OrderStatusEnum::PENDING->value] ?? 0;
        $delivered = $counts[OrderStatusEnum::DELIVERED->value] ?? 0;

        if ($pending <= 0 && $delivered > 0) {
            $this->delivery_status = OrderStatusEnum::DELIVERED->value;
            $this->save();
        }
    }

    public function isEligibleForAccounting(): bool
    {
        return in_array($this->order_status, [
            OrderStatusEnum::CONFIRMED->value,
            OrderStatusEnum::ACCOUNTED->value,
            OrderStatusEnum::INVOICED->value,
        ])
            && $this->delivery_status === OrderStatusEnum::DELIVERED->value;
        // && $this->payment_status === PaymentStatusEnum::PAID->value; // market order never gone come as paid
    }


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

    public function marketOrderDocuments()
    {
        return $this->hasMany(MarketOrderDocument::class, 'market_order_id');
    }

    public function shippingFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'shipping_fulfillment_location_id', 'id');
    }


    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'source_id')->where('source', MarketOrder::class);
    }



    // Methods for adding and removing flags

    public function addFlag(OrderFlagsEum $flag, ?string $reason = null): void
    {
        $flags = $this->flags ?? [];

        $value = $reason
            ? "{$flag->value}: {$reason}"
            : $flag->value;

        if (!in_array($value, $flags)) {
            $flags[] = $value;
            $this->flags = array_values($flags);
            $this->save();
        }
    }

    public function removeFlag(OrderFlagsEum $flag): void
    {
        $flags = collect($this->flags ?? [])
            ->reject(fn($f) => str_starts_with($f, $flag->value))
            ->values()
            ->toArray();

        $this->flags = $flags;
        $this->save();
    }









    // 
}
