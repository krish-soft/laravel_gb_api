<?php

namespace App\Http\Controllers\Api\v1\Admin\Report\Shipping;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Shipment\Shipment;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ShippingReportByShipmentAdminApiController extends ApiResponseWithAdminAuthController
{

    public function getShippingShipmentReport(Request $request)
    {

        $request->validate([
            'depot_id' => 'nullable|exists:mst_depots,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $start = $request->start_date ?? now()->subDay()->toDateString();
        $end   = $request->end_date ?? now()->toDateString();



        /*
|--------------------------------------------------------------------------
| LOAD SHIPMENTS
|--------------------------------------------------------------------------
*/

        $shipments = Shipment::with(['shipmentPackages.product'])
            ->whereBetween('shipment_date', [$start, $end])
            ->when($request->filled('depot_id'), fn($q) => $q->where('depot_id', $request->depot_id))
            ->get();



        /*
|--------------------------------------------------------------------------
| FLATTEN PACKAGES
|--------------------------------------------------------------------------
*/

        $packages = $shipments->flatMap(function ($s) {

            return $s->shipmentPackages->map(function ($p) use ($s) {

                $fromAddress = implode(
                    ',',
                    array_filter(
                        array_diff_key($s->from_address ?? [], array_flip(['lat', 'lng']))
                    )
                );

                $toAddress = implode(
                    ',',
                    array_filter(
                        array_diff_key($s->to_address ?? [], array_flip(['lat', 'lng']))
                    )
                );

                return [

                    'shipment_id' => $s->id,
                    'shipment_type' => $s->shipment_type,
                    'status' => $s->status,

                    // 'from' => $s->from_address['addr_name'] ?? 'N/A',
                    // 'to' => $s->to_address['addr_name'] ?? 'N/A',

                    'from' => $fromAddress,
                    'to' => $toAddress,



                    'product' => [
                        'product_code' => $p->product->product_code ?? null,
                        'name' => $p->product->name ?? null
                    ],

                    'pack_size' => $p->pack_size,
                    'pack_unit' => $p->pack_unit,

                    'qty' => $p->qty,
                    'weight' => $p->qty * $p->pack_size

                ];
            });
        });



        /*
|--------------------------------------------------------------------------
| SHIPMENT SUMMARY
|--------------------------------------------------------------------------
*/

        $shipmentSummary = $shipments
            ->groupBy(fn($s) => $s->shipment_type . '_' . $s->status)
            ->map(function ($g) {

                $packages = $g->flatMap->shipmentPackages;

                return [

                    'shipment_type' => $g->first()->shipment_type,
                    'status' => $g->first()->status,

                    'shipments' => $g->count(),

                    'packages' => $packages->count(),

                    'qty' => $packages->sum('qty'),

                    'weight' => $packages->sum(fn($p) => $p->qty * $p->pack_size)

                ];
            })->values();



        /*
|--------------------------------------------------------------------------
| PRODUCT SUMMARY BY SHIPMENT TYPE
|--------------------------------------------------------------------------
*/

        $productSummary = $packages
            ->groupBy('shipment_type')
            ->map(function ($g, $type) {

                $products = $g
                    ->groupBy(fn($p) => $p['product']['product_code'] . '_' . $p['pack_unit'] . '_' . $p['pack_size'])
                    ->map(function ($x) {

                        $p = $x->first();

                        return [

                            'product' => $p['product'],

                            'pack_size' => $p['pack_size'],
                            'pack_unit' => $p['pack_unit'],

                            'packages' => $x->count(),

                            'qty' => $x->sum('qty'),

                            'weight' => $x->sum('weight')

                        ];
                    })->values();

                return [
                    'shipment_type' => $type,
                    'products' => $products
                ];
            })->values();



        /*
|--------------------------------------------------------------------------
| FLOW SUMMARY (FROM → TO)
|--------------------------------------------------------------------------
*/

        $flowSummary = $packages
            ->groupBy(fn($p) => $p['from'] . '_' . $p['to'])
            ->map(function ($g) {

                $p = $g->first();

                $products = $g
                    ->groupBy(fn($x) => $x['product']['product_code'] . '_' . $x['pack_size'])
                    ->map(function ($x) {

                        $p = $x->first();

                        return [

                            'product' => $p['product'],
                            'pack_size' => $p['pack_size'],
                            'pack_unit' => $p['pack_unit'],

                            'qty' => $x->sum('qty'),
                            'weight' => $x->sum('weight')

                        ];
                    })->values();

                return [

                    'from' => $p['from'],
                    'to' => $p['to'],

                    'shipments' => $g->unique('shipment_id')->count(),

                    'packages' => $g->count(),

                    'qty' => $g->sum('qty'),

                    'weight' => $g->sum('weight'),

                    'products' => $products

                ];
            })->values();



        /*
|--------------------------------------------------------------------------
| FLOW DETAILS
|--------------------------------------------------------------------------
*/

        $flowDetails = $shipments
            ->groupBy(fn($s) => ($s->from_address['addr_name'] ?? 'N/A') . '_' .
                ($s->to_address['addr_name'] ?? 'N/A'))
            ->map(function ($group) {

                $s = $group->first();

                $shipmentsData = $group->map(function ($s) {

                    $items = $s->shipmentPackages->map(function ($p) {

                        return [

                            'product' => [
                                'product_code' => $p->product->product_code ?? null,
                                'name' => $p->product->name ?? null
                            ],

                            'pack_size' => $p->pack_size,
                            'pack_unit' => $p->pack_unit,

                            'qty' => $p->qty,

                            'weight' => $p->qty * $p->pack_size

                        ];
                    });

                    return [

                        'shipment_number' => $s->shipment_number,
                        'shipment_date' => date('Y-m-d', strtotime($s->shipment_date)),

                        'shipment_type' => $s->shipment_type,
                        'status' => $s->status,

                        'items' => $items,

                        'total' => [
                            'packages' => $items->count(),
                            'qty' => $items->sum('qty'),
                            'weight' => $items->sum('weight')
                        ]

                    ];
                });

                return [

                    'from' => $s->from_address,
                    'to' => $s->to_address,

                    'shipments' => $shipmentsData

                ];
            })->values();



        /*
        |--------------------------------------------------------------------------
        | FROM LOCATION PRODUCT SUMMARY (PICKUPS)
        |--------------------------------------------------------------------------
        */

        $fromLocationSummary = $packages
            ->groupBy('from')
            ->map(function ($g, $location) {

                $products = $g
                    ->groupBy(fn($p) => $p['product']['product_code'] . '_' . $p['pack_size'])
                    ->map(function ($x) {

                        $p = $x->first();

                        return [

                            'product' => $p['product'],
                            'pack_size' => $p['pack_size'],
                            'pack_unit' => $p['pack_unit'],

                            'qty' => $x->sum('qty'),
                            'weight' => $x->sum('weight')

                        ];
                    })->values();

                return [

                    'location' => $location,

                    'shipments' => $g->unique('shipment_id')->count(),

                    'packages' => $g->count(),

                    'qty' => $g->sum('qty'),

                    'weight' => $g->sum('weight'),

                    'products' => $products

                ];
            })->values();



        /*
|--------------------------------------------------------------------------
| TO LOCATION PRODUCT SUMMARY (DELIVERIES)
|--------------------------------------------------------------------------
*/

        $toLocationSummary = $packages
            ->groupBy('to')
            ->map(function ($g, $location) {

                $products = $g
                    ->groupBy(fn($p) => $p['product']['product_code'] . '_' . $p['pack_size'])
                    ->map(function ($x) {

                        $p = $x->first();

                        return [

                            'product' => $p['product'],
                            'pack_size' => $p['pack_size'],
                            'pack_unit' => $p['pack_unit'],

                            'qty' => $x->sum('qty'),
                            'weight' => $x->sum('weight')

                        ];
                    })->values();

                return [

                    'location' => $location,

                    'shipments' => $g->unique('shipment_id')->count(),

                    'packages' => $g->count(),

                    'qty' => $g->sum('qty'),

                    'weight' => $g->sum('weight'),

                    'products' => $products

                ];
            })->values();



        /*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

        $res = [

            'filters' => [
                'start_date' => $start,
                'end_date' => $end
            ],

            'shipment_summary' => $shipmentSummary,

            'product_summary' => $productSummary,

            'flow_summary' => $flowSummary,

            'flow_details' => $flowDetails,

            'from_location_summary' => $fromLocationSummary,

            'to_location_summary' => $toLocationSummary

        ];



        if ($request->boolean('is_pdf_export')) {

            return $this->successResponse(
                __('messages.success_messages.success_get'),
                $this->pdf($res)
            );
        }

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $res
        );
    }



    /*
|--------------------------------------------------------------------------
| PDF EXPORT
|--------------------------------------------------------------------------
*/

    public function pdf($data)
    {

        $pdf = Pdf::loadView(
            'pdf.reports.shipping.shipping_report_shipment',
            $data
        )->setPaper('a4');

        return storeFileWithSignedUrl($pdf->output());
    }
}
