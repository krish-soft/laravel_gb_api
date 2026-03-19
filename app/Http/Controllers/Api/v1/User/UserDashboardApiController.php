<?php

namespace App\Http\Controllers\Api\v1\User;

use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserDashboardApiController extends ApiResponseWithAuthController
{
    //




    public function dashboard(Request $request)
    {
        $user = $request->user();

        $dashboardData = [];

        $dashboardData['summary'] = [
            // buyer
            'total_orders' => $user->buyerOrders()->count(), // buyer
            'total_followings' => $user->followings()->count(), // buyer
            // seller
            'total_product_listings' => $user->sellerProductListings()->count(), // seller
            'total_followers' => $user->followers()->count(), // seller

            // driver
            'total_deliveries' => $user->deliveryShipments()->count(), // driver
            'requested_deliveries' => $user->deliveryShipments()->whereIn('status', [DriverShipmentStatusEnum::REQUESTED->value, DriverShipmentStatusEnum::ASSIGNED->value])->count(), // driver
            'active_deliveries' => $user->deliveryShipments()->whereIn('status', [DriverShipmentStatusEnum::ACCEPTED->value, DriverShipmentStatusEnum::IN_TRANSIT->value])->count(), // driver

            'total_ratings' => $user->driverRatings()->count(), // driver
            'average_rating' => $user->driverRatings()->avg('rating'), // driver
        ];




        if ($user->account) {
            $dashboardData['earnings'] = [
                'available_balance' => $user->account->available_balance,
                'pending_balance' => $user->account->pending_balance,
            ];
        }



        return $this->successResponse(__('messages.success_messages.success_get'), $dashboardData);
    }








    //
}
