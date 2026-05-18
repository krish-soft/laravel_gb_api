<?php

namespace App\Jobs\CutOff;

use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Common\Package\SellerPackage;
use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobBuyerCutoffDemandOrder implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected int $demandOrderId;

    public function __construct(int $orderId)
    {
        $this->demandOrderId = $orderId;
    }

    public function uniqueId(): string
    {
        return (string) "buyer_cutoff_demand_order_" . $this->demandOrderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $demandOrder = DemandOrder::with(['demandOrderItems'])
            ->lockForUpdate()
            ->find($this->demandOrderId);

        if (!$demandOrder) {
            return; // If order not found then skip processing
        }

        try {

            DB::transaction(function () use ($demandOrder) {

                $buyer = $demandOrder->buyer;
                $buyerDepot = $buyer?->primaryDepot->depot;

                $deliveryShipment = Shipment::where('buyer_id', $demandOrder->buyer_id)
                    ->where('origin_depot_id', $demandOrder->depot_id) // This one is for dispatch so always from DEPOT
                    ->where('destination_flmnt_location_id', $demandOrder->shipping_fulfillment_location_id)  // Destunation of user
                    ->available()
                    ->first();

                if (!$deliveryShipment) {
                    $deliveryShipment = Shipment::create([
                        'shipment_date' => date('Y-m-d'),
                        'shipment_type' => ShipmentTypeEnum::DISPATCH->value, // to buyer always dispatch
                        'buyer_id' => $demandOrder->buyer_id,

                        'origin_type' => ShipmentTypeEnum::DEPOT->value,
                        'origin_depot_id' => $demandOrder->depot_id, // This one is for dispatch so always from DEPOT

                        'destination_type' => ShipmentTypeEnum::FULFILLMENT_LOCATION->value,
                        'destination_flmnt_location_id' => $demandOrder->shipping_fulfillment_location_id,  // Destunation of user

                        'status' => ShipmentStatusEnum::PENDING->value,
                        'is_buyer_pickup' => $demandOrder->is_buyer_pickup, // for buyer cutoff always pickup
                    ]);
                }

                // First Check if we can get from  farmer listings packages which are shipment Packages

                foreach ($demandOrder->demandOrderItems as $demandOrderItem) {

                    $orderQty = $demandOrderItem->order_qty;
                    $productId = $demandOrderItem->product_id;

                    $shipQty = $demandOrderItem->seller_ship_qty ?? 0;
                    $needQty = $orderQty - $shipQty;


                    for ($i = 1; $i <= $needQty; $i++) {

                        $sellerPackage = SellerPackage::with(['productListingPackage', 'shipmentPackage'])
                            ->where('product_id', $demandOrderItem->product_id)
                            ->where('is_used', false)
                            ->whereHas('productListingPackage', function ($q) use ($demandOrderItem) {
                                $q->where('pack_size', $demandOrderItem->pack_size)
                                    ->where('pack_unit', $demandOrderItem->pack_unit);
                                // ->where('pack_type_unit', $demandOrderItem->pack_type_unit);
                            })
                            ->first();


                        $productListingPackage = $sellerPackage?->productListingPackage;

                        if (
                            $sellerPackage
                            // Also we need to check that package was pickedup or not if not then ignore
                            && (
                                $productListingPackage
                                && $productListingPackage->pack_size == $demandOrderItem->pack_size
                                && $productListingPackage->pack_unit == $demandOrderItem->pack_unit
                                // && $productListingPackage->pack_type_unit == $demandOrderItem->pack_type_unit
                            )

                        ) {

                            $existShipmentPackage = ShipmentPackage::where('demand_order_item_id', $demandOrderItem->id)
                                // ->where('source_item', DemandOrderItem::class)
                                ->where('product_id', $demandOrderItem->product_id)
                                ->where('product_variant_id', $demandOrderItem->product_variant_id)
                                ->where('product_listing_package_id', $sellerPackage->product_listing_package_id)
                                // ->where('product_listing_item_id', $sellerPackage->product_listing_item_id)
                                // ->where('product_listing_id', $sellerPackage->product_listing_id)
                                ->first();

                            if ($existShipmentPackage) {
                                continue; // If already exist then skip to avoid duplication
                            }

                            $shipmentPackage = ShipmentPackage::create([
                                'shipment_id' => $deliveryShipment->id,
                                'parent_shipment_package_id' => $existShipmentPackage?->id, // if already exist then link to that otherwise null for new package
                                'seller_package_id' => $sellerPackage?->id,
                                'depot_id' => $demandOrder->depot_id, // because for dispatch always from depot


                                'demand_order_id' => $demandOrder->id,
                                'demand_order_item_id' => $demandOrderItem->id,

                                'buyer_id' => $demandOrder->buyer_id,
                                'seller_id' => $sellerPackage?->seller_id,

                                'product_listing_package_id' => $sellerPackage?->product_listing_package_id,
                                'product_listing_item_id' => $sellerPackage?->product_listing_item_id,
                                'product_listing_id' => $sellerPackage?->product_listing_id,

                                'product_id' => $demandOrderItem->product_id,
                                'product_variant_id' => $demandOrderItem->product_variant_id,

                                'qty' => 1, // because we are creating package one by one for each qty
                                'pack_size' => $demandOrderItem->pack_size,
                                'pack_unit' => $demandOrderItem->pack_unit,
                                'pack_price' => $demandOrderItem->pack_price,
                                'pack_type_unit' => $demandOrderItem->pack_type_unit,

                                'package_number_buyer' => ShipmentPackage::generatePackageNumberBuyer($demandOrder->buyer_id),
                                'package_number_seller' =>  $sellerPackage?->shipmentPackage?->package_number_seller,

                                'is_seller_dropoff' => $sellerPackage->is_seller_dropoff ?? false,
                                'is_buyer_pickup' => $demandOrder->is_buyer_pickup,

                            ]);

                            $demandOrderItem->seller_ship_qty = ($demandOrderItem->seller_ship_qty ?? 0) + 1;

                            // Check if seller_ship_qty is equal to order qty then only mark fulfilled because we have to check fullfilment based on seller ship qty because its direct relation with shipment package and also for cutoff management if we get from farmer so we have to manage based on seller ship qty
                            if ($demandOrderItem->seller_ship_qty >= $demandOrderItem->order_qty) {
                                $demandOrderItem->is_fulfilled = true;
                            }
                            $demandOrderItem->save();

                            // ProductListing Pacakge increase sold_qty
                            $productListingPackage->demand_sold_qty = ($productListingPackage->demand_sold_qty ?? 0) + 1;
                            $productListingPackage->demand_ship_qty = ($productListingPackage->demand_ship_qty ?? 0) + 1;
                            $productListingPackage->save();

                            // Mark seller package used
                            $sellerPackage->is_used = true;
                            $sellerPackage->save();

                            // update sellerpackage shipmentPacakge reference for buyer
                            if ($sellerPackage->shipmentPackage && empty($sellerPackage->shipmentPackage->package_number_buyer)) {
                                $sellerPackage->shipmentPackage->package_number_buyer = $shipmentPackage->package_number_buyer; // update buyer package number to seller package for reference;
                                $sellerPackage->shipmentPackage->save();
                            }
                        } else {
                            // Log::warning("Seller Package unavailable for DemandOrderItem ID: {$demandOrderItem->id}, DemandOrder ID: {$demandOrder->id}");
                            // Log::warning('No shipment creaed based on seller package.');
                        }
                    }


                    //
                }


                // We need another shipment to picup from market and bring to depot for remain which are not matched
                $marketId = $buyer->primaryDepot->depot->market_id;

                if (!$marketId) {
                    throw new \RuntimeException("Market ID missing for demand order {$demandOrder->order_number}");
                }


                $pickupShipment = Shipment::where('buyer_id', $demandOrder->buyer_id)
                    ->where('origin_market_id',  $marketId) // This one is for dispatch so always from MARKET
                    ->where('destination_depot_id', $demandOrder->depot_id)  // Destunation of user
                    ->available()
                    ->first();

                if (!$pickupShipment) {
                    $pickupShipment = Shipment::create([
                        'shipment_date' => date('Y-m-d'),
                        'shipment_type' => ShipmentTypeEnum::MARKET_PICKUP->value, // to buyer always dispatch
                        'buyer_id' => $demandOrder->buyer_id,

                        'origin_type' => ShipmentTypeEnum::MARKET->value,
                        'origin_market_id' => $marketId, // This one is for dispatch so always from MARKET

                        'destination_type' => ShipmentTypeEnum::DEPOT->value,
                        'destination_depot_id' => $demandOrder->depot_id,  // Destunation of user

                        'status' => ShipmentStatusEnum::PENDING->value,
                        'is_buyer_pickup' => $demandOrder->is_buyer_pickup, // for buyer cutoff always pickup
                    ]);
                }


                // Once completed matching from farmer if not match or missing or not found 
                // so those items now we have to get from APMC or other market
                // So those pacakge never exist on Shipment Package we have to create it

                foreach ($demandOrder->demandOrderItems as $demandOrderItem) {
                    $orderQty = $demandOrderItem->order_qty;

                    $shipQty = $demandOrderItem->ship_qty ?? 0;
                    $shipQtySeller = $demandOrderItem->seller_ship_qty ?? 0;

                    $needQty = $orderQty - $shipQty - $shipQtySeller;

                    if ($needQty > 0) {

                        for ($i = 0; $i < $needQty; $i++) {

                            // existng pacakge same product id can be exist for same order item because of multiple seller package so we have to check with product id and listing package id and listing item id and listing id
                            // To Prevent duplicated its already manage my is_cutoff false or true so no need to check

                            $deliveryShipmentPackage =    ShipmentPackage::create([
                                'shipment_id' => $deliveryShipment->id,

                                // 'source' => DemandOrder::class,
                                // 'source_id' => $demandOrder->id,

                                // 'source_item' => DemandOrderItem::class,
                                // 'source_item_id' => $demandOrderItem->id,
                                'demand_order_id' => $demandOrder->id,
                                'demand_order_item_id' => $demandOrderItem->id,

                                'buyer_id' => $demandOrder->buyer_id,

                                'product_id' => $demandOrderItem?->product_id,
                                'product_variant_id' => $demandOrderItem?->product_variant_id,

                                'qty' => 1, // because we are creating package one by one for each qty
                                'pack_size' => $demandOrderItem->pack_size,
                                'pack_unit' => $demandOrderItem->pack_unit,
                                'pack_price' => $demandOrderItem->pack_price,
                                'pack_type_unit' => $demandOrderItem->pack_type_unit,

                                'package_number_buyer' => ShipmentPackage::generatePackageNumberBuyer($demandOrder->buyer_id),

                                'is_buyer_pickup' => $demandOrder->is_buyer_pickup,

                            ]);


                            // Same for pickup shipment package for remain qty which is not fulfilled by farmer
                            $pickupShipmentPackage =    ShipmentPackage::create([
                                'shipment_id' => $pickupShipment->id,

                                // 'source' => DemandOrder::class,
                                // 'source_id' => $demandOrder->id,

                                // 'source_item' => DemandOrderItem::class,
                                // 'source_item_id' => $demandOrderItem->id,
                                'demand_order_id' => $demandOrder->id,
                                'demand_order_item_id' => $demandOrderItem->id,

                                'buyer_id' => $demandOrder->buyer_id,
                                'market_id' => $marketId,

                                'product_id' => $demandOrderItem?->product_id,
                                'product_variant_id' => $demandOrderItem?->product_variant_id,

                                'qty' => 1, // because we are creating package one by one for each qty
                                'pack_size' => $demandOrderItem->pack_size,
                                'pack_unit' => $demandOrderItem->pack_unit,
                                'pack_price' => $demandOrderItem->pack_price,
                                'pack_type_unit' => $demandOrderItem->pack_type_unit,

                                'package_number_buyer' => $deliveryShipmentPackage->package_number_buyer,
                                'package_number_market' => ShipmentPackage::generatePackageNumberMarket($marketId),

                                'is_buyer_pickup' => $demandOrder->is_buyer_pickup,

                            ]);

                            // update delivery shipment package reference to market package for buyer
                            if ($deliveryShipmentPackage && empty($deliveryShipmentPackage->package_number_market)) {
                                $deliveryShipmentPackage->package_number_market = $pickupShipmentPackage->package_number_market; // update market package number to delivery shipment package for reference;
                                $deliveryShipmentPackage->save();
                            }
                        }
                    }
                }


                $demandOrder->is_cutoff = true;
                $demandOrder->is_locked = true;
                $demandOrder->removeFlag(OrderFlagsEum::CUTOFF_ERROR); // Remove cutoff error flag if exist because now we successfully processed
                $demandOrder->save();
                //

            });
        } catch (\Exception $e) {
            // Handle the exception, log it, or rethrow it as needed
            $demandOrder->addFlag(OrderFlagsEum::CUTOFF_ERROR, "Error processing cutoff for Order Number: {$demandOrder->order_number}. Error: " . $e->getMessage());

            throw $e; // VERY IMPORTANT
        }
        //
    }


    //
}
