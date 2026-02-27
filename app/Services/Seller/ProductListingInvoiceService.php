<?php

namespace App\Services\Seller;

use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingInvoice;
use App\Models\Master\Setting\MstBusinessSetting;
use App\Services\Seller\Product\ProductListingChargePreviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

            $invoice = ProductListingInvoice::create([
                'product_listing_id' => $productListing->id,
                'invoice_date' => now(),
                'invoice_path' => null,
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
        // $combineItems = $this->extractSoldItems($productListing);
        // if (empty($combineItems)) {
        //     throw new \Exception('No sold package found for this product listing.');
        // }        // [$charges, $totalRecevableData] = $this->buildChargesByOrderItems(
        //     $combineItems,
        //     $seller->charge_level_code,
        //     $productListing->is_seller_dropoff
        // );


        $productListingPackages = $productListing->listingItems
            ->flatMap(fn($item) => $item->listingPackages)
            ->filter(fn($pkg) => ($pkg->sold_qty - $pkg->reverse_qty) ?? 0 > 0) // Only consider packages with sold quantity
            ->values()
            ->all();

        [$charges, $totalRecevableData] = $this->buildChargesByListingPackage(
            $productListingPackages,
            $seller->charge_level_code,
            $productListing->is_seller_dropoff
        );

        $shippingAddress = $productListing->fulfillmentLocation->address;
        $billingAddress  = $shippingAddress;

        $business = MstBusinessSetting::getOrCreate()
            ->load(['billAddress', 'address']);

        $pdf = Pdf::loadView(
            'pdf.listing_invoice',
            [
                'productListing'    => $productListing,
                'invoice'           => $invoice,
                'business'          => $business,
                'billingAddress'    => $billingAddress,
                'shippingAddress'   => $shippingAddress,
                // 'combineItems'      => $combineItems,
                'productListingPackages' => $productListingPackages,
                'charges'           => $charges,
                'totalRecevableData' => $totalRecevableData,
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

                if ($package->sold_qty <= 0) continue;

                if ($package->orderItem) {
                    $orderItems[] = $package->orderItem;
                }

                // market Order we ignoring due to market bill photo directly need to show 
                // if ($package->marketOrderItem && $productListing->is_sell_to_market) {
                //     $marketOrderItems[] = $package->marketOrderItem;
                // }
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



    // protected function buildChargesByOrderItem(array $combineItems, string $chargeLevelCode, bool $isSellerDropoff): array
    // {
    //     $previewService = app(ProductListingChargePreviewService::class);

    //     $previewData = $previewService->preview(
    //         collect($combineItems)->map(fn($item) => [
    //             'order_qty'       => $item->order_qty,
    //             'pack_size'       => $item->pack_size,
    //             'pack_unit'       => $item->pack_unit,
    //             'pack_type_unit'  => $item->pack_type_unit,
    //             'pack_price'      => $item->pack_price,
    //             'per_kg_price'    => $item->per_kg_price,
    //         ])->toArray(),
    //         $chargeLevelCode,
    //         $isSellerDropoff
    //     );

    //     // SAFE INIT (your earlier bug fixed)
    //     $charges = collect($previewData['charges'] ?? [])
    //         ->map(fn($c) => (object)$c)
    //         ->all();

    //     $totalRecevableData = (object)[
    //         'gross_amount'        => $previewData['gross_amount'] ?? 0,
    //         'charge_tax'          => $previewData['charge_tax'] ?? 0,
    //         'total_charge_amount' => $previewData['total_charge_amount'] ?? 0,
    //         'net_receivable'      => $previewData['net_receivable'] ?? 0,
    //     ];

    //     return [$charges, $totalRecevableData];
    // }


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
