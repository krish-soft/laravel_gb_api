<?php

namespace App\Http\Controllers\Api\v1\User\Common\Shipment;

use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Delivery\DriverShipment;
use Illuminate\Http\Request;

class DriverShipmentApiController extends ApiResponseWithAuthController
{

    /*
    |--------------------------------------------------------------------------
    | DRIVER SHIPMENT LIST (FOR MOBILE HOME)
    |--------------------------------------------------------------------------
    */

    // Get All Shipments and prepare routes need all address or deliver
    public function getDeliverShipments(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shipment_number' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', DriverShipmentStatusEnum::casesAsValues()),
        ]);

        $query = DriverShipment::with([

            // 🔥 ONLY ROUTE DATA
            'shipment.originFulfillmentLocation.address',
            'shipment.destinationFulfillmentLocation.address',
            'shipment.originDepot.address',
            'shipment.destinationDepot.address',

            // 🔥 ONLY COUNT PURPOSE
            'shipment.shipmentGroups:id,shipment_id',
        ])
            ->where('driver_id', $request->user()->id)
            ->where('status', '!=', DriverShipmentStatusEnum::CANCELLED->value);

        $start = $request->filled('start_date')
            ? now()->parse($request->start_date)->startOfDay()
            : now()->startOfDay();

        $end = $request->filled('end_date')
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $driverShipments = $query
            ->whereBetween('assigned_at', [$start, $end])
            ->get();

        $priority = [
            'pickup'   => 1,
            'transfer' => 2,
            'dispatch' => 3,
        ];

        $routeList = $driverShipments
            ->map(function ($ds) {

                $shipment = $ds->shipment;

                return [
                    'driver_shipment_id' => $ds->id,
                    'shipment_id'        => $shipment->id,
                    'shipment_number'    => $shipment->shipment_number,
                    'shipment_type'      => $shipment->shipment_type,
                    'status'             => $shipment->status,

                    // 🔥 MODEL ACCESSOR (FAST)
                    'origin'      => $shipment->from_address,
                    'destination' => $shipment->to_address,

                    // 🔥 COUNT ONLY
                    'total_packages' => $shipment->shipmentGroups->count(),
                ];
            })
            ->sortBy(function ($item) use ($priority) {

                if ($item['status'] === 'completed') {
                    return 999;
                }

                return $priority[$item['shipment_type']] ?? 50;
            })
            ->values();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $routeList
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SINGLE SHIPMENT DETAIL (PHONE DETAIL SCREEN)
    |--------------------------------------------------------------------------
    */

    public function shipmentDetails(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if (in_array($driverShipment->status, [
            DriverShipmentStatusEnum::CANCELLED->value,
            DriverShipmentStatusEnum::COMPLETED->value
        ])) {
            return $this->errorResponse("This shipment has been cancelled/completed.", 410);
        }

        $driverShipment->load([

            'shipment.originFulfillmentLocation.address',
            'shipment.destinationFulfillmentLocation.address',
            'shipment.originDepot.address',
            'shipment.destinationDepot.address',

            'shipment.shipmentGroups.shipmentPackage.buyer.address',
            'shipment.shipmentGroups.shipmentPackage.seller.address',
        ]);

        $shipment = $driverShipment->shipment;

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'driver_shipment_id' => $driverShipment->id,
                'shipment_number' => $shipment->shipment_number,
                'shipment_type'   => $shipment->shipment_type,
                'status'          => $shipment->status,

                'origin'      => $shipment->from_address,
                'destination' => $shipment->to_address,

                'packages' => $shipment->shipmentGroups->map(function ($g) {

                    $p = $g->shipmentPackage;

                    return [
                        'shipment_group_id' => $g->id,     // 🔥 IMPORTANT
                        'shipment_package_id' => $p->id,   // 🔥 IMPORTANT
                        'package_number' => $p->package_number,
                        'qty' => $p->qty,
                        'pack_size' => $p->pack_size,
                        'unit' => $p->pack_unit,

                        'buyer' => [
                            'nickname' => $p->buyer?->nickname,
                            'phone' => $p->buyer?->address?->phone_number,
                            'address' => $p->buyer?->address?->address_line1,
                        ],

                        'seller' => [
                            'nickname' => $p->seller?->nickname,
                            'phone' => $p->seller?->address?->phone_number,
                            'address' => $p->seller?->address?->address_line1,
                        ],
                    ];
                })->values(),
            ]
        );
    }

    public function getAllShipments(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            // 'driver_id' => 'nullable|exists:users,id',
            'shipment_number' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', DriverShipmentStatusEnum::casesAsValues()),
        ]);

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 20);

        $query = DriverShipment::with([
            'driver',
            // 'driverVehicle',
            // 'shipment',
            'shipment.shipmentGroups.shipmentPackage',
            // 'shipment.shipmentGroups.shipmentPackage.buyer',
            // 'shipment.shipmentGroups.shipmentPackage.seller',

        ])
            ->where('driver_id', $request->user()->id);

        $start = $request->filled('start_date')
            ? now()->parse($request->start_date)->startOfDay()
            : now()->startOfDay();

        $end = $request->filled('end_date')
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $query->whereBetween('assigned_at', [$start, $end]);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->filled('shipment_number')) {
            $query->whereHas(
                'shipment',
                fn($q) =>
                $q->where('shipment_number', 'like', '%' . $request->shipment_number . '%')
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $shipments = $query->orderByDesc('assigned_at')->get()->slice($offset, $limit)->values();

        return $this->successResponse(__('messages.success_messages.success_get'), $shipments);
    }



    /*
    |--------------------------------------------------------------------------
    | ACCEPT
    |--------------------------------------------------------------------------
    */
    public function accept(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if ($driverShipment->rejected_at) {
            return $this->showSuccessMessage("Rejected shipment can not accept again.");
        }

        if ($driverShipment->accepted_at) {
            return $this->showSuccessMessage("Already accepted.");
        }

        $driverShipment->update([
            'accepted_at' => now(),
            'status' => DriverShipmentStatusEnum::ACCEPTED->value,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }


    public function reject(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if ($driverShipment->accepted_at) {
            return $this->showSuccessMessage("Already accepted. Can not reject now.");
        }

        if ($driverShipment->rejected_at) {
            return $this->showSuccessMessage("Already rejected.");
        }

        $driverShipment->update([
            'rejected_at' => now(),
            'status' => DriverShipmentStatusEnum::REJECTED->value,
        ]);

        // Make original shipment available for other drivers by setting driver_id to null 
        $shipment = $driverShipment->shipment;
        if ($shipment && $shipment->status === ShipmentStatusEnum::ASSIGNED->value) {
            $shipment->update(['status' => ShipmentStatusEnum::GROUPED->value]);
        }


        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /*
    |--------------------------------------------------------------------------
    | START DELIVERY
    |--------------------------------------------------------------------------
    */
    public function start(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if (!$driverShipment->accepted_at) {
            return $this->errorResponse("Must accept first.", 422);
        }

        $driverShipment->update([
            'started_at' => now(),
            'status' => DriverShipmentStatusEnum::IN_TRANSIT->value,
        ]);

        $driverShipment->shipment()->update([
            'status' => 'in_transit'
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETE DELIVERY
    |--------------------------------------------------------------------------
    */
    public function complete(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        $driverShipment->update([
            'completed_at' => now(),
            'status' => DriverShipmentStatusEnum::COMPLETED->value,
        ]);

        $driverShipment->shipment()->update([
            'status' => 'completed'
        ]);

        // Get Original Order here 
        // per shipment package group andper package get order and make order status completed if all shipment completed for that order


        // TODO: Trigger any post-delivery processes like notifications, payments, etc.


        // 1. If Pickup (Seller To Depot)

        // 2. Transfer (Depot To Depot) - if needed

        // 3. Dipatch (Depot To Customer Final Delivery)
        // Accoutning  final for all 




        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }




    // Shipment Images  Pending 













}
