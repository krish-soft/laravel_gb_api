<?php

namespace App\Jobs\Invoice;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Seller\Product\ProductListing;
use App\Services\Invoice\InvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobProductListingInvoicing implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $productListingIds;
    protected bool $isEnforce;

    public function __construct(array $productListingIds,  $isEnforce = false)
    {
        $this->productListingIds = $productListingIds;
        $this->isEnforce = $isEnforce;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $invoiceService = app(InvoiceService::class);

        foreach ($this->productListingIds as $productListingId) {

            try {

                $productListing = ProductListing::findOrFail($productListingId);

                Log::info("Starting invoicing for Product Listing ID: {$productListingId}");
                // this will create OR repair automatically
                $invoice = $invoiceService->generateProductListingInvoiceData($productListing, $this->isEnforce);

                // Mark Product Listing Invoice
                $productListing->status = OrderStatusEnum::INVOICED->value;
                $productListing->save();

                // optional log
                // Log::info("Invoice ready for {$productListing->listing_number}");

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




        //
    }
}
