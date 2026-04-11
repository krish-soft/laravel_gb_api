<?php

namespace App\Http\Controllers\Api\v1\Admin\Report\Shipping;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\DemandOrder;
use Illuminate\Http\Request;
use PDF;

class ShippingReportByBuyerAdminApiController extends ApiResponseWithAdminAuthController
{

    public function getShippingBuyerReport(Request $request)
    {

        $start = $request->start_date ?? now()->subDay()->toDateString();
        $end   = $request->end_date ?? now()->toDateString();


        /*
        |--------------------------------------------------------------------------
        | LOAD ORDERS
        |--------------------------------------------------------------------------
        */

        $orders = Order::with([
            'buyer',
            'orderItems.shipmentPackages.shipment'
        ])
            ->whereBetween('order_date', [$start, $end])
            ->get();


        /*
        |--------------------------------------------------------------------------
        | LOAD DEMAND ORDERS
        |--------------------------------------------------------------------------
        */

        $demandOrders = DemandOrder::with([
            'buyer',
            'demandOrderItems.shipmentPackages.shipment'
        ])
            ->whereBetween('order_date', [$start, $end])
            ->get();


        /*
        |--------------------------------------------------------------------------
        | NORMALIZE ITEMS
        |--------------------------------------------------------------------------
        */

        $orderItems = $orders->flatMap(function ($order) {

            return $order->orderItems->map(function ($item) use ($order) {

                return (object)[
                    'source_type'   => 'order',
                    'source_number' => $order->order_number,
                    'buyer_id'      => $order->buyer_id,
                    'buyer'         => $order->buyer,

                    'product_id'    => $item->product_id,
                    'product_code'  => $item->product_code,
                    'product_name'  => $item->product_name,

                    'pack_size'     => $item->pack_size,
                    'pack_unit'     => $item->pack_unit,
                    'pack_type_unit' => $item->pack_type_unit,

                    'qty'           => $item->order_qty,
                    'amount'        => $item->total_amount,

                    'shipmentPackages' => $item->shipmentPackages
                ];
            });
        });


        $demandItems = $demandOrders->flatMap(function ($order) {

            return $order->demandOrderItems->map(function ($item) use ($order) {

                return (object)[
                    'source_type'   => 'demand_order',
                    'source_number' => $order->order_number,
                    'buyer_id'      => $order->buyer_id,
                    'buyer'         => $order->buyer,

                    'product_id'    => $item->product_id,
                    'product_code'  => $item->product_code,
                    'product_name'  => $item->product_name,

                    'pack_size'     => $item->pack_size,
                    'pack_unit'     => $item->pack_unit,
                    'pack_type_unit' => $item->pack_type_unit,

                    'qty'           => $item->order_qty,
                    'amount'        => $item->total_amount,

                    'shipmentPackages' => $item->shipmentPackages
                ];
            });
        });


        $items = $orderItems->merge($demandItems);


        /*
        |--------------------------------------------------------------------------
        | PRODUCT SUMMARY
        |--------------------------------------------------------------------------
        */

        $productSummary = $this->summary(
            $items,
            fn($i) => $i->product_id . '_' . $i->pack_unit,
            false
        );


        /*
        |--------------------------------------------------------------------------
        | BUYER REPORTS
        |--------------------------------------------------------------------------
        */

        $buyerReports = $items
            ->groupBy('buyer_id')
            ->map(function ($buyerItems) {

                $items = $this->summary(
                    $buyerItems,
                    fn($i) => $i->product_id . '_' . $i->pack_unit . '_' . $i->pack_type_unit . '_' . $i->pack_size,
                    true
                );

                return [
                    'buyer' => $buyerItems->first()->buyer,
                    'items' => $items,
                    'total' => $this->totals($items)
                ];
            })
            ->values();


        $grandTotals = $this->totals($productSummary);


        $res = [

            'filters' => [
                'start_date' => $start,
                'end_date'   => $end
            ],

            'product_summary' => $productSummary,

            'buyer_reports' => $buyerReports,

            'grand_totals' => $grandTotals
        ];


        if ($request->boolean('is_pdf_export')) {

            return $this->successResponse(
                __('messages.success_messages.success_get'),
                $this->pdf($res)
            );
        }

        return $this->successResponse(__('messages.success_messages.success_get'), $res);
    }


    /*
    |--------------------------------------------------------------------------
    | SUMMARY BUILDER
    |--------------------------------------------------------------------------
    */

    private function summary($items, $group, $includePack)
    {

        return $items->groupBy($group)->map(function ($g) use ($includePack) {

            $i = $g->first();

            $qty = $g->sum('qty');

            $ship = $g->flatMap->shipmentPackages
                ->filter(fn($s) => $s->shipment)
                ->sum('qty');

            $amount = $g->sum('amount');

            $row = [

                'source_type'   => $i->source_type,
                'source_number' => $i->source_number,

                'product' => [
                    'product_code' => $i->product_code,
                    'name' => $i->product_name
                ],

                'pack_unit' => $i->pack_unit,

                'qty' => $qty,

                'shipped_qty' => $ship,

                'weight' => $qty * $i->pack_size,

                'shipped_weight' => $ship * $i->pack_size,

                'amount' => $amount
            ];


            if ($includePack) {

                $row['pack_size'] = $i->pack_size;

                $row['pack_type_unit'] = $i->pack_type_unit;
            }

            return $row;
        })->values();
    }


    /*
    |--------------------------------------------------------------------------
    | TOTALS
    |--------------------------------------------------------------------------
    */

    private function totals($rows)
    {

        return collect($rows)
            ->groupBy('pack_unit')
            ->map(function ($g) {

                return [

                    'pack_unit' => $g->first()['pack_unit'],

                    'qty' => $g->sum('qty'),

                    'shipped_qty' => $g->sum('shipped_qty'),

                    'weight' => $g->sum('weight'),

                    'shipped_weight' => $g->sum('shipped_weight'),

                    'amount' => $g->sum('amount')

                ];
            })
            ->values();
    }


    /*
    |--------------------------------------------------------------------------
    | PDF EXPORT
    |--------------------------------------------------------------------------
    */

    public function pdf($data)
    {

        $pdf = PDF::loadView(
            'pdf.reports.shipping.shipping_report_buyer',
            $data
        )->setPaper('a4');

        return storeFileWithSignedUrl($pdf->output());
    }
}
