<?php

namespace App\Http\Controllers\Api\v1\User\Common;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DriverApiController extends ApiResponseWithAuthController
{
    //

    /**
     *  Driver Related APIs
     */

    public function updateDriverOnlineOffline(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'is_available_for_delivery' => 'required|boolean',
        ]);

        // manage on middleware
        // if (!$user->isDelivery()) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }


        $user->update([
            'is_available_for_delivery' => $request->input('is_available_for_delivery'), // Toggle the online status
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    public function getDriverOnlineOfflineStatus(Request $request)
    {
        $user = $request->user();

        // manage on middleware
        // if (!$user->isDelivery()) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }

        return $this->successResponse(__('messages.success_messages.success_get'), [
            'is_available_for_delivery' => $user->is_available_for_delivery,
        ], 200);
    }


    public function updateDriverLastLocation(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'driver_shipment_id' => 'nullable|integer|exists:driver_shipments,id',
        ]);

        // manage on middleware
        // if (!$user->isDelivery()) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }

        $location = $user->driverLocation()->updateOrCreate(
            ['driver_id' => $user->id],
            [
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'driver_shipment_id' => $request->input('driver_shipment_id'),
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }



    //
}
