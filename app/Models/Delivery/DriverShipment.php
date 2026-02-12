<?php

namespace App\Models\Delivery;

use App\Models\BaseModel;
use App\Models\Common\Shipment\Shipment;
use App\Models\User;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverShipment extends BaseModel
{
    //

    use SoftDeletes;


    protected $fillable = [
        'shipment_id',
        'driver_id',
        'driver_vehicle_id',

        'assigned_by',
        'assigned_at',
        'accepted_at',
        'rejected_at',
        'started_at',
        'completed_at',

        'vehicle_number',
        'status',

        'remarks',
    ];

    // casts 
    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // relationships
    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function driverShipmentPackages()
    {
        return $this->shipment()->with('shipmentGroups.driverShipmentPackages');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id')->select('id', 'name', 'user_code', 'nickname', 'charge_level_code');
    }

    public function driverVehicle()
    {
        return $this->belongsTo(DriverVehicle::class, 'driver_vehicle_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by')->select('id', 'name', 'user_code', 'nickname');
    }

    protected $appends = [

        // Charges summary for driver to accept or not 
        'shipment_payable',
    ];

    public function getShipmentPayableAttribute()
    {
        if (!$this->shipment) {
            return null;
        }

        $packages = $this->shipment
            ->shipmentGroups
            ->map(fn($g) => [
                'order_qty'  => $g->shipmentPackage->qty ?? 0,
                'pack_size'  => $g->shipmentPackage->pack_size ?? 0,
                'pack_price' => $g->shipmentPackage->pack_price ?? 0,
                'pack_unit'  => $g->shipmentPackage->pack_unit ?? '',
                'pack_type_unit' => $g->shipmentPackage->pack_type_unit ?? null,
            ]);

        $orderAmount = $packages->sum(
            fn($pkg) => $pkg['order_qty'] * $pkg['pack_price']
        );

        $totalQty = $packages->sum('order_qty');
        $totalWeight = $packages->sum(
            fn($pkg) => $pkg['order_qty'] * $pkg['pack_size']
        );

        $chargeLevelCode = $this->driver?->charge_level_code;

        $service = app(ChargeCalculationService::class);

        // Driver payable estimate (delivery side)
        $deliveryEstimate = $service->calculateDeliveryCharges(
            $chargeLevelCode,
            $packages->toArray(),
            false,
            false
        );

        // Platform commission estimate (driver order amount = 0)
        $commissionEstimate = $service->calculatePlatformFee(
            $chargeLevelCode,
            0,
            $packages->toArray()
        );

        $driverPayableEstimate = $deliveryEstimate['total_charge_amount'] ?? 0;
        $platformCommissionEstimate = $commissionEstimate['total_amount'] ?? 0;

        $approxDriverPayable = round(
            $driverPayableEstimate - $platformCommissionEstimate,
            2
        );

        return [
            'total_quantity' => $totalQty,
            'total_weight'   => $totalWeight,

            // only reference, not financial
            'order_amount' => round($orderAmount, 2),

            // 🟡 ESTIMATIONS ONLY
            // 'driver_payable'      => $deliveryEstimate,
            // 'platform_commission' => $commissionEstimate,

            'delivery_charge_amount' => round($driverPayableEstimate, 2),
            'platform_commission_amount' => round($platformCommissionEstimate, 2),     


            // final approx number
            'final_payable_amount' => $approxDriverPayable,
        ];
    }


    //
}
