<?php

namespace App\Jobs\Accounting;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Seller\Product\ProductListing;
use App\Services\Accounting\OrderAccountingService;
use App\Services\Accounting\ProductListingAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobProductListingAccounting implements ShouldQueue
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


        try {

            DB::transaction(function () {

                $listings = ProductListing::whereIn('id', $this->productListingIds)->get();

                foreach ($listings as $listing) {
                    // Log::info("Processing Product Listing ID: {$listing->id} for Seller ID: {$listing->seller_id}");
                    app(ProductListingAccountingService::class)
                        ->recordProductListing($listing);

                    $listing->status = OrderStatusEnum::ACCOUNTED->value; // or any status to indicate it's processed
                    $listing->save();
                }
                //
            });
        } catch (Throwable $e) {

            Log::error('Product Listing Accounting Job Failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
