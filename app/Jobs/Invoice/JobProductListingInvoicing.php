<?php

namespace App\Jobs\Invoice;


use App\Models\Seller\Product\ProductListing;
use App\Services\Invoice\InvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobProductListingInvoicing implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected int $productListingId;
    protected bool $isEnforce;

    public function __construct(int $productListingId,  $isEnforce = false)
    {
        $this->productListingId = $productListingId;
        $this->isEnforce = $isEnforce;
    }

    public function uniqueId(): string
    {
        return (string) "inv_product_listing_" . $this->productListingId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $invoiceService = app(InvoiceService::class);

        $productListing = ProductListing::lockForUpdate()
            ->find($this->productListingId);

        if (!$productListing) {
            return;
        }



        try {

            $invoice = $invoiceService->generateProductListingInvoiceData($productListing, $this->isEnforce);
            //
        } catch (Throwable $e) {

            // Log::error('Product Listing Invoice Job Failed', [
            //     'product_listing_id' => $this->productListingId,
            //     'message'  => $e->getMessage(),
            //     'file'     => $e->getFile(),
            //     'line'     => $e->getLine(),
            // ]);

            throw $e;
        }





        //
    }
}
