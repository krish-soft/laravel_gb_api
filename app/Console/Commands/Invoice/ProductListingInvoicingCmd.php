<?php

namespace App\Console\Commands\Invoice;

use App\Enum\Queue\QueueEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use App\Models\Seller\Product\ProductListing;
use App\Jobs\Invoice\JobProductListingInvoicing;

class ProductListingInvoicingCmd extends Command
{
    protected $signature = 'invoicing:product-listing
                            {startDate?} 
                            {endDate?}';

    protected $description = 'Batch invoicing for product listings.';

    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Invoicing from {$startDate} to {$endDate}");

        /*
    |--------------------------------------------------------------------------
    | STEP 1 — Pull ONLY listings eligible for invoicing
    |--------------------------------------------------------------------------
    */
        $listings = ProductListing::query()
            ->select(['id', 'seller_id']) // IMPORTANT → reduce memory
            ->where('is_active', true)
            ->where('is_expired', true)
            ->where('is_cutoff', true)
            ->whereBetween('listing_date', [
                $startDate,
                $endDate
            ])
            // ->whereHas('listingItems.listingPackages', function ($q) {
            //     // $q->where('is_locked', false)
            //     //     ->where('is_sold', false)
            //     //     ->whereRaw('qty > sold_qty');
            //     $q->whereRaw('qty > sold_qty');
            // })
            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();

        if ($listings->isEmpty()) {
            $this->warn('No listings eligible for invoicing.');
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | STEP 2 — GROUP BY SELLER
    |--------------------------------------------------------------------------
    */
        $groupedBySeller = $listings->groupBy('seller_id');

        $jobs = [];

        /*
    |--------------------------------------------------------------------------
    | STEP 3 — Chunk per seller
    |--------------------------------------------------------------------------
    */
        foreach ($groupedBySeller as $sellerId => $sellerListings) {

            $sellerListings->pluck('id')
                ->chunk(15) // batch size per seller
                ->each(function ($chunk) use (&$jobs) {

                    $jobs[] = new JobProductListingInvoicing($chunk->toArray());
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | STEP 4 — Dispatch batch
    |--------------------------------------------------------------------------
    */
        Bus::batch($jobs)
            ->name('Product Listing Invoicing Batch (Grouped by Seller)')
            ->onQueue(QueueEnum::INVOICING->value) // assign entire batch to invoicing queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }



    //
}
