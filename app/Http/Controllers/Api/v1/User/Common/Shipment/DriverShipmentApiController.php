<?php

namespace App\Http\Controllers\Api\v1\User\Common\Shipment;

use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Delivery\DriverShipment;

class DriverShipmentApiController extends ApiResponseWithAuthController
{

    /*
    |--------------------------------------------------------------------------
    | DRIVER SHIPMENT LIST (FOR MOBILE HOME)
    |--------------------------------------------------------------------------
    */
    public function getShipments()
    {
        $driverId = request()->user()->id;

        // Also multiple shipments then how to group and show one

        $shipments = DriverShipment::with(['shipment', 'shipment.shipmentGroups', 'driverVehicle'])
            ->where('driver_id', $driverId)
            ->where('status', '!=', DriverShipmentStatusEnum::COMPLETED->value)
            ->orderByDesc('assigned_at')
            ->get();

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

        $driverShipment->load([
            'shipment.shipmentGroups.shipmentPackage.buyer:id,name,nickname',
            'shipment.shipmentGroups.shipmentPackage.seller:id,name,nickname',
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

        if ($driverShipment->accepted_at) {
            return $this->showSuccessMessage("Already accepted.");
        }

        $driverShipment->update([
            'accepted_at' => now(),
            'status' => DriverShipmentStatusEnum::ACCEPTED->value,
        ]);

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

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }
}
