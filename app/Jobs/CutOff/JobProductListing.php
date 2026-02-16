<?php

namespace App\Jobs\CutOff;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
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
use Throwable;

class JobProductListing implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $listingIds;

    public function __construct(array $listingIds)
    {
        $this->listingIds = $listingIds;
    }

    public function handle(ProductListingService $listingService): void
    {
        Log::info('CutOff Job START', ['listingIds' => $this->listingIds]);

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
                    if ($listing->is_active && !$listing->is_expired) {
                        $listing->is_active  = false;
                        $listing->is_expired = true;
                        $listing->expires_at = now();
                    }

                    if ($listing->is_locked) {
                        $listing->save();
                        continue;
                    }

                    $listing->is_locked = true;
                    $listing->save();

                    if (!$listing->is_sell_to_market) {
                        continue;
                    }

                    $marketId = $listing->seller->primaryDepot->depot->market_id;

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

                            $remain = $pkg->qty - $pkg->sold_qty;

                            if ($remain <= 0) {
                                continue;
                            }

                            $marketItem = MarketOrderItem::create([
                                'market_order_id' => $marketOrder->id,
                                'market_order_number' => $marketOrder->market_order_number,

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

                    /*
                     |--------------------------------------------------------------------------
                     | STEP 2 — CREATE SHIPMENT PACKAGES (SINGLE PICKUP UNITS)
                     |--------------------------------------------------------------------------
                     | ShipmentPackage is ALWAYS qty = 1
                     | Linked with market_order_item_id
                     |--------------------------------------------------------------------------
                     */

                    foreach ($createdMarketItems as $row) {

                        $marketItem = $row['marketItem'];
                        $pkg        = $row['package'];
                        $remain     = $row['remain'];

                        for ($i = 0; $i < $remain; $i++) {

                            ShipmentPackage::create([
                                'market_order_id' => $marketOrder->id,
                                'market_order_item_id' => $marketItem->id,

                                'shipment_date' => now()->toDateString(),

                                'order_type' => 'market',
                                'market_id' => $marketId,

                                'buyer_id' => null,
                                'seller_id' => $listing->seller_id,

                                'pickup_fulfillment_location_id' => $listing->fulfillment_location_id,
                                'shipping_fulfillment_location_id' => $marketOrder->shipping_fulfillment_location_id,

                                'product_code' => $marketItem->product_code,
                                'product_name' => $marketItem->product_name,

                                // SINGLE PICKUP UNIT
                                'qty' => 1,
                                'pack_size' => $pkg->pack_size,
                                'pack_price' => $pkg->pack_price,
                                'pack_unit' => $pkg->pack_unit,
                                'pack_type_unit' => $pkg->pack_type_unit,

                                'package_number' => ShipmentPackage::generatePackageNumber(null, $marketId),
                                'status' => ShipmentStatusEnum::PENDING->value,

                                'pickup_depot_id' => $marketOrder->depot_id,
                                'shipping_depot_id' => $marketOrder->depot_id,

                                'is_buyer_pickup' => $marketOrder->is_buyer_pickup,
                                'is_seller_dropoff' => $listing->is_seller_dropoff,
                            ]);
                        }
                    }
                }

                /*
                 |--------------------------------------------------------------------------
                 | FINAL SHIPMENT FLOW CREATION
                 |--------------------------------------------------------------------------
                 */
                app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::PICKUP->value);
                app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::TRANSFER->value);
                app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::DISPATCH->value);
            });

            Log::info('CutOff Job FINISHED SUCCESS');
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
