<?php

namespace App\Http\Controllers\Api\v1\Admin\Shipment;

use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackageGroup;
use App\Services\Common\Shipment\ShipmentService;
use Illuminate\Http\Request;

class ShipmentAdminApiController extends ApiResponseWithAdminAuthController
{
    //





    // List Shipments
    public function index(Request $request)
    {
        $request->validate([
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'shipment_type' => 'nullable|string|in:pickup,dispatch,transfer',
            'status'        => 'nullable|string|in:' . implode(',', ShipmentStatusEnum::casesAsValues()),
        ]);

        $shipments = Shipment::query()
            ->latest()
            ->with([
                'buyer',
                'seller',

                'originFulfillmentLocation.address',
                'destinationFulfillmentLocation.address',

                'originDepot',
                'destinationDepot',

                'originMarket',
                'destinationMarket',

                'shipmentPackages.buyer',
                'shipmentPackages.seller',
                'shipmentPackages.market',

            ])
            ->when(
                $request->filled('start_date') && $request->filled('end_date'),
                fn($q) => $q->whereBetween('shipment_date', [
                    $request->input('start_date'),
                    $request->input('end_date'),
                ])
            )
            ->when(
                $request->filled('shipment_type'),
                fn($q) => $q->where('shipment_type', $request->input('shipment_type'))
            )
            ->when(
                $request->filled('status'),
                fn($q) => $q->where('status', $request->input('status'))
            )
            ->get();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $shipments
        );
    }



    public function show(Request $request, Shipment $shipment)
    {

        $shipment->load(['shipmentPackages', 'pickupDepot', 'shippingDepot']);

        return $this->successResponse(__('messages.success_messages.success_get'), $shipment);
    }

    // Update Shipment Status
    public function updateShipmentStatus(Request $request, Shipment $shipment)
    {
        $request->validate([
            'status' => 'required|string|in:' . implode(',', ShipmentStatusEnum::casesAsValues()),
        ]);

        // Validate status transition logic
        $shipment->status = $request->input('status');
        $shipment->save();


        return $this->successResponse(__('messages.success_messages.success_update'), $shipment);
    }




    //
}
