<?php

namespace App\Http\Controllers\Api\v1\Admin\Report\Shipping;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Seller\Product\ProductListing;
use Illuminate\Http\Request;
use PDF;

class ShippingReportBySellerAdminApiController extends ApiResponseWithAdminAuthController
{

    public function getShippingSellerReport(Request $request)
    {

        $start = $request->start_date ?? now()->subDay()->toDateString();
        $end = $request->end_date ?? now()->toDateString();

        // load listings
        $listings = ProductListing::with(['seller', 'listingItems.product', 'listingItems.listingPackages.shipmentPackages.shipment'])
            ->whereBetween('listing_date', [$start, $end])->get();

        // flatten packages
        $packages = $listings->flatMap->listingItems->flatMap->listingPackages;


        // product summary
        $productSummary = $this->summary($packages, fn($p) => $p->product->id . '_' . $p->pack_unit, false);


        // seller reports
        $sellerReports = $listings->groupBy('seller_id')->map(function ($l) {

            $sellerPackages = $l->flatMap->listingItems->flatMap->listingPackages;

            $items = $this->summary(
                $sellerPackages,
                fn($p) => $p->product->id . '_' . $p->pack_unit . '_' . $p->pack_type_unit . '_' . $p->pack_size,
                true
            );

            return [
                'seller' => $l->first()->seller,
                'items' => $items,
                'total' => $this->totals($items)
            ];
        })->values();


        // overall totals
        $grandTotals = $this->totals($productSummary);


        $res = [
            'filters' => ['start_date' => $start, 'end_date' => $end],
            'product_summary' => $productSummary,
            'seller_reports' => $sellerReports,
            'grand_totals' => $grandTotals
        ];

        if ($request->boolean('is_pdf_export')) return $this->pdf($res);

        return $this->successResponse(__('messages.success_messages.success_get'), $res);
    }



    // summary builder
    private function summary($pkgs, $group, $includePack)
    {

        return $pkgs->groupBy($group)->map(function ($g) use ($includePack) {

            $p = $g->first();

            $qty = $g->sum('qty');
            $sold = $g->sum('sold_qty') + $g->sum('demand_sold_qty');

            $ship = $g->flatMap->shipmentPackages
                ->filter(fn($s) => $s->shipment && $s->shipment->shipment_type == 'pickup')
                ->sum('qty');

            $row = [
                'product' => [
                    'product_code' => $p->product->product_code,
                    'name' => $p->product->name
                ],
                'pack_unit' => $p->pack_unit,
                'qty' => $qty,
                'sold_qty' => $sold,
                'shipped_qty' => $ship,
                'listed_weight' => $qty * $p->pack_size,
                'sold_weight' => $sold * $p->pack_size,
                'shipped_weight' => $ship * $p->pack_size
            ];

            if ($includePack) {
                $row['pack_size'] = $p->pack_size;
                $row['pack_type_unit'] = $p->pack_type_unit;
            }

            return $row;
        })->values();
    }



    // totals grouped by unit
    private function totals($rows)
    {

        return collect($rows)->groupBy('pack_unit')->map(function ($g) {

            return [
                'pack_unit' => $g->first()['pack_unit'],
                'qty' => $g->sum('qty'),
                'sold_qty' => $g->sum('sold_qty'),
                'shipped_qty' => $g->sum('shipped_qty'),
                'listed_weight' => $g->sum('listed_weight'),
                'sold_weight' => $g->sum('sold_weight'),
                'shipped_weight' => $g->sum('shipped_weight')
            ];
        })->values();
    }



    // pdf export

    public function pdf($data)
    {
        $pdf = PDF::loadView('pdf.reports.shipping.shipping_report_seller', $data)
            ->setPaper('a4');

        $fileName = 'shipping_report_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->stream($fileName);
    }
}
