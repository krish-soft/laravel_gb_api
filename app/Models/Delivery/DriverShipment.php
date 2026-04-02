<?php

namespace App\Models\Delivery;

use App\Models\BaseModel;
use App\Models\Common\Rating\DriverRating;
use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Common\Shipment\ShipmentPackageGroup;
use App\Models\User;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

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
        'proof_image_path',
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

    // public function shipmentPackages()
    // {
    //     return $this->hasMany(ShipmentPackage::class, 'shipment_id', 'shipment_id');
    // }



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

    public function driverRatings()
    {
        return $this->hasMany(DriverRating::class, 'driver_shipment_id', 'id');
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

        $packages = $this->shipment->shipmentPackages;

        if ($packages->isEmpty()) {
            return [
                'total_quantity' => 0,
                'total_weight' => 0,
                'order_amount' => 0,
                'delivery_charge_amount' => 0,
                'platform_commission_amount' => 0,
                'final_payable_amount' => 0,
            ];
        }

        $totalQty = 0;
        $totalWeight = 0;
        $orderAmount = 0;

        $packageData = [];

        foreach ($packages as $p) {

            $qty = $p->qty ?? 0;
            $packSize = $p->pack_size ?? 0;
            $packPrice = $p->pack_price ?? 0;

            $totalQty += $qty;
            $totalWeight += $qty * $packSize;
            $orderAmount += $qty * $packPrice;

            $packageData[] = [
                'order_qty' => $qty,
                'pack_size' => $packSize,
                'pack_price' => $packPrice,
                'pack_unit' => $p->pack_unit ?? '',
                'pack_type_unit' => $p->pack_type_unit ?? null,
            ];
        }

        $chargeLevelCode = $this->driver?->charge_level_code;

        $service = app(ChargeCalculationService::class);

        $deliveryEstimate = $service->calculateDeliveryCharges(
            $chargeLevelCode,
            $packageData,
            false,
            false
        );

        $commissionEstimate = $service->calculatePlatformFee(
            $chargeLevelCode,
            0,
            $packageData
        );

        $driverPayableEstimate = $deliveryEstimate['total_charge_amount'] ?? 0;
        $platformCommissionEstimate = $commissionEstimate['total_amount'] ?? 0;

        $approxDriverPayable = round(
            $driverPayableEstimate - $platformCommissionEstimate,
            2
        );

        // Log::info('Driver Shipment Payable Estimate', [
        //     'driver_shipment_id' => $this->id,
        //     'shipment_id' => $this->shipment_id,
        //     'driver_id' => $this->driver_id,
        //     'charge_level_code' => $chargeLevelCode,
        //     'total_qty' => $totalQty,
        //     'total_weight' => $totalWeight,
        //     'order_amount' => $orderAmount,
        //     'delivery_charge_estimate' => $driverPayableEstimate,
        //     'platform_commission_estimate' => $platformCommissionEstimate,
        //     'approx_driver_payable' => $approxDriverPayable,
        // ]);

        return [
            'total_quantity' => $totalQty,
            'total_weight' => $totalWeight,
            'order_amount' => round($orderAmount, 2),

            'delivery_charge_amount' => round($driverPayableEstimate, 2),
            'platform_commission_amount' => round($platformCommissionEstimate, 2),

            'final_payable_amount' => $approxDriverPayable,
        ];
    }
    //
}
