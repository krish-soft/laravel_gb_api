<?php

namespace App\Jobs\NewCutoff;

use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Models\Common\Package\SellerPackage;
use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Seller\Product\ProductListing;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobSellerCutoff implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected int $listingId;

    public function __construct(int $listingId)
    {
        $this->listingId = $listingId;
    }

    public function uniqueId(): string
    {
        return (string) "seller_cutoff_listing_" . $this->listingId;
    }

    public function handle(): void
    {

        $listing = ProductListing::with([
            'seller.primaryDepot.depot.market',
            'listingItems.product',
            // 'listingItems.listingPackages' => fn($q) => $q->available(),
            'listingItems.listingPackages',
        ])
            ->lockForUpdate()
            ->find($this->listingId);

        if (!$listing) {
            return;
        }

        try {

            DB::transaction(function () use ($listing) {


                if ($listing->is_expired || $listing->is_locked || $listing->is_cutoff) {
                    return;
                }

                // Expire logic
                if (!$listing->is_sell_to_market && $listing->total_sold_qty <= 0) {


                    $listing->update([
                        'is_expired' => true,
                        'expires_at' => now(),
                        'is_cutoff'  => true,
                    ]);

                    return;
                }

                $seller = $listing->seller;
                $depot  = $seller->primaryDepot->depot;

                $packagesToCreate = [];

                /*
                     |--------------------------------------------------------------------------
                     | Collect Packages First
                     |--------------------------------------------------------------------------
                     */
                foreach ($listing->listingItems as $item) {

                    if ($item->listingPackages->isEmpty()) {
                        continue;
                    }

                    foreach ($item->listingPackages as $pkg) {

                        $qty = (!$listing->is_sell_to_market && $pkg->sold_qty > 0)
                            ? $pkg->sold_qty
                            : $pkg->qty;

                        if ($qty <= 0) {
                            continue;
                        }

                        $packagesToCreate[] = [
                            'item' => $item,
                            'pkg'  => $pkg,
                            'qty'  => $qty
                        ];
                    }
                }

                // Need to cancel listing if no package to sell to market
                if (!$listing->is_sell_to_market && $listing->total_sold_qty > 0) {
                    $listing->update([
                        'is_expired' => true,
                        'expires_at' => now(),
                        'is_cutoff'  => true,
                    ]);                  
                }

                /*
                     |--------------------------------------------------------------------------
                     | If no packages → skip shipment
                     |--------------------------------------------------------------------------
                     */
                if (empty($packagesToCreate)) {
                    return;
                }

                /*
                     |--------------------------------------------------------------------------
                     | Get or Create Shipment
                     |--------------------------------------------------------------------------
                     */
                $shipment = Shipment::where('seller_id', $seller->id)
                    ->where('origin_flmnt_location_id', $listing->fulfillment_location_id)
                    ->where('destination_depot_id', $depot->id)
                    ->available()
                    ->first();

                if (!$shipment) {

                    $shipment = Shipment::create([
                        'shipment_date' => now()->toDateString(),
                        'shipment_type' => ShipmentTypeEnum::PICKUP->value,
                        'seller_id' => $seller->id,

                        'origin_type' => ShipmentTypeEnum::FULFILLMENT_LOCATION->value,
                        'origin_flmnt_location_id' => $listing->fulfillment_location_id,

                        'destination_type' => ShipmentTypeEnum::DEPOT->value,
                        'destination_depot_id' => $depot->id,

                        'status' => ShipmentStatusEnum::PENDING->value,
                        'is_seller_dropoff' => $listing->is_seller_dropoff,
                    ]);
                }

                /*
                     |--------------------------------------------------------------------------
                     | Create Seller Packages + Shipment Packages
                     |--------------------------------------------------------------------------
                     */
                foreach ($packagesToCreate as $package) {

                    $item = $package['item'];
                    $pkg  = $package['pkg'];
                    $qty  = $package['qty'];

                    for ($i = 0; $i < $qty; $i++) {

                        $sellerPackage = SellerPackage::create([
                            'seller_id' => $seller->id,
                            'product_listing_package_id' => $pkg->id,
                            'product_listing_item_id' => $item->id,
                            'product_listing_id' => $listing->id,
                            'product_id' => $item->product_id,
                            'product_variant_id' => $item->product_variant_id,
                            'package_date' => now()->toDateString(),
                            'is_seller_dropoff' => $listing->is_seller_dropoff,
                        ]);

                        ShipmentPackage::create([
                            'shipment_id' => $shipment->id,
                            'seller_package_id' => $sellerPackage->id,
                            'depot_id' => $depot->id, // because for pickup always to depot                              

                            'product_listing_id' => $listing->id,
                            'product_listing_item_id' => $item->id,
                            'product_listing_package_id' => $pkg->id,

                            'seller_id' => $seller->id,

                            'product_id' => $item->product_id,
                            'product_variant_id' => $item->product_variant_id,

                            'qty' => 1,
                            'pack_size' => $pkg->pack_size,
                            'pack_unit' => $pkg->pack_unit,
                            'pack_price' => $pkg->pack_price,
                            'pack_type_unit' => $pkg->pack_type_unit,

                            'package_number_seller' =>
                            ShipmentPackage::generatePackageNumberSeller($seller->id),

                            'is_seller_dropoff' => $listing->is_seller_dropoff,
                        ]);
                    }
                }

                $listing->update([
                    'is_cutoff' => true
                ]);
                $listing->removeFlag(OrderFlagsEum::CUTOFF_ERROR); // remove cutoff error flag if exist because cutoff process is successful for this listing now
            });
        } catch (Throwable $e) {

            $listing->addFlag(OrderFlagsEum::CUTOFF_ERROR, "Seller Cutoff.  " . $e->getMessage());
            // Log::error('Seller CutOff Job FAILED', [
            //     'message' => $e->getMessage(),
            //     'file'    => $e->getFile(),
            //     'line'    => $e->getLine(),
            // ]);
            throw $e;
        }
    }
}
