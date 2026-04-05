<?php

namespace App\Jobs\Cutoff;

use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Package\SellerPackage;
use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobBuyerCutoffDirectOrder implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $orderIds;

    public function __construct(array $orderIds)
    {
        $this->orderIds = $orderIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        try {

            DB::transaction(function () {

                $orderIds = $this->orderIds;

                $orders = Order::with(['orderItems'])
                    ->whereIn('id', $orderIds)
                    ->get();

                // Now base on this we have to add order value to Shipment Packages 
                foreach ($orders as $order) {

                    // Create Shipment First Exist or not 
                    $shipment = Shipment::where('buyer_id', $order->buyer_id)
                        ->where('origin_depot_id', $order->depot_id) // This one is for dispatch so always from DEPOT
                        ->where('destination_flmnt_location_id', $order->shipping_fulfillment_location_id)  // Destunation of user
                        ->available()
                        ->first();

                    if (!$shipment) {
                        $shipment = Shipment::create([
                            'shipment_date' => date('Y-m-d'),
                            'shipment_type' => ShipmentTypeEnum::DISPATCH->value, // to buyer always dispatch
                            'buyer_id' => $order->buyer_id,

                            'origin_type' => ShipmentTypeEnum::DEPOT->value,
                            'origin_depot_id' => $order->depot_id, // This one is for dispatch so always from DEPOT

                            'destination_type' => ShipmentTypeEnum::FULFILLMENT_LOCATION->value,
                            'destination_flmnt_location_id' => $order->shipping_fulfillment_location_id,  // Destunation of user

                            'status' => ShipmentStatusEnum::PENDING->value,
                            'is_buyer_pickup' => $order->is_buyer_pickup, // for buyer cutoff always pickup
                        ]);
                    }

                    foreach ($order->orderItems as $orderItem) {

                        for ($i = 1; $i <= $orderItem->order_qty; $i++) {

                            $sellerPackage = SellerPackage::with(['shipmentPackage'])
                                ->where('seller_id', $orderItem->seller_id)
                                ->where('product_listing_package_id', $orderItem->product_listing_package_id)
                                ->where('product_listing_item_id', $orderItem->product_listing_item_id)
                                ->where('is_used', false)
                                ->first();

                            $existShipmentPackage = ShipmentPackage::where('order_item_id', $orderItem->id)
                                // ->where('source_item', OrderItem::class)
                                ->where('product_listing_package_id', $orderItem->product_listing_package_id)
                                ->where('product_listing_item_id', $orderItem->product_listing_item_id)
                                ->where('product_listing_id', $orderItem->product_listing_id)
                                ->first();

                            if (!$sellerPackage) {
                                $order->flags = array_merge($order->flags ?? [], [OrderFlagsEum::ORDER_ITEM_SELLER_PACKAGE_UNAVAILABLE->value]);
                                $order->save();
                                // Log::warning("Seller Package unavailable for OrderItem ID: {$orderItem->id}, Order ID: {$order->id}");
                                continue; // If no available seller package then skip to
                            }

                            if ($existShipmentPackage) {
                                continue; // If already exist then skip to avoid duplication
                            }

                            $shipmentPackage = ShipmentPackage::create([
                                'shipment_id' => $shipment->id,
                                'seller_package_id' => $sellerPackage?->id,
                                'depot_id' => $order->depot_id, // because for dispatch always from depot

                                // 'source' => Order::class,
                                // 'source_id' => $order->id,

                                // 'source_item' => OrderItem::class,
                                // 'source_item_id' => $orderItem->id,
                                'order_id' => $order->id,
                                'order_item_id' => $orderItem->id,

                                'buyer_id' => $order->buyer_id,
                                'seller_id' => $orderItem->seller_id,

                                'product_listing_package_id' => $orderItem->product_listing_package_id,
                                'product_listing_item_id' => $orderItem->product_listing_item_id,
                                'product_listing_id' => $orderItem->product_listing_id,

                                'product_id' => $orderItem?->productListingItem?->product_id,
                                'product_variant_id' => $orderItem?->productListingItem?->product_variant_id,

                                'qty' => 1, // because we are creating package one by one for each qty
                                'pack_size' => $orderItem->pack_size,
                                'pack_unit' => $orderItem->pack_unit,
                                'pack_price' => $orderItem->pack_price,
                                'pack_type_unit' => $orderItem->pack_type_unit,

                                'package_number_buyer' => ShipmentPackage::generatePackageNumberBuyer($order->buyer_id),
                                'package_number_seller' =>  $sellerPackage?->shipmentPackage?->package_number_seller,

                                'is_seller_dropoff' => $sellerPackage?->is_seller_dropoff ?? $orderItem?->productListingItem?->productListing?->is_seller_dropoff,
                                'is_buyer_pickup' => $order->is_buyer_pickup,

                            ]);

                            if ($sellerPackage) {

                                $sellerPackage->is_used = true;
                                $sellerPackage->save();

                                // increse ship qty in order item
                                // Because what seller already send to us so already shipped                              
                                $orderItem->ship_qty = ($orderItem->ship_qty ?? 0) + 1;
                                $orderItem->save();


                                // update sellerpackage shipmentPacakge reference for buyer
                                if ($sellerPackage->shipmentPackage && empty($sellerPackage->shipmentPackage->package_number_buyer)) {
                                    $sellerPackage->shipmentPackage->package_number_buyer = $shipmentPackage->package_number_buyer; // update buyer package number to seller package for reference;
                                    $sellerPackage->shipmentPackage->save();
                                }
                            }
                        }

                        // Make Order Packages
                    }

                    $order->is_cutoff = true;
                    $order->save();

                    //
                }


                //
            });
            
        } catch (\Exception $e) {
            Log::error('Error processing JobBuyerCutoffDirectOrder: ' . $e->getMessage(), [
                'order_ids' => $this->orderIds,
            ]);
            throw $e; // VERY IMPORTANT // FOr job failure and retry mechanism we have to rethrow the exception after logging it.
        }

        //
    }
}
