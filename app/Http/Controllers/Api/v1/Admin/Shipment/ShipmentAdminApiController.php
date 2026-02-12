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



    public function generateShipmentAndGroups(Request $request)
    {
        $request->validate([
            'shipment_type' => 'required|string|in:pickup,dispatch,transfer',
        ]);

        $shipmentType = $request->input('shipment_type');

        $result = app(ShipmentService::class)
            ->createShipmentAndGroups($shipmentType);

        return $this->successResponse(__('messages.success_messages.success_create'), $result);
    }



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
            ->with([
                'buyer',
                'seller',

                'originFulfillmentLocation.address',
                'destinationFulfillmentLocation.address',

                'originDepot',
                'destinationDepot',

                'shipmentGroups.shipmentPackage.buyer',
                'shipmentGroups.shipmentPackage.seller',
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
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $shipments
        );
    }



    public function show(Request $request, Shipment $shipment)
    {

        $shipment->load(['shipmentGroups.shipmentPackage', 'pickupDepot', 'shippingDepot']);

        return $this->successResponse(__('messages.success_messages.success_get'), $shipment);
    }


    /*
|--------------------------------------------------------------------------
| SPLIT GROUP (ADMIN)
|--------------------------------------------------------------------------
*/
    public function splitGroup(Request $request)
    {
        $request->validate([
            'group_number' => 'required|string',
            'package_ids'  => 'required|array',
            'package_ids.*' => 'integer|exists:shipment_packages,id',
        ]);

        $newGroup = app(ShipmentService::class)->splitGroup(
            $request->group_number,
            $request->package_ids
        );

        return $this->successResponse(
            __('messages.success_messages.success_update'),
            ['new_group_number' => $newGroup]
        );
    }


    /*
|--------------------------------------------------------------------------
| MOVE PACKAGE TO ANOTHER GROUP
|--------------------------------------------------------------------------
*/
    public function movePackage(Request $request)
    {
        $request->validate([
            'package_id'   => 'required|integer|exists:shipment_packages,id',
            'group_number' => 'required|string',
        ]);

        app(ShipmentService::class)->movePackage(
            $request->package_id,
            $request->group_number
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }


    /*
|--------------------------------------------------------------------------
| MERGE GROUPS
|--------------------------------------------------------------------------
*/
    public function mergeGroups(Request $request)
    {
        $request->validate([
            'from_group' => 'required|string',
            'to_group'   => 'required|string',
        ]);

        app(ShipmentService::class)->mergeGroups(
            $request->from_group,
            $request->to_group
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }



    public function mergeShipments(Request $request)
    {
        $request->validate([
            'from_shipment_id' => 'required|integer|exists:shipments,id',
            'to_shipment_id'   => 'required|integer|exists:shipments,id',
        ]);

        app(ShipmentService::class)->mergeShipments(
            $request->from_shipment_id,
            $request->to_shipment_id
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }



    /*
|--------------------------------------------------------------------------
| REBUILD SHIPMENT GROUPING
|--------------------------------------------------------------------------
*/
    public function rebuildShipment(Shipment $shipment)
    {
        $result = app(ShipmentService::class)
            ->rebuildGrouping($shipment->id);

        return $this->successResponse(
            __('messages.success_messages.success_update'),
            $result
        );
    }


    /*
|--------------------------------------------------------------------------
| DELETE SHIPMENT (SOFT)
|--------------------------------------------------------------------------
*/
    public function destroy(Shipment $shipment)
    {
        $shipment->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }


    /*
|--------------------------------------------------------------------------
| GET SINGLE GROUP PACKAGES
|--------------------------------------------------------------------------
*/
    public function getGroupPackages(string $groupNumber)
    {
        $groups = ShipmentPackageGroup::query()
            ->where('group_number', $groupNumber)
            ->with([
                'shipmentPackage:id,shipment_package_number,package_number,buyer_id,seller_id,status,pack_size,pack_unit',
                'shipmentPackage.buyer:id,name,user_code,nickname',
                'shipmentPackage.seller:id,name,user_code,nickname',
            ])
            ->get();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $groups
        );
    }




    //
}
