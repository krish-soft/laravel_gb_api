<?php

namespace App\Jobs\Invoice;

use App\Models\Seller\Product\ProductListing;
use App\Services\Seller\ProductListingInvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobProductListingInvoice implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $productListingIds;

    public function __construct(array $productListingIds)
    {
        $this->productListingIds = $productListingIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $invoiceService = app(ProductListingInvoiceService::class);

        foreach ($this->productListingIds as $productListingId) {

            try {

                $productListing = ProductListing::with('productListingInvoice')->findOrFail($productListingId);

                // INDUSTRY SAFE:
                // this will create OR repair automatically
                $invoice = $invoiceService->repairOrGenerateInvoiceForListing($productListing);



                // optional log
                // Log::info("Invoice ready for {$order->order_number}");

            } catch (Throwable $e) {

                Log::error('Product Listing Invoice Job Failed', [
                    'product_listing_id' => $productListingId,
                    'message'  => $e->getMessage(),
                    'file'     => $e->getFile(),
                    'line'     => $e->getLine(),
                ]);

                throw $e;
            }
        }
    }
}
