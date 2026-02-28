<?php

namespace App\Console\Commands\Accounting;

use App\Enum\Queue\QueueEnum;
use App\Jobs\Accounting\JobProductListingAccounting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use App\Models\Seller\Product\ProductListing;
use App\Jobs\CutOff\JobCutOffProductListing;
use Illuminate\Support\Facades\Log;

class ProductListingAccountingCmd extends Command
{
    protected $signature = 'accounting:product-listing
                            {startDate?} 
                            {endDate?}';

    protected $description = 'Batch accounting for product listings.';

    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Product Listing accounting from {$startDate} to {$endDate}");

        /*
    |--------------------------------------------------------------------------
    | STEP 1 — Pull ONLY listings eligible for accounting
    |--------------------------------------------------------------------------
    */
        $listings = ProductListing::query()
            ->select(['id', 'seller_id']) // IMPORTANT → reduce memory
            ->where('is_active', true)
            ->where('is_cutoff', true)
            // ->whereBetween('created_at', [
            //     \Carbon\Carbon::parse($startDate)->startOfDay(),
            //     \Carbon\Carbon::parse($endDate)->endOfDay(),
            // ])
            ->whereBetween('listing_date', [
                $startDate,
                $endDate
            ])
            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();

        if ($listings->isEmpty()) {
            $this->warn('No product listings eligible for cutoff.');
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | STEP 2 — GROUP BY SELLER
    |--------------------------------------------------------------------------
    */
        $groupedBySeller = $listings->groupBy('seller_id');

        $jobs = [];

        // Log::info("Total product listings for accounting: {$listings->count()} across " . $groupedBySeller->count() . " sellers.");

        /*
    |--------------------------------------------------------------------------
    | STEP 3 — Chunk per seller
    |--------------------------------------------------------------------------
    */
        foreach ($groupedBySeller as $sellerId => $sellerListings) {

            $sellerListings->pluck('id')
                ->chunk(5) // batch size per seller
                ->each(function ($chunk) use (&$jobs) {

                    $jobs[] = new JobProductListingAccounting($chunk->toArray());
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
            ->name('Accounting Product Listing Batch (Grouped by Seller)')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value) // assign entire batch to accounting queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }



    //
}
