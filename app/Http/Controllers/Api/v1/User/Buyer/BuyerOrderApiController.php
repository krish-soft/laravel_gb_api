<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Buyer\Order\Order;
use Illuminate\Http\Request;

class BuyerOrderApiController extends ApiResponseWithAuthController
{
    //


    public function getBuyerOrders(Request $request)
    {
        $buyer = $request->user();

        $request->validate([
            'year'         => 'nullable|integer|min:2000|max:' . now()->year,
            'month'        => 'nullable|integer|min:1|max:12',
            'order_number' => 'nullable|string|max:50',
            'order_date'   => 'nullable|date',
            'status'       => 'nullable|string|max:50',
            'limit'        => 'nullable|integer|min:10|max:100',
            'offset'       => 'nullable|integer|min:0',
        ]);

        $limit  = min((int) $request->get('limit', 50), 100); // default 50
        $offset = (int) $request->get('offset', 0);

        $query = Order::query()
            ->select('id', 'order_number', 'order_date', 'order_status', 'delivery_status', 'payment_status', 'total_amount')
            ->where('buyer_id', $buyer->id)

            ->when($request->filled('year'), function ($q) use ($request) {
                $q->whereYear('order_date', $request->year);
            })

            ->when($request->filled('month'), function ($q) use ($request) {
                $q->whereMonth('order_date', $request->month);
            })

            ->when($request->filled('order_number'), function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->order_number . '%');
            })

            ->when($request->filled('order_date'), function ($q) use ($request) {
                $q->whereDate('order_date', $request->order_date);
            })

            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })

            ->orderByDesc('order_date');

        $orders = $query
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $orders
        );
    }


    public function getBuyerOrderDetails(Request $request, $orderId)
    {
        $buyer = $request->user();

        $order = Order::query()
            ->with([
                'orderItems',
                'orderCharges',
            ])
            ->where('buyer_id', $buyer->id)
            ->where('id', $orderId)
            ->firstOrFail();


        // unload relations to avoid n+1 problem
        $order->orderItems->each(function ($item) {
            // $item->setRelation('seller', null);
            // $item->setRelation('pickup_depot', null);
            $item->makeHidden(['seller', 'pickup_depot']); // what append need to hide
        });


        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $order
        );
    }


    public function getOrderShipmentPackages(Request $request, $orderId)
    {
        $buyer = $request->user();

        $order = Order::query()
            ->where('buyer_id', $buyer->id)
            ->where('id', $orderId)
            ->with(['shipmentPackages' => function ($query) {
                $query->select(
                    'id',
                    'order_id', // IMPORTANT (required for relation)
                    'product_name',
                    'qty',
                    'ship_qty',
                    'pack_size',
                    'pack_price',
                    'pack_unit',
                    'pack_type_unit',
                    'shipment_package_number',
                    'package_number',
                    'status',
                    'seller_status',
                    'buyer_status'
                );
            }])
            ->firstOrFail();

        $packages = $order->shipmentPackages
            ->makeHidden(['seller', 'buyer']); // hide unwanted relations if appended

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $packages
        );
    }


    //
}
