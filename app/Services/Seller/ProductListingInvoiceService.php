<?php

namespace App\Services\Seller;

use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingInvoice;
use App\Models\Master\Setting\MstBusinessSetting;
use App\Services\Seller\Product\ProductListingChargePreviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductListingInvoiceService
{

    /*
    |--------------------------------------------------------------------------
    | PUBLIC ENTRY
    |--------------------------------------------------------------------------
    */

    public function generateInvoiceForListing(ProductListing $productListing): ProductListingInvoice
    {
        return DB::transaction(function () use ($productListing) {

            $business = MstBusinessSetting::getOrCreate();

            $invoice = ProductListingInvoice::create([
                'product_listing_id' => $productListing->id,
                'invoice_date' => now(),
                'invoice_path' => null,
                'business_bill_addr_code' => $business->bill_addr_code,
            ]);

            return $this->buildInvoicePdf($invoice, $productListing);
        });
    }


    public function repairOrGenerateInvoiceForListing(ProductListing $productListing, bool $isEnforce = false): ProductListingInvoice
    {
        $existing = $productListing->productListingInvoice;

        if ($existing) {

            if (
                $existing->invoice_path &&
                Storage::disk('private')->exists($existing->invoice_path)
                && !$isEnforce
            ) {
                return $existing;
            }

            // rebuild SAME invoice (DO NOT CREATE NEW)
            return $this->buildInvoicePdf($existing, $productListing);
        }

        return $this->generateInvoiceForListing($productListing);
    }


    /*
    |--------------------------------------------------------------------------
    | CORE BUILDER (NO DUPLICATION NOW)
    |--------------------------------------------------------------------------
    */
    protected function buildInvoicePdf(
        ProductListingInvoice $invoice,
        ProductListing $productListing
    ): ProductListingInvoice {

        $productListing->load([
            'seller',
            'fulfillmentLocation.address',
            'listingItems.listingPackages',
        ]);

        $seller = $productListing->seller;

        // We dont want to use order Base 
        $combineItems = $this->extractSoldItems($productListing);

        if (empty($combineItems)) {
            throw new RuntimeException('No sold package found for this product listing.');
        }

        [$charges, $totalRecevableData] = $this->buildChargesByOrderItems(
            $combineItems,
            $seller->charge_level_code,
            $productListing->is_seller_dropoff
        );

        // To Show view seprate both 
        $regularOrderItems = collect($combineItems)->filter(fn($item) => $item->type === 'REGULAR');
        $marketOrderItems = collect($combineItems)->filter(fn($item) => $item->type === 'MARKET');

        // Get Summary only to show 
        $productListing->loadMissing('listingItems.listingPackages.productListingItem.product');

        $productListingPackages = $productListing->listingItems
            ->flatMap(fn($item) => $item->listingPackages)
            ->filter(fn($pkg) => ($pkg->sold_qty - $pkg->reverse_qty) > 0)
            ->values();

        /// Just loop listed qty, sold qty and reverse qty for summary in invoice, we are not using this for charge calculation so no issue of order item or market order item. We can directly use listing package data.
        $listingSummary = collect($productListingPackages)
            ->map(function ($pkg) {

                $pkg->loadMissing('productListingItem.product');

                $listingItem = $pkg->productListingItem;

                if (!$listingItem || !$listingItem->product) {
                    return null; // skip broken row safely
                }

                return [
                    'product_name' => $listingItem->product->name,
                    'pack_size' => $pkg->pack_size,
                    'pack_unit' => $pkg->pack_unit,
                    'pack_type_unit' => $pkg->pack_type_unit,
                    'listed_qty' => $pkg->qty,
                    'sold_qty' => $pkg->sold_qty,
                    'reverse_qty' => $pkg->reverse_qty,
                ];
            })
            ->filter()
            ->groupBy(fn($item) => "{$item['product_name']}-{$item['pack_size']}-{$item['pack_unit']}-{$item['pack_type_unit']}")
            ->map(fn($group) => (object) [
                'product_name' => $group->first()['product_name'],
                'pack_size' => $group->first()['pack_size'],
                'pack_unit' => $group->first()['pack_unit'],
                'pack_type_unit' => $group->first()['pack_type_unit'],
                'listed_qty' => $group->sum('listed_qty'),
                'sold_qty' => $group->sum('sold_qty'),
                'reverse_qty' => $group->sum('reverse_qty'),
                'net_sold_qty' => $group->sum('sold_qty') - $group->sum('reverse_qty'),
            ])
            ->values()
            ->all();

        // [$charges, $totalRecevableData] = $this->buildChargesByListingPackage(
        //     $productListingPackages,
        //     $seller->charge_level_code,
        //     $productListing->is_seller_dropoff
        // );

        $shippingAddress = $productListing->fulfillmentLocation->address;
        $billingAddress  = $shippingAddress;

        $business = MstBusinessSetting::getOrCreate()
            ->load(['billAddress', 'address']);

        $pdf = Pdf::loadView(
            // 'pdf.listing_invoice',
            'pdf.listing_invoice_by_order',
            [
                'productListing'    => $productListing,
                'invoice'           => $invoice,
                'business'          => $business,
                'billingAddress'    => $billingAddress,
                'shippingAddress'   => $shippingAddress,
                'combineItems'      => $combineItems,
                'listingSummary'      => $listingSummary,
                // 'productListingPackages' => $productListingPackages,
                'charges'           => $charges,
                'totalRecevableData' => $totalRecevableData,

                'regularOrderItems' => $regularOrderItems,
                'marketOrderItems' => $marketOrderItems,
            ]
        )->setPaper('a4', 'portrait');

        $path = $this->buildPdfPath($seller->user_code, $invoice->invoice_number);

        Storage::disk('private')->put($path, $pdf->output());

        $invoice->update([
            'invoice_path' => $path
        ]);

        return $invoice->fresh();
    }


    /*
    |--------------------------------------------------------------------------
    | EXTRACT SOLD ITEMS (ONE PLACE ONLY NOW)
    |--------------------------------------------------------------------------
    */

    protected function extractSoldItems(ProductListing $productListing): array
    {
        $orderItems = [];
        $marketOrderItems = [];

        foreach ($productListing->listingItems as $item) {
            foreach ($item->listingPackages as $package) {

                if ($package->sold_qty - $package->reverse_qty <= 0) continue;

                if ($package->orderItem) {
                    $package->orderItem->type = 'REGULAR';
                    $orderItems[] = $package->orderItem;
                }

                // market Order we ignoring due to market bill photo directly need to show 
                if ($package->marketOrderItem && $productListing->is_sell_to_market) {
                    $package->marketOrderItem->type = 'MARKET';
                    $marketOrderItems[] = $package->marketOrderItem;
                }
            }
        }

        return $productListing->is_sell_to_market
            ? array_merge($orderItems, $marketOrderItems)
            : $orderItems;
    }


    /*
    |--------------------------------------------------------------------------
    | BUILD CHARGES (NO DUPLICATION)
    |--------------------------------------------------------------------------
    */
    protected function buildChargesByListingPackage(array $packages, string $chargeLevelCode, bool $isSellerDropoff): array
    {
        $previewService = app(ProductListingChargePreviewService::class);

        $previewData = $previewService->preview(
            collect($packages)->map(fn($pkg) => [
                'order_qty'       => $pkg->sold_qty, // we only care about sold qty for charge calculation
                'pack_size'       => $pkg->pack_size,
                'pack_unit'       => $pkg->pack_unit,
                'pack_type_unit'  => $pkg->pack_type_unit,
                'pack_price'      => $pkg->pack_price,
                'per_kg_price'    => $pkg->per_kg_price,
            ])->toArray(),
            $chargeLevelCode,
            $isSellerDropoff
        );

        // SAFE INIT (your earlier bug fixed)
        $charges = collect($previewData['charges'] ?? [])
            ->map(fn($c) => (object)$c)
            ->all();

        $totalRecevableData = (object)[
            'gross_amount'        => $previewData['gross_amount'] ?? 0,
            'charge_tax'          => $previewData['charge_tax'] ?? 0,
            'total_charge_amount' => $previewData['total_charge_amount'] ?? 0,
            'net_receivable'      => $previewData['net_receivable'] ?? 0,
        ];

        return [$charges, $totalRecevableData];
    }



    protected function buildChargesByOrderItems(array $combineItems, string $chargeLevelCode, bool $isSellerDropoff): array
    {
        $previewService = app(ProductListingChargePreviewService::class);

        $previewData = $previewService->preview(
            collect($combineItems)->map(fn($item) => [
                'order_qty'       => $item->ship_qty, // we only care about shipped qty for charge calculation
                'pack_size'       => $item->pack_size,
                'pack_unit'       => $item->pack_unit,
                'pack_type_unit'  => $item->pack_type_unit,
                'pack_price'      => $item->pack_price,
                'per_kg_price'    => $item->per_kg_price,
            ])->toArray(),
            $chargeLevelCode,
            $isSellerDropoff
        );

        // SAFE INIT (your earlier bug fixed)
        $charges = collect($previewData['charges'] ?? [])
            ->map(fn($c) => (object)$c)
            ->all();

        $totalRecevableData = (object)[
            'gross_amount'        => $previewData['gross_amount'] ?? 0,
            'charge_tax'          => $previewData['charge_tax'] ?? 0,
            'total_charge_amount' => $previewData['total_charge_amount'] ?? 0,
            'net_receivable'      => $previewData['net_receivable'] ?? 0,
        ];

        return [$charges, $totalRecevableData];
    }


    /*
    |--------------------------------------------------------------------------
    | PATH
    |--------------------------------------------------------------------------
    */

    protected function buildPdfPath(string $userCode, string $invoiceNumber): string
    {
        return "invoices/{$userCode}/{$invoiceNumber}.pdf";
    }
}
