<?php

namespace App\Http\Controllers\Api\v1\Admin\Buyer\Order;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Buyer\Order\Order;
use Illuminate\Http\Request;

class AdminOrderApiController extends ApiResponseWithAdminAuthController
{
    //


    public function getOrdersList(Request $request)
    {
        //

        $orderQuery = Order::with(['buyer', 'shippingFulfillmentLocation'])->latest();

        if ($request->has('status')) {
            $orderQuery->where('status', $request->input('status'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $orderQuery->whereBetween('order_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $orders = $orderQuery->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $orders, 200);
    }



    public function getOrderDetails($orderId)
    {
        //

        $order = Order::with([
            'buyer',
            'orderItems',
            'orderItems.pickupFulfillmentLocation',

            'orderCharges',
            'shippingFulfillmentLocation', // actual shipping location
            'billingAddress', // for invoice
            'shippingAddress', // for invoice
        ])->where('id', $orderId)->firstOrfail();



        return $this->successResponse(__('messages.success_messages.success_get'), $order, 200);
    }







    //
}
