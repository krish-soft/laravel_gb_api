<?php

namespace App\Http\Controllers\Api\v1\Admin\Shipment;

use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Shipment\ShipmentPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShipmentPackageAdminApiController extends ApiResponseWithAdminAuthController
{



    //
    public function summaryReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'depot_id'   => 'nullable|integer',
        ]);

        $startDate = $request->filled('start_date')
            ? \Carbon\Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $endDate = $request->filled('end_date')
            ? \Carbon\Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $depotId = $request->input('depot_id');

        /*
    |--------------------------------------------------------------------------
    | BASE QUERY
    |--------------------------------------------------------------------------
    */
        $base = ShipmentPackage::query()
            ->with([
                'pickupDepot:id,name',
                'shippingDepot:id,name',
                'buyer:id,name,nickname',
                'seller:id,name,nickname',
            ])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($depotId) {
            $base->where(function ($q) use ($depotId) {
                $q->where('pickup_depot_id', $depotId)
                    ->orWhere('shipping_depot_id', $depotId);
            });
        }

        /*
    |--------------------------------------------------------------------------
    | ✅ STATUS SUMMARY (ONLY PACKAGE COUNT)
    |--------------------------------------------------------------------------
    */
        $statusSummary = (clone $base)
            ->selectRaw('
            status,
            COUNT(*) as total_packages
        ')
            ->groupBy('status')
            ->get()
            ->map(function ($row) use ($base) {

                $packages = (clone $base)
                    ->where('status', $row->status)
                    ->get();

                return [
                    'status'         => $row->status,
                    'total_packages' => (int)$row->total_packages,
                    'packages'       => $packages,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | ✅ PACK COMBINATION SUMMARY (ONLY PLACE WHERE WEIGHT EXISTS)
    |--------------------------------------------------------------------------
    */
        $packCombinationSummary = (clone $base)
            ->selectRaw('
            pack_size,
            pack_unit,
            pack_type_unit,
            COUNT(*) as total_packages,
            COALESCE(SUM(pack_size),0) as total_weight
        ')
            ->groupBy('pack_size', 'pack_unit', 'pack_type_unit')
            ->orderBy('pack_size')
            ->get()
            ->map(function ($row) use ($base) {

                $packages = (clone $base)
                    ->where('pack_size', $row->pack_size)
                    ->where('pack_unit', $row->pack_unit)
                    ->where('pack_type_unit', $row->pack_type_unit)
                    ->get();

                return [
                    'pack_size'       => $row->pack_size,
                    'pack_unit'       => $row->pack_unit,
                    'pack_type_unit'  => $row->pack_type_unit,
                    'total_packages'  => (int)$row->total_packages,
                    'total_weight'    => (float)$row->total_weight,
                    'packages'        => $packages,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | ✅ REGULAR DEPOT SUMMARY (ONLY COUNT)
    |--------------------------------------------------------------------------
    */
        $regularDepotSummary = (clone $base)
            ->whereNotNull('pickup_depot_id')
            ->whereColumn('pickup_depot_id', '=', 'shipping_depot_id')
            ->selectRaw('
            pickup_depot_id,
            status,
            COUNT(*) as total_packages
        ')
            ->groupBy('pickup_depot_id', 'status')
            ->with('pickupDepot:id,name')
            ->get()
            ->map(function ($row) use ($base) {

                $packages = (clone $base)
                    ->whereColumn('pickup_depot_id', '=', 'shipping_depot_id')
                    ->where('pickup_depot_id', $row->pickup_depot_id)
                    ->where('status', $row->status)
                    ->get();

                return [
                    'depot_id'        => $row->pickup_depot_id,
                    'depot_name'      => $row->pickupDepot->name ?? 'N/A',
                    'status'          => $row->status,
                    'total_packages'  => (int)$row->total_packages,
                    'packages'        => $packages,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | ✅ CROSS DEPOT SUMMARY (ONLY COUNT)
    |--------------------------------------------------------------------------
    */
        $crossDepotSummary = (clone $base)
            ->where(function ($q) {
                $q->whereColumn('pickup_depot_id', '!=', 'shipping_depot_id')
                    ->orWhereNull('pickup_depot_id')
                    ->orWhereNull('shipping_depot_id');
            })
            ->selectRaw('
            pickup_depot_id,
            shipping_depot_id,
            status,
            COUNT(*) as total_packages
        ')
            ->groupBy('pickup_depot_id', 'shipping_depot_id', 'status')
            ->with(['pickupDepot:id,name', 'shippingDepot:id,name'])
            ->get()
            ->map(function ($row) use ($base) {

                $packages = (clone $base)
                    ->where('status', $row->status)
                    ->where('pickup_depot_id', $row->pickup_depot_id)
                    ->where('shipping_depot_id', $row->shipping_depot_id)
                    ->get();

                return [
                    'pickup_depot_id'     => $row->pickup_depot_id,
                    'pickup_depot_name'   => $row->pickupDepot->name ?? 'N/A',
                    'shipping_depot_id'   => $row->shipping_depot_id,
                    'shipping_depot_name' => $row->shippingDepot->name ?? 'N/A',
                    'status'              => $row->status,
                    'total_packages'      => (int)$row->total_packages,
                    'packages'            => $packages,
                ];
            });


        /*
    |--------------------------------------------------------------------------
    | 🔥 NEW — DRIVER ACTION SUMMARY
    |--------------------------------------------------------------------------
    | pickup_needed   → driver must pickup from seller
    | delivery_needed → driver must deliver to buyer
    | self_handled    → no logistics movement
    */

        $logisticsActionSummary = collect([
            'pickup_needed' => (clone $base)
                ->where('is_seller_dropoff', false)
                ->get(),

            'delivery_needed' => (clone $base)
                ->where('is_buyer_pickup', false)
                ->get(),

            'self_handled' => (clone $base)
                ->where('is_seller_dropoff', true)
                ->where('is_buyer_pickup', true)
                ->get(),
        ])->map(function ($packages, $key) {

            return [
                'action_type'   => $key,
                'total_packages' => $packages->count(),
                'packages'      => $packages,
            ];
        })->values();


        /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */
        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'filters' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date'   => $endDate->toDateString(),
                    'depot_id'   => $depotId,
                ],
                'total_packages'           => $statusSummary->sum('total_packages'),
                'status_summary'           => $statusSummary,
                'pack_combination_summary' => $packCombinationSummary,
                'regular_depot_summary'    => $regularDepotSummary,
                'cross_depot_summary'      => $crossDepotSummary,

                'logistics_action_summary' => $logisticsActionSummary,
            ]
        );
    }

    //
}
