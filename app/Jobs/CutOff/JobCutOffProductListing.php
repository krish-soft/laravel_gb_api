<?php

namespace App\Jobs\Cutoff;

use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Package\PackageTypeEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Models\Common\Package\SellerPackage;
use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Seller\Product\ProductListing;
use App\Models\Market\MarketOrder;
use App\Models\Market\MarketOrderItem;
use App\Services\Common\Shipment\ShipmentService;
use App\Services\Seller\Product\ProductListingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class JobCutOffProductListing implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $listingIds;

    public function __construct(array $listingIds)
    {
        $this->listingIds = $listingIds;
    }

    public function handle(ProductListingService $listingService): void
    {
        // Log::info('CutOff Job START', ['listingIds' => $this->listingIds]);

        try {

            DB::transaction(function () use ($listingService) {

                $listings = ProductListing::with([
                    'seller.primaryDepot.depot.market',
                    'listingItems.product',
                    'listingItems.listingPackages' => fn($q) => $q->available(),
                ])
                    ->whereIn('id', $this->listingIds)
                    ->lockForUpdate()
                    ->get();

                foreach ($listings as $listing) {

                    /* ---------------------------------------------
                     | EXPIRE LISTING
                     ---------------------------------------------*/
                    if (!$listing->is_expired) {
                        // $listing->is_active  = false; // keep active so can understand it was created and expired via cutoff
                        $listing->is_expired = true;
                        $listing->expires_at = now();
                    }

                    if ($listing->is_locked) {
                        $listing->save();
                        continue;
                    }

                    // In case its pending from first cutoff
                    $listing->is_cutoff = true;
                    // $listing->is_locked = true; // is locked at end of day
                    $listing->save();

                    if (!$listing->is_sell_to_market) {

                        // then if listing not to sold to market 
                        // we have to make qty sold and qty same
                        // foreach ($listing->listingItems as $item) {
                        //     foreach ($item->listingPackages as $pkg) {
                        //         // Sold qty is equal to qty to settle

                        //     }
                        // }


                        continue;
                    }

                    $marketId = $listing->seller->primaryDepot->depot->market_id;

                    // Market Id missing failed the transaction, so we can be sure it exists here
                    if (!$marketId) {
                        throw new \RuntimeException("Market ID missing for listing {$listing->listing_code}");
                    }

                    $seller = $listing->seller;
                    $depot  = $seller->primaryDepot->depot;

                    /* ---------------------------------------------
                     | CREATE / GET MARKET ORDER
                     ---------------------------------------------*/
                    $marketOrder = MarketOrder::where('reference', $listing->listing_code)
                        ->lockForUpdate()
                        ->first();

                    if (!$marketOrder) {
                        $marketOrder = MarketOrder::create([
                            'market_id' => $marketId,
                            'depot_id' => $listing->seller->primaryDepot->depot_id,

                            'order_status' => OrderStatusEnum::CONFIRMED->value,
                            'delivery_status' => OrderStatusEnum::PENDING->value,

                            'shipping_fulfillment_location_id'
                            => $listing->seller->primaryDepot->depot->market->fulfillment_location_id,

                            'order_date' => date('Y-m-d'),

                            'is_manual' => false,
                            'reference' => $listing->listing_code,

                            'currency' => 'INR',
                            'subtotal' => 0,
                            'tax_amount' => 0,
                            'total_amount' => 0,
                        ]);
                    }

                    /*
                     |--------------------------------------------------------------------------
                     | STEP 1 — CREATE MARKET ORDER ITEMS (PER PACKAGE CONSOLIDATED)
                     |--------------------------------------------------------------------------
                     | ONE MarketOrderItem per PACKAGE
                     | order_qty = remain qty
                     | Shipment will be created later
                     |--------------------------------------------------------------------------
                     */

                    $createdMarketItems = collect();

                    foreach ($listing->listingItems as $item) {

                        if (!$item->product) {
                            throw new \RuntimeException(
                                "Product missing for listing_item {$item->id}"
                            );
                        }

                        foreach ($item->listingPackages as $pkg) {

                            $remain = $pkg->qty - $pkg->sold_qty - $pkg->demand_sold_qty; // for cutoff we have to manage demand sold qty also because we can get demand order from farmer and also direct order from buyer so we have to manage both type of orders in cutoff

                            if ($remain <= 0) {
                                continue;
                            }

                            $marketItem = MarketOrderItem::create([
                                'market_order_id' => $marketOrder->id,
                                'market_order_number' => $marketOrder->market_order_number,


                                'product_listing_id' => $listing->id,
                                'product_listing_item_id' => $item->id,
                                'product_listing_package_id' => $pkg->id,

                                'seller_id' => $listing->seller_id,
                                'pickup_fulfillment_location_id' => $listing->fulfillment_location_id,
                                'listing_code' => $listing->listing_code,

                                'product_code' => $item->product->product_code,
                                'product_name' => $item->product->name,

                                'variant_code' => null,
                                'variant_name' => null,

                                // CONSOLIDATED PER PACKAGE
                                'order_qty' => $remain,
                                'ship_qty'  => 0,


                                'pack_size' => $pkg->pack_size,
                                'pack_unit' => $pkg->pack_unit,
                                'pack_type_unit' => $pkg->pack_type_unit,

                                'pack_price' => 0,
                                'per_unit_price' => 0,

                                'discount_amount' => 0,
                                'discount_type' => null,

                                'taxable_amount' => 0,
                                'tax_amount' => 0,
                                'total_amount' => 0,
                            ]);

                            $createdMarketItems->push([
                                'marketItem' => $marketItem,
                                'package'    => $pkg,
                                'remain'     => $remain,
                            ]);

                            // mark package processed
                            $listingService->markPackageSoldFromCutoff($pkg);
                        }
                    }

                    unset($marketItem, $pkg, $remain, $item);
                    /*
                     |--------------------------------------------------------------------------
                     | STEP 2 — CREATE SHIPMENT PACKAGES (SINGLE PICKUP UNITS)
                     |--------------------------------------------------------------------------
                     | ShipmentPackage is ALWAYS qty = 1
                     | Linked with market_order_item_id
                     |--------------------------------------------------------------------------
                     */

                    $shipment = Shipment::where('seller_id', $seller->id)
                        ->where('origin_depot_id', $depot->depot_id) // This one is for dispatch so always from DEPOT
                        ->where('destination_market_id', $marketOrder->shipping_fulfillment_location_id)  // Destunation of user
                        ->available()
                        ->first();

                    if (!$shipment) {

                        $shipment = Shipment::create([
                            'shipment_date' => now()->toDateString(),
                            'shipment_type' => ShipmentTypeEnum::MARKET_DISPATCH->value,
                            'seller_id' => $seller->id,

                            'origin_type' => ShipmentTypeEnum::DEPOT->value,
                            'origin_depot_id' => $depot->depot_id, // This one is for dispatch so always from DEPOT

                            'destination_type' => ShipmentTypeEnum::MARKET->value,
                            'destination_market_id' => $marketOrder->shipping_fulfillment_location_id,

                            'status' => ShipmentStatusEnum::PENDING->value,
                            'is_seller_dropoff' => $listing->is_seller_dropoff,
                        ]);
                    }

                    foreach ($createdMarketItems as $row) {

                        $marketItem = $row['marketItem'];
                        $pkg        = $row['package'];
                        $remain     = $row['remain'];

                        for ($i = 1; $i <= $remain; $i++) {

                            $sellerPackage = SellerPackage::with(['shipmentPackage'])
                                ->where('seller_id', $seller->id)
                                ->where('product_listing_package_id', $marketItem->product_listing_package_id)
                                ->where('product_listing_item_id', $marketItem->product_listing_item_id)
                                ->where('is_used', false)
                                ->first();

                            if ($sellerPackage) {


                                $shipmentPackage =  ShipmentPackage::create([
                                    'shipment_id' => $shipment->id,
                                    'seller_package_id' => $sellerPackage->id,

                                    'source' => MarketOrder::class,
                                    'source_id' => $marketOrder->id,

                                    'source_item' => MarketOrderItem::class,
                                    'source_item_id' => $marketItem->id,

                                    'seller_id' => $seller->id,

                                    'product_listing_package_id' => $marketItem->product_listing_package_id,
                                    'product_listing_item_id' => $marketItem->product_listing_item_id,
                                    'product_listing_id' => $marketItem->product_listing_id ?? null,

                                    'product_id' => $marketItem->product_id,
                                    'product_variant_id' => $marketItem->product_variant_id,

                                    'qty' => 1,
                                    'pack_size' => $pkg->pack_size,
                                    'pack_unit' => $pkg->pack_unit,
                                    'pack_price' => $pkg->pack_price,
                                    'pack_type_unit' => $pkg->pack_type_unit,

                                    'package_number_market' => ShipmentPackage::generatePackageNumber(null, null, $marketOrder->id),
                                    'package_number_seller' => $sellerPackage?->shipmentPackage?->package_number_seller,

                                    'is_seller_dropoff' => $listing->is_seller_dropoff,
                                ]);

                                $sellerPackage->is_used = true;
                                $sellerPackage->save();

                                // mark market item ship qty
                                if ($sellerPackage->shipmentPackage && empty($sellerPackage->shipmentPackage->package_number_market)) {
                                    $sellerPackage->shipmentPackage->package_number_market = $shipmentPackage->package_number_market; // update market package number to seller package for reference;
                                    $sellerPackage->shipmentPackage->save();
                                }

                                //
                            } else {
                                //
                                $marketOrder->flags = array_merge($marketOrder->flags ?? [], [OrderFlagsEum::ORDER_ITEM_SELLER_PACKAGE_UNAVAILABLE->value]);
                                $marketOrder->save();

                                // throw exception
                                throw new RuntimeException("Seller Package unavailable for MarketOrderItem ID: {$marketItem->id}, Market Order ID: {$marketOrder->id}");

                                Log::warning("Seller Package unavailable for MarketOrderItem ID: {$marketItem->id}, Market Order ID: {$marketOrder->id}");
                                // continue; // If no available seller package then skip to avoid creating shipment package without seller package
                            }
                        }
                    }



                    //
                }

                /*
                 |--------------------------------------------------------------------------
                 | FINAL SHIPMENT FLOW CREATION
                 |--------------------------------------------------------------------------
                 */
                // app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::PICKUP->value);
                // app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::TRANSFER->value);
                // app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::DISPATCH->value);
            });

            // Log::info('CutOff Job FINISHED SUCCESS');
        } catch (Throwable $e) {

            Log::error('CutOff Job FAILED', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
