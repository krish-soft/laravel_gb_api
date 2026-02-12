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
            'shipment.originFulfillmentLocation.address',
            'shipment.destinationFulfillmentLocation.address',
            'shipment.originDepot',
            'shipment.destinationDepot',
            'shipment.shipmentGroups.shipmentPackage',
            'shipment.shipmentGroups.shipmentPackage.buyer',
            'shipment.shipmentGroups.shipmentPackage.seller',
        ])
            ->where('driver_id', $request->user()->id)
            ->where('status', '!=', DriverShipmentStatusEnum::CANCELLED->value);

        $start = $request->filled('start_date')
            ? now()->parse($request->start_date)->startOfDay()
            : now()->startOfDay();

        $end = $request->filled('end_date')
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $query->whereBetween('assigned_at', [$start, $end]);

        $driverShipments = $query->get();

        /*
    |--------------------------------------------------------------------------
    | 🔥 BUILD DRIVER ROUTE LIST
    |--------------------------------------------------------------------------
    */

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

                    /*
                |--------------------------------------------------------------------------
                | ADDRESS RESOLUTION
                |--------------------------------------------------------------------------
                */
                    'origin' => [
                        'type' => $shipment->origin_type,
                        'name' =>
                        $shipment->originFulfillmentLocation?->address?->addr_name
                            ?? $shipment->originDepot?->name,
                        'lat'  =>
                        $shipment->originFulfillmentLocation?->address?->latitude
                            ?? $shipment->originDepot?->latitude,
                        'lng'  =>
                        $shipment->originFulfillmentLocation?->address?->longitude
                            ?? $shipment->originDepot?->longitude,
                    ],

                    'destination' => [
                        'type' => $shipment->destination_type,
                        'name' =>
                        $shipment->destinationFulfillmentLocation?->address?->addr_name
                            ?? $shipment->destinationDepot?->name,
                        'lat'  =>
                        $shipment->destinationFulfillmentLocation?->address?->latitude
                            ?? $shipment->destinationDepot?->latitude,
                        'lng'  =>
                        $shipment->destinationFulfillmentLocation?->address?->longitude
                            ?? $shipment->destinationDepot?->longitude,
                    ],

                    /*
                |--------------------------------------------------------------------------
                | PACKAGES SUMMARY
                |--------------------------------------------------------------------------
                */
                    'total_packages' => $shipment->shipmentGroups->count(),

                    'packages' => $shipment->shipmentGroups->map(function ($g) {

                        $p = $g->shipmentPackage;

                        return [
                            'package_number' => $p->package_number,
                            'buyer'  => $p->buyer?->nickname,
                            'seller' => $p->seller?->nickname,
                            'qty'    => $p->qty,
                            'pack_size' => $p->pack_size,
                            'unit'      => $p->pack_unit,
                        ];
                    }),
                ];
            })

            /*
        |--------------------------------------------------------------------------
        | 🔥 SORT BY ROUTE SEQUENCE
        |--------------------------------------------------------------------------
        */
            ->sortBy(function ($item) use ($priority) {

                // completed goes bottom
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
    | SINGLE SHIPMENT DETAIL (PHONE DETAIL SCREEN)
    |--------------------------------------------------------------------------
    */
    public function shipmentDetails(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if (in_array($driverShipment->status, [DriverShipmentStatusEnum::CANCELLED->value, DriverShipmentStatusEnum::COMPLETED->value])) {
            return $this->errorResponse("This shipment has been cancelled/completed.", 410);
        }

        $driverShipment->load([
            'shipment.shipmentGroups.shipmentPackage',
        ]);

        return $this->successResponse(__('messages.success_messages.success_get'), $driverShipment);
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
