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

            'shipment.shipmentPackages',
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

            'shipment.shipmentPackages',
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
        if ($driverShipment->driver_id !== request()->user()->id) {
            return $this->errorResponse("Unauthorized", 403);
        }

        $request->validate([
            'proof_image' => 'required|image|max:2048', // Optional proof image

        ]);

        $shipment = $driverShipment->shipment;

        if (!$shipment) {
            return $this->showErrorMessage(__('messages.error_messages.not_found'), 404);
        }

        $shipment->load('shipmentPackages');

        if (in_array($driverShipment->status, [
            DriverShipmentStatusEnum::CANCELLED->value,
            ShipmentStatusEnum::RETURNED->value,
            DriverShipmentStatusEnum::COMPLETED->value,
        ])) {
            return $this->errorResponse("This shipment has been cancelled/returned/completed. Cannot complete.", 410);
        }

        // Only for buyer dispatch confirmation.
        if (
            $shipment->shipment_type == ShipmentStatusEnum::DISPATCH->value
            && $shipment->buyer_id !== null
        ) {
            // we need OTP
            $request->validate([
                'otp' => 'required|string|min:4|max:8',
                'request_id' => 'required|string', // To identify which OTP request this is for
            ]);

            $driverShipment = DriverShipment::with('shipment.buyer')->find($driverShipment->id);
            $buyer = User::findOrFail($driverShipment->shipment->buyer_id);

            // Check OTP logic here (if implemented)

            // If not matching then return error response
            if (!$otpService->verify(
                $buyer,
                OtpPurposeEnum::DELIVERY_CONFIRMATION->value,
                $request->request_id,
                $request->otp,
                $request->phone_number
            )) {
                // return $this->showErrorMessage('Invalid or expired OTP', 422);
                return $this->showErrorMessage(__('messages.error_messages.invalid_or_expired_otp'), 422);
            }
        }





        DB::transaction(function () use ($request, $driverShipment, $shipment) {

            $type = $shipment->shipment_type;

            $packages = $shipment->shipmentPackages;

            // Store image if provided and save path in driver shipment for record
            if ($request->hasFile('proof_image')) {
                $imagePath = $request->file('proof_image')->store('driver_shipment_proofs', 'public');
                $driverShipment->update(['proof_image_path' => $imagePath]);
            }

            /*
        |--------------------------------------------------------------------------
        | 1️⃣ FIRST CHECK — BLOCK IF ALL PENDING
        |--------------------------------------------------------------------------
        */

            if (in_array($type, [ShipmentTypeEnum::PICKUP->value, ShipmentTypeEnum::MARKET_PICKUP->value])) {

                // all or one of pending then cannot complete pickup because if all pending then there is no action taken by driver and if one of them not pending then we can say driver has taken action for pickup so we can allow to complete pickup and move to next step which is internal transfer or dispatch based on shipment type.
                $allPending = $packages->every(function ($package) {
                    return $package->status === ShipmentStatusEnum::PENDING->value;
                });

                if ($allPending) {
                    throw new RuntimeException("Cannot complete pickup. All packages are still pending.");
                }
            }

            if (in_array($type, [ShipmentTypeEnum::DISPATCH->value, ShipmentTypeEnum::MARKET_DISPATCH->value])) {

                $allPending = $packages->every(function ($package) {
                    return $package->status !== ShipmentStatusEnum::DELIVERED->value;
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

                    if (in_array($type, [ShipmentTypeEnum::PICKUP->value, ShipmentTypeEnum::MARKET_PICKUP->value])) {

                    // Only process if package is picked and not already arrived
                    if (
                        $package->status === ShipmentStatusEnum::PICKED_UP->value &&
                        $package->status !== ShipmentStatusEnum::ARRIVED_AT_DEPOT->value
                    ) {

                        $package->update([
                            'status' => ShipmentStatusEnum::ARRIVED_AT_DEPOT->value,
                        ]);

                        // $orderItem = $package?->orderItem;
                        //    $marketOrderItem = $package?->marketOrderItem;
                        // $demandOrderItem = $package?->demandOrderItem;


                        if ($package->source_item == OrderItem::class) {
                            $orderItem = $package?->orderItem;
                            if ($orderItem) {
                                $orderItem->increment('ship_qty', $package->qty);
                            }
                        }

                        if ($package->source_item == MarketOrderItem::class) {
                            $marketOrderItem = $package?->marketOrderItem;
                            if ($marketOrderItem) {
                                $marketOrderItem->increment('ship_qty', $package->qty);
                            }
                        }

                        if ($package->source_item == DemandOrderItem::class) {
                            $demandOrderItem = $package?->demandOrderItem;
                            if ($demandOrderItem) {
                                $sellerShipQty = $demandOrderItem->seller_ship_qty ?? 0;
                                $currentShipQty = $demandOrderItem->ship_qty ?? 0;
                                $totalShipped = $sellerShipQty + $currentShipQty + $package->qty;

                                if ($totalShipped <= $demandOrderItem->qty) {
                                    $demandOrderItem->increment('ship_qty', $package->qty);
                                }
                            }
                        }
                    }
                }

                if (in_array($type, [ShipmentTypeEnum::TRANSFER->value])) {

                    if ($package->status === ShipmentStatusEnum::ARRIVED_AT_DEPOT->value) {
                        $package->update([
                            'status' => ShipmentStatusEnum::INTERNAL_TRANSFER->value,
                        ]);
                    }
                }

                if (in_array($type, [ShipmentTypeEnum::DISPATCH->value, ShipmentTypeEnum::MARKET_DISPATCH->value])) {

                    $dArr  = [
                        'is_market_order' => $isMarketOrderPackage,
                        'order_id' => $package->order_id,
                        'market_order_id' => $package->market_order_id,
                    ];

                    if ($package->status === ShipmentStatusEnum::DELIVERED->value) {
                        $package->update([
                            'status' => ShipmentStatusEnum::DELIVERED->value,
                            'delivered_at' => now(),
                        ]);
                        $dArr['status'] = ShipmentStatusEnum::DELIVERED->value;
                    }

                    $dispatchDeliveredList[] = $dArr;
                }
            }



            // Check for dispatch if all or order_id or marker_order_id is same and status have delivereed then mark that order as delivered because in dispatch we can have multiple package for same order or market order and if any of them delivered then we can say order is delivered because buyer receive at least one package of that order.
            if (in_array($type, [ShipmentTypeEnum::DISPATCH->value, ShipmentTypeEnum::MARKET_DISPATCH->value])) {

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


    // Update shipment package status (e.g. picked up, delivered) - optional, can be done by driver or system based on delivery confirmation

    /* =========================================================
    | COMMON GUARD
    ========================================================= */
    private function guardDriverShipment($driverShipmentId, $packageId)
    {
        $driverShipment = DriverShipment::with('shipment.shipmentPackages')
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

        // if shipment itself is not started or accepted or in transit
        if (
            !in_array($driverShipment->shipment->status, [
                DriverShipmentStatusEnum::IN_TRANSIT->value,
            ]) ||
            !in_array($driverShipment->status, [
                DriverShipmentStatusEnum::IN_TRANSIT->value,
            ])
        ) {
            throw new RuntimeException(
                "Shipment is not in a valid state for updating package status.",
                422
            );
        }


        $belongs = $driverShipment->shipment->shipmentPackages
            ->pluck('id')
            ->contains($packageId);

        if (!$belongs) {
            throw new RuntimeException('Shipment package not belongs to this shipment.', 404);
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
