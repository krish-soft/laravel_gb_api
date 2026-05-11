<?php

namespace App\Http\Controllers\Api\v1\User\Common\Shipment;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\OtpPurposeEnum;
use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Order\DemandOrderItem;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Delivery\DriverShipment;
use App\Models\Market\MarketOrderItem;
use App\Models\User;
use App\Services\Common\Auth\OneTimePasswordService;
use Exception;
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
    // to get status requested or not to driver show can just show popup on mobile app
    public function getRequestedShipments(Request $request)
    {
        $user = $request->user();

        $driverShipments = DriverShipment::oldest()
            ->where('driver_id', $user->id)
            ->whereIn('status', [DriverShipmentStatusEnum::REQUESTED->value, DriverShipmentStatusEnum::ASSIGNED->value]) // becasue default we have to assigned direct as startup
            ->get();

        $activeShipments = DriverShipment::oldest()
            ->where('driver_id', $user->id)
            ->whereIn('status', [
                DriverShipmentStatusEnum::ACCEPTED->value,
                DriverShipmentStatusEnum::IN_TRANSIT->value,
            ])->get();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'has_requested_shipments' => $driverShipments->isNotEmpty(),
                'number_of_requested_shipments' => $driverShipments->count(),
                // 'shipment_ids' => implode(',', $driverShipments->pluck('id')->toArray()),
                'shipment_ids' =>  $driverShipments->pluck('id')->toArray(),

                // Active Shipments
                'has_active_shipments' => $activeShipments->isNotEmpty(),
                'active_shipments_count' => $activeShipments->count(),
                'active_shipment_ids' => $activeShipments->pluck('id')->toArray(),

            ]
        );
    }


    // Get All Shipments and prepare routes need all address or deliver
    public function getDeliverShipments(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shipment_number' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', DriverShipmentStatusEnum::casesAsValues()),
            'status_not_in' => 'nullable|in:' . implode(',', DriverShipmentStatusEnum::casesAsValues()),
        ]);

        $query = DriverShipment::with([

            // 🔥 ONLY ROUTE DATA
            'shipment.originFulfillmentLocation.address',
            'shipment.destinationFulfillmentLocation.address',

            'shipment.originDepot.address',
            'shipment.destinationDepot.address',

            'shipment.originMarket.fulfillmentLocation.address',
            'shipment.destinationMarket.fulfillmentLocation.address',

            'shipment.shipmentPackages.product',
        ])
            ->where('driver_id', $request->user()->id)
            ->whereNotIn('status', [
                DriverShipmentStatusEnum::CANCELLED->value,
                DriverShipmentStatusEnum::REJECTED->value,
                ...($request->filled('status_not_in') ? explode(',', $request->status_not_in) : [])
            ]);


        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }


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
            ->map(function ($ds) use ($request) {

                $shipment = $ds->shipment;

                if (!$shipment) {
                    return null; // or handle this case as needed
                }

                $processData =  [
                    'driver_shipment_id' => $ds->id,
                    'driver_shipment_status' => $ds->status,
                    'shipment_id'        => $shipment->id,
                    'shipment_number'    => $shipment->shipment_number,
                    'shipment_type'      => $shipment->shipment_type,
                    'shipment_status'             => $shipment->status,

                    // 🔥 MODEL ACCESSOR (FAST)
                    'origin'      => $shipment->from_address,
                    'destination' => $shipment->to_address,

                    // 🔥 COUNT ONLY
                    'total_packages' => $shipment?->total_packages,
                    'shipment_payable' => $ds->shipment_payable,

                    'is_delivery_confirmation_otp_required' => $shipment->shipment_type === ShipmentStatusEnum::DISPATCH->value && $shipment->buyer_id !== null, // Only for dispatch shipments with a buyer

                ];

                // $processData['shipment_packages_summary'] = $shipment->shipmentPackages
                //     ->groupBy(function ($item) {
                //         return $item->product_id . '-' . $item->pack_size . '-' . $item->pack_unit . '-' . $item->pack_type_unit;
                //     })
                //     ->map(function ($group) {

                //         $first = $group->first();

                //         $totalQty = $group->sum('qty');
                //         $packSize = $first->pack_size ?? 0;

                //         $totalWeight = $totalQty * $packSize;

                //         return [
                //             'product_id' => $first->product_id,
                //             'product_code' => $first->product?->product_code,
                //             'product_name' => $first->product?->name,

                //             'pack_type_unit' => $first->pack_type_unit, // bag / crate / box
                //             'pack_size' => $first->pack_size,
                //             'pack_unit' => $first->pack_unit,

                //             'total_packages' => $totalQty,
                //             'total_weight' => $totalWeight,

                //             'display' =>
                //             $totalQty . ' ' . $first->pack_type_unit .
                //                 ' (' . $first->pack_size . $first->pack_unit . ') = ' .
                //                 $totalWeight . $first->pack_unit
                //         ];
                //     })
                //     ->values();
                $processData['shipment_packages_summary'] = $shipment->shipmentPackages
                    ->groupBy(function ($item) {
                        return implode('-', [
                            $item->product_id,
                            $item->pack_type_unit,
                            $item->pack_size,
                            $item->pack_unit
                        ]);
                    })
                    ->map(function ($group) {

                        $first = $group->first();

                        $totalQty = $group->sum('qty');

                        $packSize = $first->pack_size ?? 0;

                        $totalWeight = $totalQty * $packSize;

                        // 🔹 dynamic status summary
                        $statusSummary = $group
                            ->groupBy('status')
                            ->map(function ($statusGroup) {
                                return $statusGroup->sum('qty');
                            });

                        return [
                            'product_id' => $first->product_id,
                            'product_code' => $first->product?->product_code,
                            'product_name' => $first->product?->name,

                            'pack_type_unit' => $first->pack_type_unit,
                            'pack_size' => $first->pack_size,
                            'pack_unit' => $first->pack_unit,

                            'total_packages' => $totalQty,
                            'total_weight' => $totalWeight,

                            'status_summary' => $statusSummary,

                            'display' =>
                            $totalQty . ' ' . $first->pack_type_unit .
                                ' (' . $first->pack_size . $first->pack_unit . ') = ' .
                                $totalWeight . $first->pack_unit
                        ];
                    })
                    ->values();

                if ($request->status != DriverShipmentStatusEnum::REQUESTED->value) {

                    $processData['shipmentPackages'] =  $shipment->shipmentPackages->map(function ($p) {
                        return [
                            'shipment_package_id' => $p->id,
                            'shipment_package_number' => $p->shipment_package_number,
                            'shipment_trace_code' => $p->shipment_trace_code,
                            'shipment_package_status' => $p->status,


                            'package_number' => $p->package_number,
                            'package_number_seller' => $p->package_number_seller,
                            'package_number_buyer' => $p->package_number_buyer,
                            'package_number_market' => $p->package_number_market,

                            'qty' => $p->qty,
                            'pack_size' => $p->pack_size,
                            'unit' => $p->pack_unit,

                            'product' => [
                                'code' => $p->product?->product_code,
                                'name' => $p->product?->name,
                            ],
                        ];
                    })->values();
                }

                return $processData;
            })
            ->sortBy(function ($item) use ($priority) {

                if ($item['driver_shipment_status'] === 'completed') {
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
        // Log::info("Fetching shipment details for DriverShipment ID: {$driverShipment->id}, Driver ID: {$driverShipment->driver_id}, Requested by User ID: " . request()->user()->id);

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

            'shipment.originMarket.fulfillmentLocation.address',
            'shipment.destinationMarket.fulfillmentLocation.address',

            'shipment.shipmentPackages.product',
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

                'packages' => $shipment->shipmentPackages->map(function ($p) {

                    return [
                        'shipment_package_id' => $p->id,

                        'shipment_package_number' => $p->shipment_package_number,
                        'shipment_package_status' => $p->status,

                        'package_number' => $p->package_number,
                        'qty' => $p->qty,
                        'pack_size' => $p->pack_size,
                        'unit' => $p->pack_unit,

                        'product' => [
                            'code' => $p->product?->product_code,
                            'name' => $p->product?->name,
                        ],

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
            'shipment.shipmentPackages',


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
            return $this->showErrorMessage("Unauthorized", 403);
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
            return $this->showErrorMessage("Unauthorized", 403);
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
        $shipment->update(['status' => ShipmentStatusEnum::PENDING->value]);
        // }


        // Log Activity
        logActivity(
            'driver_shipment_rejected',
            request()->user(),
            get_class($driverShipment),
            $driverShipment->id,
            $shipment->shipment_number,
            [
                'driver_shipment_id' => $driverShipment->id,
                'shipment_number' => $shipment->shipment_number,
            ]
        );

        // once rejected delete it only drivershipment
        $driverShipment->delete();



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
            return $this->showErrorMessage("Unauthorized", 403);
        }

        if (!$driverShipment->accepted_at || $driverShipment->status !== DriverShipmentStatusEnum::ACCEPTED->value) {
            return $this->showErrorMessage("Must accept first.", 422);
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

    public function complete(Request $request, DriverShipment $driverShipment, OneTimePasswordService $otpService)
    {
        /*
        |--------------------------------------------------------------------------
        | AUTHORIZATION & VALIDATION
        |--------------------------------------------------------------------------
        */
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        $request->validate([
            'proof_image' => 'required|image|max:2048',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'otp' => 'nullable|string|min:4|max:8',
            'request_id' => 'nullable|string',
        ]);

        $shipment = $driverShipment->shipment;

        if (!$shipment) {
            return $this->showErrorMessage(__('messages.error_messages.not_found'), 404);
        }

        // ✅ Check if shipment is already completed/cancelled
        if (in_array($driverShipment->status, [
            DriverShipmentStatusEnum::CANCELLED->value,
            DriverShipmentStatusEnum::COMPLETED->value,
            DriverShipmentStatusEnum::REJECTED->value,
        ])) {
            return $this->errorResponse("This shipment cannot be completed. Current status: {$driverShipment->status}", 410);
        }

        if (in_array($shipment->status, [ShipmentStatusEnum::RETURNED->value])) {
            return $this->errorResponse("This shipment has been returned and cannot be completed.", 410);
        }

        $shipment->load('shipmentPackages');

        /*
        |--------------------------------------------------------------------------
        | DISPATCH OTP VERIFICATION (only for buyer dispatch)
        |--------------------------------------------------------------------------
        */
        if (
            $shipment->shipment_type === ShipmentTypeEnum::DISPATCH->value
            && $shipment->buyer_id !== null
        ) {
            $request->validate([
                'otp' => 'required|string|min:4|max:8',
                'request_id' => 'required|string',
            ]);

            $buyer = User::findOrFail($shipment->buyer_id);

            if (!$otpService->verify(
                $buyer,
                OtpPurposeEnum::DELIVERY_CONFIRMATION->value,
                $request->request_id,
                $request->otp,
                $request->phone_number
            )) {
                return $this->showErrorMessage(__('messages.error_messages.invalid_or_expired_otp'), 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PRE-CHECK: Validate packages based on shipment type
        |--------------------------------------------------------------------------
        */
        try {
            $this->validatePackagesForCompletion($shipment);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION: Update all related data
        |--------------------------------------------------------------------------
        */
        try {
            DB::transaction(function () use ($request, $driverShipment, $shipment) {
                $this->processShipmentCompletion($request, $driverShipment, $shipment);
            });

            return $this->showSuccessMessage(__('messages.success_messages.success_update'));
        } catch (Exception $e) {
            Log::error('Shipment completion error', [
                'driver_shipment_id' => $driverShipment->id,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to complete shipment: ' . $e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Validate Packages for Completion
    |--------------------------------------------------------------------------
    */
    private function validatePackagesForCompletion($shipment): void
    {
        $type = $shipment->shipment_type;
        $packages = $shipment->shipmentPackages;

        if ($packages->isEmpty()) {
            throw new RuntimeException("No packages found in this shipment.");
        }

        /*
        | PICKUP: At least one package must be picked up (not all PENDING)
        */
        if (in_array($type, [ShipmentTypeEnum::PICKUP->value, ShipmentTypeEnum::MARKET_PICKUP->value])) {
            $allPending = $packages->every(fn($p) => $p->status === ShipmentStatusEnum::PENDING->value);

            if ($allPending) {
                throw new RuntimeException("Cannot complete pickup. All packages are still pending - no action has been taken.");
            }
        }

        /*
        | DISPATCH: At least one package must be delivered (not all PENDING)
        */
        if (in_array($type, [ShipmentTypeEnum::DISPATCH->value, ShipmentTypeEnum::MARKET_DISPATCH->value])) {
            $allPending = $packages->every(fn($p) => $p->status === ShipmentStatusEnum::PENDING->value);

            if ($allPending) {
                throw new RuntimeException("Cannot complete dispatch. No package has been delivered.");
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Process Shipment Completion
    |--------------------------------------------------------------------------
    */
    private function processShipmentCompletion($request, $driverShipment, $shipment): void
    {
        $type = $shipment->shipment_type;
        $packages = $shipment->shipmentPackages;

        // 🎯 Store proof image
        if ($request->hasFile('proof_image')) {
            $imagePath = $request->file('proof_image')->store('driver_shipment_proofs', 'public');
            $driverShipment->update(['proof_image_path' => $imagePath]);
        }

        // 🎯 Track orders to update (for deduplication)
        $ordersToUpdate = collect();

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ PROCESS PACKAGES BY SHIPMENT TYPE
        |--------------------------------------------------------------------------
        */
        foreach ($packages as $package) {
            $this->processPackageByType($type, $package, $ordersToUpdate);
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ UPDATE ALL ORDERS (deduplicated)
        |--------------------------------------------------------------------------
        */
        $ordersToUpdate->each(function ($order) {
            if ($order && $order->delivery_status !== OrderStatusEnum::DELIVERED->value) {
                $order->delivery_status = OrderStatusEnum::DELIVERED->value;
                $order->save();
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ MARK DRIVER SHIPMENT & SHIPMENT AS COMPLETED
        |--------------------------------------------------------------------------
        */
        $driverShipment->update([
            'completed_at' => now(),
            'status' => DriverShipmentStatusEnum::COMPLETED->value,
        ]);

        $shipment->update([
            'status' => ShipmentStatusEnum::COMPLETED->value,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Process Individual Package
    |--------------------------------------------------------------------------
    */
    private function processPackageByType($type, $package, $ordersToUpdate): void
    {
        /*
        |--------------------------------------------------------------------------
        | PICKUP FLOW
        |--------------------------------------------------------------------------
        */
        if (in_array($type, [ShipmentTypeEnum::PICKUP->value, ShipmentTypeEnum::MARKET_PICKUP->value])) {
            // ✅ Update ship_qty for orders (only if package is picked up)
            if ($package->status !== ShipmentStatusEnum::PENDING->value) {
                $this->incrementOrderShipQty($package);
            }

            // ✅ Move package status to ARRIVED_AT_DEPOT (only if currently PICKED_UP)
            if ($package->status === ShipmentStatusEnum::PICKED_UP->value) {
                $package->update(['status' => ShipmentStatusEnum::ARRIVED_AT_DEPOT->value]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSFER FLOW
        |--------------------------------------------------------------------------
        */
        if ($type === ShipmentTypeEnum::TRANSFER->value) {
            // ✅ Move package to INTERNAL_TRANSFER (only if at depot)
            if ($package->status === ShipmentStatusEnum::ARRIVED_AT_DEPOT->value) {
                $package->update(['status' => ShipmentStatusEnum::INTERNAL_TRANSFER->value]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | DISPATCH FLOW
        |--------------------------------------------------------------------------
        */
        if (in_array($type, [ShipmentTypeEnum::DISPATCH->value, ShipmentTypeEnum::MARKET_DISPATCH->value])) {
            // ✅ Only update if not already delivered
            if ($package->status !== ShipmentStatusEnum::DELIVERED->value) {
                $package->update([
                    'status' => ShipmentStatusEnum::DELIVERED->value,
                    'delivered_at' => now(),
                ]);
            }

            // ✅ Queue parent order for update (will be deduplicated later)
            $this->queueOrderForUpdate($package, $ordersToUpdate);

            // ✅ For market orders, handle special market pickup logic
            if ($package->market_order_id) {
                $this->handleMarketOrderPickupLogic($package);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Increment Order Ship Qty
    |--------------------------------------------------------------------------
    */
    private function incrementOrderShipQty($package): void
    {
        $qty = $package->qty ?? 0;

        if ($package->orderItem) {
            $package->orderItem->ship_qty = min(
                ($package->orderItem->ship_qty ?? 0) + $qty,
                $package->orderItem->qty
            );
            $package->orderItem->save();
        }

        if ($package->demandOrderItem) {
            $package->demandOrderItem->ship_qty = min(
                ($package->demandOrderItem->ship_qty ?? 0) + $qty,
                $package->demandOrderItem->order_qty
            );
            $package->demandOrderItem->save();
        }

        if ($package->marketOrderItem) {
            $package->marketOrderItem->ship_qty = min(
                ($package->marketOrderItem->ship_qty ?? 0) + $qty,
                $package->marketOrderItem->qty
            );
            $package->marketOrderItem->save();
        }

        if ($package->productListingPackage && $package->seller_id) {
            $package->productListingPackage->ship_qty = min(
                ($package->productListingPackage->ship_qty ?? 0) + $qty,
                $package->productListingPackage->qty
            );
            $package->productListingPackage->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Queue Order for Update (with deduplication by ID)
    |--------------------------------------------------------------------------
    */
    private function queueOrderForUpdate($package, $ordersToUpdate): void
    {
        if ($package->order_id && $package->order) {
            $ordersToUpdate->put("order_{$package->order_id}", $package->order);
        }

        if ($package->demand_order_id && $package->demandOrder) {
            $ordersToUpdate->put("demand_order_{$package->demand_order_id}", $package->demandOrder);
        }

        if ($package->market_order_id && $package->marketOrder) {
            $ordersToUpdate->put("market_order_{$package->market_order_id}", $package->marketOrder);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Handle Market Order Pickup Logic
    |--------------------------------------------------------------------------
    */
    private function handleMarketOrderPickupLogic($package): void
    {
        // Find the corresponding pickup package for this market order
        $pickupMarketPackage = ShipmentPackage::where('seller_package_id', $package->seller_package_id)
            ->where('product_listing_package_id', $package->product_listing_package_id)
            ->where('shipment_id', '!=', $package->shipment_id) // exclude current dispatch shipment
            ->first();

        if ($pickupMarketPackage && $pickupMarketPackage->status === ShipmentStatusEnum::PICKED_UP->value) {
            $marketItem = $package->marketOrderItem;
            if ($marketItem) {
                // Increase ship_qty to match pickup qty for accurate tracking
                $marketItem->ship_qty = min(
                    ($marketItem->ship_qty ?? 0) + ($package->qty ?? 0),
                    $marketItem->qty
                );
                $marketItem->save();
            }
        }
    }


    // Update shipment package status (e.g. picked up, delivered) - optional, can be done by driver or system based on delivery confirmation

    /* =========================================================
    | COMMON GUARD
    ========================================================= */
    private function guardDriverShipment($driverShipmentId, $packageId)
    {
        $driverShipment = DriverShipment::with('shipment.shipmentPackages')
            ->findOrFail($driverShipmentId);

        if ($driverShipment->driver_id !== request()->user()->id) {
            throw new Exception(__('messages.error_messages.unauthorized_action'), 403);
        }

        // if driver shipment not accepted do not change any status of packages
        if (
            (!$driverShipment->accepted_at && !in_array($driverShipment->status, [DriverShipmentStatusEnum::ACCEPTED->value, DriverShipmentStatusEnum::IN_TRANSIT->value]))
        ) {
            throw new Exception("Must accept/start shipment first.", 422);
        }

        if (in_array($driverShipment->status, [
            DriverShipmentStatusEnum::CANCELLED->value,
            DriverShipmentStatusEnum::COMPLETED->value,
            DriverShipmentStatusEnum::REJECTED->value,
            ShipmentStatusEnum::RETURNED->value,
        ])) {
            throw new Exception("This shipment has been cancelled/rejected/returned/completed.", 410);
        }

        // if shipment itself is not started or accepted or in transit
        if (
            !in_array($driverShipment->shipment->status, [
                DriverShipmentStatusEnum::IN_TRANSIT->value,
            ]) ||
            !in_array($driverShipment->status, [
                DriverShipmentStatusEnum::IN_TRANSIT->value,
            ])
        ) {
            throw new Exception(
                "Shipment is not in a valid state for updating package status.",
                422
            );
        }


        $belongs = $driverShipment->shipment->shipmentPackages
            ->pluck('id')
            ->contains($packageId);

        if (!$belongs) {
            throw new Exception('Shipment package not belongs to this shipment.', 404);
        }

        return $driverShipment;
    }

    // Main Pacakge Status update
    public function updateShipmentPackageStatus(Request $request)
    {
        $allowed = [
            ShipmentStatusEnum::PENDING->value,
            ShipmentStatusEnum::PICKED_UP->value,
            ShipmentStatusEnum::NOT_PICKED_UP->value,
            ShipmentStatusEnum::RETURNED->value,
            ShipmentStatusEnum::LOST->value,
            ShipmentStatusEnum::DAMAGED->value,
            ShipmentStatusEnum::OUT_FOR_DELIVERY->value,
            ShipmentStatusEnum::DELIVERED->value,
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
            'status' => $request->status,
            'picked_up_at'  => $request->status === ShipmentStatusEnum::PICKED_UP->value ? now() : null,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }
}
