<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Models\Common\Accounting\Account;
use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingPackage;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Product;

class ProductListingAccountingService
{
    //


    // Mainly Seller accounting.

    public function recordProductListing(ProductListing $productListing)
    {
        //

        // If product listing inactive then ignore 
        // and not expired even ignore that becasue once cutoff done then need to start
        if (!$productListing->is_active || !$productListing->is_expired) {
            return;
        }

        $accountingService = app(AccountingService::class);
        $chargeService = app(ChargeCalculationService::class);

        DB::transaction(function () use ($productListing, $accountingService, $chargeService) {

            $productListing->load([
                'listingItems',
                'listingItems.product',
                'listingItems.productVariant',
                'listingItems.listingPackages',
            ]);

            $totalSellingAmount = 0;
            $packageArr = [];

            $seller = $productListing->seller;
            $sellerAccount = Account::getOrCreateByOwner(
                AccountOwnerTypeEnum::SELLER->value,
                $seller->id
            );

            foreach ($productListing->listingItems as $listingItem) {
                foreach ($listingItem->listingPackages as $package) {
                    // We only need to record delivery charges
                    $packageArr[] =
                        [
                            'order_qty'  => $package->sold_qty, // only what is sold.
                            'pack_size'  => $package->pack_size,
                            'pack_price' => $package->pack_price,
                            'pack_unit'  => $package->pack_unit,
                            'pack_type_unit' => $package->pack_type_unit,
                        ];
                    // Calculate total selling amount for this package (for future use, e.g., revenue recognition)
                    $totalSellingAmount += ($package->sold_qty * $package->pack_price);
                }
            }

            $platformChargesData  = $chargeService->calculatePlatformFee(
                $seller->charge_level_code,
                $totalSellingAmount,
                $packageArr
            );
            $totalPlatformChargeAmount = $platformChargesData['total_amount'] ?? 0;

            ##          


            if (!$accountingService->ledgerExists(
                $sellerAccount->id,
                AccountEntryTypeEnum::PLATFORM_CHARGE_BASE->value,
                ProductListing::class,
                $productListing->id
            )) {
                $accountingService->createLedger($sellerAccount, [
                    'description' => "Platform fee for product listing #{$productListing->listing_code}",
                    'credit' => 0,
                    'debit'  =>  $totalPlatformChargeAmount,
                    'entry_type' => AccountEntryTypeEnum::PLATFORM_CHARGE_BASE->value,
                    'status' => LedgerStatusEnum::AVAILABLE->value,
                    'source_type' => ProductListing::class,
                    'source_id' => $productListing->id,
                    'source_code' => $productListing->listing_code,
                    'common_reference' => $productListing->listing_code,
                ]);
            }


            /**
             *  Delivery Charge Calculation:
             * - If it's seller dropoff → calculate delivery charge and record as receivable from seller
             */

            // If not drop off we have to pickup
            if (!$productListing->is_seller_dropoff) {

                $deliveryChargesData  = $chargeService->calculateDeliveryCharges(
                    $seller->charge_level_code,
                    $packageArr,
                    false, // product listing so buyer never gone come on this point
                    $productListing->is_seller_dropoff ?? false
                );

                $totalDeliveryChargeAmount = $deliveryChargesData['total_charge_amount'] ?? 0;


                ## 
                // It's seller so we have to take from them so its debit
                if (!$accountingService->ledgerExists(
                    $sellerAccount->id,
                    AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                    ProductListing::class,
                    $productListing->id
                )) {
                    $accountingService->createLedger($sellerAccount, [
                        'description' => "Delivery charge for product listing #{$productListing->listing_code}",
                        'credit' => 0,
                        'debit'  =>  $totalDeliveryChargeAmount,
                        'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => ProductListing::class,
                        'source_id' => $productListing->id,
                        'source_code' => $productListing->listing_code,
                        'common_reference' => $productListing->listing_code,
                    ]);
                }
            }


            // Record delivery charge as receivable for seller
        });






        //
    }





    //

}
