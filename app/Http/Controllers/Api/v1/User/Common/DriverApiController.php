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




    //
}
