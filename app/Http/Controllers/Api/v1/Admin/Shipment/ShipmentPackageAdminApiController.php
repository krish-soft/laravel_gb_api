<?php

namespace App\Http\Controllers\Api\v1\Admin\Shipment;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Shipment\ShipmentPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShipmentPackageAdminApiController extends ApiResponseWithAdminAuthController
{
    public function summaryReport(Request $request)
    {

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDay()->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();


        /*
        |--------------------------------------------------------------------------
        | BASE PACKAGE QUERY WITH FULL RELATIONS
        |--------------------------------------------------------------------------
        */

        $packages = ShipmentPackage::query()
            ->with([

                'buyer',
                'seller',

                'product',
                'productVariant',

                'shipment',

                'shipment.originDepot.address',
                'shipment.destinationDepot.address',

                'shipment.originMarket',
                'shipment.destinationMarket',

                'shipment.originFulfillmentLocation.address',
                'shipment.destinationFulfillmentLocation.address',

            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();


        /*
        |--------------------------------------------------------------------------
        | STATUS SUMMARY
        |--------------------------------------------------------------------------
        */

        $statusSummary = $packages
            ->groupBy('status')
            ->map(function ($rows, $status) {

                return [
                    'status' => $status,
                    'total_packages' => $rows->count(),
                    'packages' => $rows->values()
                ];
            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | REGULAR DEPOT (Only when origin_depot = destination_depot, NO duplication)
        |--------------------------------------------------------------------------
        */
        $regularDepot = $packages
            ->filter(function ($p) {
                // Only single-depot packages (same origin & destination, or only one exists)
                if (!$p->shipment) {
                    return false;
                }
                
                $origin = $p->shipment->origin_depot_id;
                $destination = $p->shipment->destination_depot_id;
                
                // Include only if: both same, or only one exists
                if ($origin && $destination && $origin === $destination) {
                    return true; // Same depot
                }
                if ($origin && !$destination) {
                    return true; // Pickup only
                }
                if ($destination && !$origin) {
                    return true; // Delivery only
                }
                return false;
            })
            ->groupBy(function ($p) {
                $origin = $p->shipment->origin_depot_id;
                $destination = $p->shipment->destination_depot_id;
                
                // Determine depot and flow
                if ($origin && $destination && $origin === $destination) {
                    $depotId = $origin;
                    $flow = 'same_depot';
                } elseif ($origin && !$destination) {
                    $depotId = $origin;
                    $flow = 'pickup';
                } else {
                    $depotId = $destination;
                    $flow = 'delivery';
                }
                
                return "{$depotId}-{$flow}-{$p->status}";
            })
            ->map(function ($rows) {
                $first = $rows->first();
                $origin = $first->shipment->origin_depot_id;
                $destination = $first->shipment->destination_depot_id;
                
                // Get depot name based on flow
                if ($origin && $destination && $origin === $destination) {
                    $depotId = $origin;
                    $depotName = $first->shipment->originDepot?->name;
                    $flow = 'same_depot';
                } elseif ($origin && !$destination) {
                    $depotId = $origin;
                    $depotName = $first->shipment->originDepot?->name;
                    $flow = 'pickup';
                } else {
                    $depotId = $destination;
                    $depotName = $first->shipment->destinationDepot?->name;
                    $flow = 'delivery';
                }
                
                return [
                    'depot_id' => $depotId,
                    'depot_name' => $depotName,
                    'flow' => $flow,
                    'status' => $first->status,
                    'total_packages' => $rows->count(),
                    'packages' => $rows->values()
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | CROSS DEPOT (hub transfers - origin_depot ≠ destination_depot, NO from regularDepot)
        |--------------------------------------------------------------------------
        */
        $crossDepot = $packages
            ->filter(function ($p) {
                return $p->shipment
                    && $p->shipment->origin_depot_id
                    && $p->shipment->destination_depot_id
                    && $p->shipment->origin_depot_id !== $p->shipment->destination_depot_id;
            })
            ->groupBy(function ($p) {

                return $p->shipment->origin_depot_id
                    . '-' .
                    $p->shipment->destination_depot_id
                    . '-' .
                    $p->status;
            })
            ->map(function ($rows) {

                $first = $rows->first();

                return [

                    'pickup_depot_id' =>
                    $first->shipment->origin_depot_id,

                    'pickup_depot_name' =>
                    $first->shipment->originDepot?->name,

                    'shipping_depot_id' =>
                    $first->shipment->destination_depot_id,

                    'shipping_depot_name' =>
                    $first->shipment->destinationDepot?->name,

                    'status' => $first->status,

                    'total_packages' => $rows->count(),

                    'packages' => $rows->values()
                ];
            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | PACK SUMMARY
        |--------------------------------------------------------------------------
        */

        $packSummary = $packages
            ->groupBy(function ($p) {

                return $p->pack_size
                    . '-' .
                    $p->pack_unit
                    . '-' .
                    $p->pack_type_unit;
            })
            ->map(function ($rows) {

                $first = $rows->first();

                return [

                    'pack_size' => $first->pack_size,

                    'pack_unit' => $first->pack_unit,

                    'pack_type_unit' => $first->pack_type_unit,

                    'total_packages' => $rows->count(),

                    'total_weight' =>
                    $rows->sum(
                        fn($p) => $p->pack_size * $p->qty
                    ),

                    'packages' => $rows->values()
                ];
            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | LOGISTICS ACTION SUMMARY
        |--------------------------------------------------------------------------
        */

        $logistics = collect([

            [
                'action_type' => 'seller_dropoff_available',
                'description' => 'Seller can dropoff package',
                'packages' => $packages
                    ->where('is_seller_dropoff', true)
            ],

            [
                'action_type' => 'buyer_pickup_available',
                'description' => 'Buyer can pickup package',
                'packages' => $packages
                    ->where('is_buyer_pickup', true)
            ],

            [
                'action_type' => 'driver_pickup_needed',
                'description' => 'Driver pickup required (seller not dropoff)',
                'packages' => $packages
                    ->where('is_seller_dropoff', false)
            ],

            [
                'action_type' => 'driver_delivery_needed',
                'description' => 'Driver delivery required (buyer not pickup)',
                'packages' => $packages
                    ->where('is_buyer_pickup', false)
            ],

            [
                'action_type' => 'self_handled',
                'description' => 'Self-handled (seller dropoff + buyer pickup)',
                'packages' => $packages
                    ->where('is_seller_dropoff', true)
                    ->where('is_buyer_pickup', true)
            ]

        ])
            ->map(function ($row) {

                return [

                    'action_type' => $row['action_type'],

                    'description' => $row['description'],

                    'total_packages' =>
                    $row['packages']->count(),

                    'packages' =>
                    $row['packages']->values()
                ];
            });


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return $this->successResponse(
            'Shipment summary',
            [

                'filters' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString()
                ],

                'total_packages' => $packages->count(),

                'status_summary' => $statusSummary,

                'regular_depot_summary' => $regularDepot,

                'cross_depot_summary' => $crossDepot,

                'pack_combination_summary' => $packSummary,

                'logistics_action_summary' => $logistics,
            ]
        );
    }
}
