<?php

namespace App\Http\Controllers\Api\v1\User\Common\Shipment;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Delivery\DriverShipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SebastianBergmann\CodeCoverage\Driver\Driver;

class DriverShipmentApiController extends ApiResponseWithAuthController
{

    /*
    |--------------------------------------------------------------------------
    | DRIVER SHIPMENT LIST (FOR MOBILE HOME)
    |--------------------------------------------------------------------------
    */

    // Get All Shipments and prepare routes need all address or deliver
    public function getDeliverShipments(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shipment_number' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', DriverShipmentStatusEnum::casesAsValues()),
        ]);

        $query = DriverShipment::with([

            // 🔥 ONLY ROUTE DATA
            'shipment.originFulfillmentLocation.address',
            'shipment.destinationFulfillmentLocation.address',
            'shipment.originDepot.address',
            'shipment.destinationDepot.address',

            // 🔥 ONLY COUNT PURPOSE
            // 🔥 ADD THIS (missing — causes null)
            'shipment.shipmentGroups.shipmentPackage',
        ])
            ->where('driver_id', $request->user()->id)
            ->whereNotIn('status', [DriverShipmentStatusEnum::CANCELLED->value, DriverShipmentStatusEnum::REJECTED->value]);

        $start = $request->filled('start_date')
            ? now()->parse($request->start_date)->startOfDay()
            : now()->startOfDay();

        $end = $request->filled('end_date')
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $driverShipments = $query
            ->whereBetween('assigned_at', [$start, $end])
            ->get();


        $priority = [
            'pickup'   => 1,
            'transfer' => 2,
            'dispatch' => 3,
        ];


        $routeList = $driverShipments
            ->map(function ($ds) {

                $shipment = $ds->shipment;

                if (!$shipment) {
                    return null; // or handle this case as needed
                }

                return [
                    'driver_shipment_id' => $ds->id,
                    'shipment_id'        => $shipment->id,
                    'shipment_number'    => $shipment->shipment_number,
                    'shipment_type'      => $shipment->shipment_type,
                    'status'             => $shipment->status,

                    // 🔥 MODEL ACCESSOR (FAST)
                    'origin'      => $shipment->from_address,
                    'destination' => $shipment->to_address,

                    // 🔥 COUNT ONLY
                    'total_packages' => $shipment?->total_packages,
                    'shipment_payable' => $ds->shipment_payable,

                ];
            })
            ->sortBy(function ($item) use ($priority) {

                if ($item['status'] === 'completed') {
                    return 999;
                }

                return $priority[$item['shipment_type']] ?? 50;
            })
            ->values();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $routeList
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SINGLE SHIPMENT DETAIL (PHONE DETAIL SCREEN)
    |--------------------------------------------------------------------------
    */

    public function shipmentDetails(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if (in_array($driverShipment->status, [
            DriverShipmentStatusEnum::CANCELLED->value,
            DriverShipmentStatusEnum::COMPLETED->value
        ])) {
            return $this->errorResponse("This shipment has been cancelled/completed.", 410);
        }

        $driverShipment->load([

            'shipment.originFulfillmentLocation.address',
            'shipment.destinationFulfillmentLocation.address',
            'shipment.originDepot.address',
            'shipment.destinationDepot.address',

            'shipment.shipmentGroups.shipmentPackage.buyer.address',
            'shipment.shipmentGroups.shipmentPackage.seller.address',
        ]);

        $shipment = $driverShipment->shipment;

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'driver_shipment_id' => $driverShipment->id,
                'shipment_number' => $shipment->shipment_number,
                'shipment_type'   => $shipment->shipment_type,
                'status'          => $shipment->status,

                'origin'      => $shipment->from_address,
                'destination' => $shipment->to_address,

                'packages' => $shipment->shipmentGroups->map(function ($g) {

                    $p = $g->shipmentPackage;

                    return [
                        'shipment_group_id' => $g->id,
                        'shipment_package_id' => $p->id,

                        'shipment_package_number' => $p->shipment_package_number,
                        'shipment_package_status' => $p->status,
                        'shipment_package_seller_status' => $p->seller_status,
                        'shipment_package_buyer_status' => $p->buyer_status,
                        'shipment_package_transfer_status' => $p->transfer_status,


                        'package_number' => $p->package_number,
                        'qty' => $p->qty,
                        'pack_size' => $p->pack_size,
                        'unit' => $p->pack_unit,

                        // 'buyer' => [
                        //     'nickname' => $p->buyer?->nickname,
                        //     'phone' => $p->buyer?->address?->phone_number,
                        //     'address' => $p->buyer?->address?->address_line1,
                        // ],

                        // 'seller' => [
                        //     'nickname' => $p->seller?->nickname,
                        //     'phone' => $p->seller?->address?->phone_number,
                        //     'address' => $p->seller?->address?->address_line1,
                        // ],
                    ];
                })->values(),
            ]
        );
    }

    public function getAllShipments(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            // 'driver_id' => 'nullable|exists:users,id',
            'shipment_number' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', DriverShipmentStatusEnum::casesAsValues()),
        ]);

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 20);

        $query = DriverShipment::with([
            'driver',
            // 'driverVehicle',
            // 'shipment',
            'shipment.shipmentGroups.shipmentPackage',
            // 'shipment.shipmentGroups.shipmentPackage.buyer',
            // 'shipment.shipmentGroups.shipmentPackage.seller',

        ])
            ->where('driver_id', $request->user()->id);

        $start = $request->filled('start_date')
            ? now()->parse($request->start_date)->startOfDay()
            : now()->startOfDay();

        $end = $request->filled('end_date')
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $query->whereBetween('assigned_at', [$start, $end]);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->filled('shipment_number')) {
            $query->whereHas(
                'shipment',
                fn($q) =>
                $q->where('shipment_number', 'like', '%' . $request->shipment_number . '%')
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $shipments = $query->orderByDesc('assigned_at')->get()->slice($offset, $limit)->values();

        return $this->successResponse(__('messages.success_messages.success_get'), $shipments);
    }



    /*
    |--------------------------------------------------------------------------
    | ACCEPT
    |--------------------------------------------------------------------------
    */
    public function accept(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if ($driverShipment->rejected_at) {
            return $this->showSuccessMessage("Rejected shipment can not accept again.");
        }

        if ($driverShipment->accepted_at) {
            return $this->showSuccessMessage("Already accepted.");
        }

        $driverShipment->update([
            'accepted_at' => now(),
            'status' => DriverShipmentStatusEnum::ACCEPTED->value,
        ]);

        $driverShipment->shipment()->update([
            'status' =>  DriverShipmentStatusEnum::ACCEPTED->value,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }


    public function reject(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if ($driverShipment->accepted_at) {
            return $this->showSuccessMessage("Already accepted. Can not reject now.");
        }

        if ($driverShipment->rejected_at) {
            return $this->showSuccessMessage("Already rejected.");
        }

        $driverShipment->update([
            'rejected_at' => now(),
            'status' => DriverShipmentStatusEnum::REJECTED->value,
        ]);

        // Make original shipment available for other drivers by setting driver_id to null 
        $shipment = $driverShipment->shipment;
        // if ($shipment && $shipment->status === ShipmentStatusEnum::ASSIGNED->value) {
        $shipment->update(['status' => ShipmentStatusEnum::GROUPED->value]);
        // }


        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /*
    |--------------------------------------------------------------------------
    | START DELIVERY
    |--------------------------------------------------------------------------
    */
    public function start(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        if (!$driverShipment->accepted_at || $driverShipment->status !== DriverShipmentStatusEnum::ACCEPTED->value) {
            return $this->errorResponse("Must accept first.", 422);
        }

        if ($driverShipment->started_at) {
            return $this->showSuccessMessage("Already started.");
        }

        $driverShipment->update([
            'started_at' => now(),
            'status' => DriverShipmentStatusEnum::IN_TRANSIT->value,
        ]);

        $driverShipment->shipment()->update([
            'status' =>  DriverShipmentStatusEnum::IN_TRANSIT->value,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETE DELIVERY
    |--------------------------------------------------------------------------
    */

    public function complete(DriverShipment $driverShipment)
    {
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        $shipment = $driverShipment->shipment;
        if (!$shipment) {
            return $this->showErrorMessage(__('messages.error_messages.not_found'), 404);
        }

        if (in_array($driverShipment->status, [
            DriverShipmentStatusEnum::CANCELLED->value,
            ShipmentStatusEnum::RETURNED->value,
            DriverShipmentStatusEnum::COMPLETED->value,
        ])) {
            return $this->errorResponse("This shipment has been cancelled/returned/completed. Cannot complete.", 410);
        }

        DB::transaction(function () use ($driverShipment, $shipment) {

            $type = $shipment->shipment_type;

            $packages = $shipment->shipmentGroups
                ->pluck('shipmentPackage')
                ->filter();

            /*
        |--------------------------------------------------------------------------
        | 1️⃣ FIRST CHECK — BLOCK IF ALL PENDING
        |--------------------------------------------------------------------------
        */

            if ($type === ShipmentStatusEnum::PICKUP->value) {

                // all or one of pending then cannot complete pickup because if all pending then there is no action taken by driver and if one of them not pending then we can say driver has taken action for pickup so we can allow to complete pickup and move to next step which is internal transfer or dispatch based on shipment type.
                $allPending = $packages->every(function ($package) {
                    return $package->seller_status === ShipmentStatusEnum::PENDING->value;
                });

                if ($allPending) {
                    throw new RuntimeException("Cannot complete pickup. All packages are still pending.");
                }
            }

            if ($type === ShipmentStatusEnum::DISPATCH->value) {

                $allPending = $packages->every(function ($package) {
                    return $package->buyer_status !== ShipmentStatusEnum::DELIVERED->value;
                });

                if ($allPending) {
                    throw new RuntimeException("Cannot complete dispatch. No package delivered.");
                }
            }

            /*
        |--------------------------------------------------------------------------
        | 2️⃣ NORMAL FLOW
        |--------------------------------------------------------------------------
        */

            foreach ($packages as $package) {

                $isMarketOrderPackage = $package->market_order_id !== null;
                $dispatchDeliveredList = [];

                if ($type === ShipmentStatusEnum::PICKUP->value) {

                    // Only process if package is picked and not already arrived
                    if (
                        $package->seller_status === ShipmentStatusEnum::PICKED_UP->value &&
                        $package->status !== ShipmentStatusEnum::ARRIVED_AT_DEPOT->value
                    ) {

                        $package->update([
                            'status' => ShipmentStatusEnum::ARRIVED_AT_DEPOT->value,
                        ]);

                        $orderItem = $package?->orderItem;
                        $marketOrderItem = $package?->marketOrderItem;

                        if ($orderItem) {
                            $orderItem->increment('ship_qty', $package->qty);
                        }

                        if ($marketOrderItem) {
                            $marketOrderItem->increment('ship_qty', $package->qty);
                        }
                    }

                    // Otherwise just sync status (optional)
                    elseif ($package->status !== $package->seller_status) {
                        $package->update([
                            'status' => $package->seller_status,
                        ]);
                    }
                }

                if ($type === ShipmentStatusEnum::TRANSFER->value) {

                    if ($package->status === ShipmentStatusEnum::ARRIVED_AT_DEPOT->value) {
                        $package->update([
                            'status' => ShipmentStatusEnum::INTERNAL_TRANSFER->value,
                        ]);
                    }
                }

                if ($type === ShipmentStatusEnum::DISPATCH->value) {

                    $dArr  = [
                        'is_market_order' => $isMarketOrderPackage,
                        'order_id' => $package->order_id,
                        'market_order_id' => $package->market_order_id,
                    ];

                    if ($package->buyer_status === ShipmentStatusEnum::DELIVERED->value) {
                        $package->update([
                            'status' => ShipmentStatusEnum::DELIVERED->value,
                            'delivered_at' => now(),
                        ]);
                        $dArr['status'] = ShipmentStatusEnum::DELIVERED->value;
                    } else {

                        if ($package->seller_status == ShipmentStatusEnum::NOT_PICKED_UP->value) {
                            $package->update([
                                'status' => $package->seller_status,
                            ]);
                            $dArr['status'] = $package->seller_status;
                        }
                    }

                    $dispatchDeliveredList[] = $dArr;
                }
            }
            // Check for dispatch if all or order_id or marker_order_id is same and status have delivereed then mark that order as delivered because in dispatch we can have multiple package for same order or market order and if any of them delivered then we can say order is delivered because buyer receive at least one package of that order.
            if ($type === ShipmentStatusEnum::DISPATCH->value) {

                $groupedByOrder = collect($dispatchDeliveredList)->groupBy(function ($item) {
                    return $item['is_market_order'] ? 'market_order_' . $item['market_order_id'] : 'order_' . $item['order_id'];
                });

                foreach ($groupedByOrder as $group) {

                    $delivered = $group->contains('status', ShipmentStatusEnum::DELIVERED->value);

                    if ($delivered) {

                        $firstItem = $group->first();

                        if ($firstItem['is_market_order']) {
                            // Mark Market Order as Delivered
                            \App\Models\Market\MarketOrder::where('id', $firstItem['market_order_id'])
                                ->update(['delivery_status' => OrderStatusEnum::DELIVERED->value]);
                        } elseif ($firstItem['order_id']) {
                            // Mark Order as Delivered
                            \App\Models\Buyer\Order\Order::where('id', $firstItem['order_id'])
                                ->update(['delivery_status' => OrderStatusEnum::DELIVERED->value]);
                        }
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 3️⃣ COMPLETE SHIPMENT
            |--------------------------------------------------------------------------
            */

            $driverShipment->update([
                'completed_at' => now(),
                'status' => DriverShipmentStatusEnum::COMPLETED->value,
            ]);

            $shipment->update([
                'status' => DriverShipmentStatusEnum::COMPLETED->value,
            ]);
        });

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    // public function complete(DriverShipment $driverShipment)
    // {
    //     if ($driverShipment->driver_id !== request()->user()->id) {
    //         return $this->errorResponse("Unauthorized", 403);
    //     }

    //     $shipment = $driverShipment->shipment;
    //     if (!$shipment) {
    //         return $this->showErrorMessage(__('messages.error_messages.not_found'), 404);
    //     }

    //     $shipmentType = $shipment->shipment_type;



    //     DB::transaction(function () use ($driverShipment, $shipment, $shipmentType) {

    //         // Get Original Order here 
    //         // per shipment package group andper package get order and make order status completed if all shipment completed for that order

    //         // TODO: Trigger any post-delivery processes like notifications, payments, etc.


    //         // 1. If Pickup (Seller To Depot)
    //         if ($shipmentType === ShipmentStatusEnum::PICKUP->value) {

    //             foreach ($shipment->shipmentGroups as $group) {
    //                 $package = $group->shipmentPackage;

    //                 if (!$package) {
    //                     continue; // Skip if no package found, though ideally should not happen
    //                 }

    //                 // Update package too 
    //                 if (!in_array($package->seller_status, [
    //                     // ShipmentStatusEnum::PENDING->value,
    //                     ShipmentStatusEnum::PENDING->value,
    //                     // ShipmentStatusEnum::READY_TO_PICKUP->value
    //                 ])) {

    //                     throw new RuntimeException("Package status must be 'picked_up/not_picked_up/lost/damaged' to complete pickup. Package number: {$package->package_number}");
    //                 }

    //                 if (!in_array($package->seller_status, [
    //                     ShipmentStatusEnum::PICKED_UP->value,
    //                 ])) {

    //                     $package->update([
    //                         'status' =>  ShipmentStatusEnum::ARRIVED_AT_DEPOT->value,
    //                         'picked_up_at' => now(),
    //                     ]);
    //                 } else {
    //                     $package->update([
    //                         'status' =>  $package->seller_status,
    //                     ]);
    //                 }
    //             }
    //         }

    //         // 2. Transfer (Depot To Depot) - if needed
    //         if ($shipmentType === ShipmentStatusEnum::TRANSFER->value) {

    //             foreach ($shipment->shipmentGroups as $group) {
    //                 $package = $group->shipmentPackage;

    //                 if (!$package) {
    //                     continue; // Skip if no package found, though ideally should not happen
    //                 }

    //                 // Update package too 
    //                 if (in_array($package->status, [
    //                     // ShipmentStatusEnum::PENDING->value,
    //                     ShipmentStatusEnum::ARRIVED_AT_DEPOT->value,
    //                     ShipmentStatusEnum::INTERNAL_TRANSFER->value,
    //                 ])) {
    //                     $package->update([
    //                         'status' => ShipmentStatusEnum::INTERNAL_TRANSFER->value,
    //                     ]);
    //                 }
    //             }
    //         }

    //         // 3. Dipatch (Depot To Customer Final Delivery)
    //         if ($shipmentType === ShipmentStatusEnum::DISPATCH->value) {

    //             foreach ($shipment->shipmentGroups as $group) {

    //                 $package = $group->shipmentPackage;

    //                 if (!$package) {
    //                     continue; // Skip if no package found, though ideally should not happen
    //                 }

    //                 // Update package too 
    //                 if (in_array($package->status, [
    //                     ShipmentStatusEnum::INTERNAL_TRANSFER->value,
    //                     ShipmentStatusEnum::ARRIVED_AT_DEPOT->value,
    //                     ShipmentStatusEnum::DELIVERED->value // need to give same because same repeat
    //                 ])) {
    //                     $package->update([
    //                         'status' =>  ShipmentStatusEnum::DELIVERED->value,
    //                         'delivered_at' => now()
    //                     ]);
    //                 }

    //                 //  Which not picked up never gone a delivered status, so we can say delivered status must be there to complete dispatch
    //                 // if (!in_array($package->buyer_status, [
    //                 //     ShipmentStatusEnum::DELIVERED->value
    //                 // ])) {
    //                 //     throw new RuntimeException("Package status must be 'delivered' to complete dispatch. Package number: {$package->package_number}");
    //                 // }
    //             }


    //             // After delivery of all packages, we can mark order as completed if all packages of that order delivered
    //         }

    //         $driverShipment->update([
    //             'completed_at' => now(),
    //             'status' => DriverShipmentStatusEnum::COMPLETED->value,
    //         ]);

    //         $driverShipment->shipment()->update([
    //             'status' => DriverShipmentStatusEnum::COMPLETED->value
    //         ]);
    //     });

    //     return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    // }


    // TO Confiorm All We pickedup
    // public function updateShipmentPackageStatus(Request $request)
    // {

    //     $request->validate([
    //         'status' => 'required|in:' . implode(',', ShipmentStatusEnum::casesAsValues()),
    //         'shipment_package_id' => 'required|exists:shipment_packages,id',
    //         'driver_shipment_id' => 'required|exists:driver_shipments,id',
    //     ]);

    //     $driverShipment = DriverShipment::findOrFail($request->driver_shipment_id);
    //     $shipmentPackageId = $request->shipment_package_id;

    //     if ($driverShipment->driver_id !== request()->user()->id) {
    //         return $this->errorResponse(__('messages.error_messages.unauthorized_action'), 403);
    //     }

    //     $shipmentPackage = $driverShipment->shipment
    //         ->shipmentGroups()
    //         ->whereHas('shipmentPackage', function ($q) use ($shipmentPackageId) {
    //             $q->where('id', $shipmentPackageId);
    //         })
    //         ->first()
    //         ?->shipmentPackage;

    //     if (!$shipmentPackage) {
    //         return $this->errorResponse(__('messages.error_messages.not_found'), 404);
    //     }



    //     $shipmentPackage->update([
    //         'status' => $request->status,
    //     ]);

    //     return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    // }


    // Update shipment package status (e.g. picked up, delivered) - optional, can be done by driver or system based on delivery confirmation

    /* =========================================================
| COMMON GUARD
========================================================= */
    private function guardDriverShipment($driverShipmentId, $packageId)
    {
        $driverShipment = DriverShipment::with('shipment.shipmentGroups')
            ->findOrFail($driverShipmentId);

        if ($driverShipment->driver_id !== request()->user()->id) {
            throw new RuntimeException(__('messages.error_messages.unauthorized_action'), 403);
        }

        // if driver shipment not accepted do not change any status of packages
        if (
            (!$driverShipment->accepted_at && !in_array($driverShipment->status, [DriverShipmentStatusEnum::ACCEPTED->value, DriverShipmentStatusEnum::IN_TRANSIT->value]))
        ) {
            throw new RuntimeException("Must accept/start shipment first.", 422);
        }

        if (in_array($driverShipment->status, [
            DriverShipmentStatusEnum::CANCELLED->value,
            DriverShipmentStatusEnum::COMPLETED->value,
            DriverShipmentStatusEnum::REJECTED->value,
            ShipmentStatusEnum::RETURNED->value,
        ])) {
            throw new RuntimeException("This shipment has been cancelled/rejected/returned/completed.", 410);
        }

        $belongs = $driverShipment->shipment->shipmentGroups
            ->pluck('shipment_package_id')
            ->contains($packageId);

        if (!$belongs) {
            throw new RuntimeException('Shipment package not belongs to this shipment.', 404);
        }

        return $driverShipment;
    }


    /* =========================================================
| SELLER STATUS UPDATE
========================================================= */
    public function updateShipmentPackageSellerStatus(Request $request)
    {
        $allowed = [
            ShipmentStatusEnum::PENDING->value,
            ShipmentStatusEnum::PICKED_UP->value,
            ShipmentStatusEnum::NOT_PICKED_UP->value,
            ShipmentStatusEnum::RETURNED->value,
            ShipmentStatusEnum::LOST->value,
            ShipmentStatusEnum::DAMAGED->value,
        ];

        $request->validate([
            'driver_shipment_id' => 'required|exists:driver_shipments,id',
            'shipment_package_id' => 'required|exists:shipment_packages,id',
            'status' => 'required|in:' . implode(',', $allowed),
        ]);

        $this->guardDriverShipment(
            $request->driver_shipment_id,
            $request->shipment_package_id
        );

        $package = ShipmentPackage::findOrFail($request->shipment_package_id);

        $package->update([
            'seller_status' => $request->status,
            'picked_up_at'  => $request->status === ShipmentStatusEnum::PICKED_UP->value ? now() : null,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }


    /* =========================================================
| BUYER STATUS UPDATE
========================================================= */
    public function updateShipmentPackageBuyerStatus(Request $request)
    {
        $allowed = [
            ShipmentStatusEnum::PENDING->value,
            ShipmentStatusEnum::OUT_FOR_DELIVERY->value,
            ShipmentStatusEnum::DELIVERED->value,
            ShipmentStatusEnum::RETURNED->value,
            ShipmentStatusEnum::LOST->value,
            ShipmentStatusEnum::DAMAGED->value,
        ];

        $request->validate([
            'driver_shipment_id' => 'required|exists:driver_shipments,id',
            'shipment_package_id' => 'required|exists:shipment_packages,id',
            'status' => 'required|in:' . implode(',', $allowed),
        ]);

        $this->guardDriverShipment(
            $request->driver_shipment_id,
            $request->shipment_package_id
        );

        $package = ShipmentPackage::findOrFail($request->shipment_package_id);

        $status = $request->status;

        // Prevent delivering if not picked up
        if (
            $status === ShipmentStatusEnum::DELIVERED->value &&
            $package->seller_status !== ShipmentStatusEnum::PICKED_UP->value
        ) {
            $status = $package->seller_status;
        }

        $package->update([
            'buyer_status' => $status,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }


    /* =========================================================
| TRANSFER STATUS UPDATE
========================================================= */
    public function updateShipmentPackageTransferStatus(Request $request)
    {
        $request->validate([
            'driver_shipment_id' => 'required|exists:driver_shipments,id',
            'shipment_package_id' => 'required|exists:shipment_packages,id',
            'status' => 'required|in:' . implode(',', ShipmentStatusEnum::casesAsValues()),
        ]);

        $this->guardDriverShipment(
            $request->driver_shipment_id,
            $request->shipment_package_id
        );

        $package = ShipmentPackage::findOrFail($request->shipment_package_id);

        $package->update([
            'transfer_status' => $request->status,
            'in_transit_at'   => $request->status === ShipmentStatusEnum::INTERNAL_TRANSFER->value ? now() : null,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }



    // Shipment Images  Pending 













}
