<?php

namespace App\Http\Controllers\Api\v1\Admin\Report\Order;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\DemandOrder;
use Illuminate\Http\Request;

class SaleOrderReportAdminApiController extends ApiResponseWithAdminAuthController
{

    public function getSaleOrderReport(Request $request)
    {

        $request->validate([
            'depot_id'   => 'nullable|exists:mst_depots,id',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $startDate = $request->start_date ?? now()->subDay()->toDateString();
        $endDate   = $request->end_date ?? now()->toDateString();



        /*
        |--------------------------------------------------------------------------
        | LOAD ORDERS
        |--------------------------------------------------------------------------
        */

        $orders = Order::whereBetween('order_date', [$startDate, $endDate])
            ->when($request->filled('depot_id'), fn($q) => $q->where('depot_id', $request->depot_id))
            ->with(['orderItems.product'])
            ->get();



        /*
        |--------------------------------------------------------------------------
        | LOAD DEMAND ORDERS
        |--------------------------------------------------------------------------
        */

        $demandOrders = DemandOrder::whereBetween('order_date', [$startDate, $endDate])
            ->when($request->filled('depot_id'), fn($q) => $q->where('depot_id', $request->depot_id))
            ->with(['demandOrderItems.product'])
            ->get();



        /*
        |--------------------------------------------------------------------------
        | STATUS SUMMARY
        |--------------------------------------------------------------------------
        */

        $orderStatusSummary = $orders
            ->groupBy('order_status')
            ->map(fn($g) => [
                'status' => $g->first()->order_status,
                'count' => $g->count()
            ])->values();


        $demandStatusSummary = $demandOrders
            ->groupBy('order_status')
            ->map(fn($g) => [
                'status' => $g->first()->order_status,
                'count' => $g->count()
            ])->values();



        /*
        |--------------------------------------------------------------------------
        | AMOUNT SUMMARY
        |--------------------------------------------------------------------------
        */

        $orderAmountSummary = [
            'order_count' => $orders->count(),
            'base_amount' => $orders->sum('base_amount'),
            'tax_amount' => $orders->sum('tax_amount'),
            'total_amount' => $orders->sum('total_amount'),
        ];

        $demandAmountSummary = [
            'order_count' => $demandOrders->count(),
            'base_amount' => $demandOrders->sum('base_amount'),
            'tax_amount' => $demandOrders->sum('tax_amount'),
            'total_amount' => $demandOrders->sum('total_amount'),
        ];



        /*
        |--------------------------------------------------------------------------
        | PRODUCT ITEMS
        |--------------------------------------------------------------------------
        */

        $orderItems = $orders->flatMap->orderItems;
        $demandItems = $demandOrders->flatMap->demandOrderItems;

        $allItems = $orderItems->merge($demandItems);



        /*
        |--------------------------------------------------------------------------
        | PRODUCT SUMMARY
        |--------------------------------------------------------------------------
        */

        $orderProductSummary = $this->productSummary($orderItems);
        $demandProductSummary = $this->productSummary($demandItems);

        // NEW → overall product summary
        $overallProductSummary = $this->productSummary($allItems);



        /*
        |--------------------------------------------------------------------------
        | TOTALS BY UNIT
        |--------------------------------------------------------------------------
        */

        $totalsByUnit = collect($overallProductSummary)
            ->groupBy('pack_unit')
            ->map(function ($g) {

                return [
                    'pack_unit' => $g->first()['pack_unit'],
                    'qty' => $g->sum('qty'),
                    'weight' => $g->sum('weight'),
                    'amount' => $g->sum('amount')
                ];
            })->values();



        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        $res = [

            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],

            'status_summary' => [
                'orders' => $orderStatusSummary,
                'demand_orders' => $demandStatusSummary
            ],

            'amount_summary' => [
                'orders' => $orderAmountSummary,
                'demand_orders' => $demandAmountSummary
            ],

            'product_summary' => [
                'orders' => $orderProductSummary,
                'demand_orders' => $demandProductSummary,
                'overall' => $overallProductSummary,
                'totals_by_unit' => $totalsByUnit
            ]

        ];



        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $res
        );
    }



    /*
    |--------------------------------------------------------------------------
    | PRODUCT SUMMARY
    |--------------------------------------------------------------------------
    */

    private function productSummary($items)
    {

        return $items
            ->groupBy(fn($i) => $i->product_id . '_' . $i->pack_unit . '_' . $i->pack_size)
            ->map(function ($g) {

                $i = $g->first();

                $qty = $g->sum('order_qty');
                $amount = $g->sum('total_amount');

                return [

                    'product' => [
                        'product_code' => $i->product_code,
                        'name' => $i->product_name
                    ],

                    'pack_size' => $i->pack_size,
                    'pack_unit' => $i->pack_unit,

                    'qty' => $qty,
                    'weight' => $qty * $i->pack_size,
                    'amount' => $amount

                ];
            })->values();
    }
}
