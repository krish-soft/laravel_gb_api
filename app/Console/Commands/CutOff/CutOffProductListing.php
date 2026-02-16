<?php

namespace App\Console\Commands\CutOff;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use App\Models\Seller\Product\ProductListing;
use App\Jobs\CutOff\JobProductListing;

class CutOffProductListing extends Command
{
    protected $signature = 'cut-off:product-listing 
                            {startDate?} 
                            {endDate?}';

    protected $description = 'Batch cutoff for product listings.';

    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");

        /*
    |--------------------------------------------------------------------------
    | STEP 1 — Pull ONLY listings eligible for cutoff
    |--------------------------------------------------------------------------
    */
        $listings = ProductListing::query()
            ->select(['id', 'seller_id']) // IMPORTANT → reduce memory
            ->where('is_active', true)
            ->where('is_expired', false)
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])

            ->whereHas('listingItems.listingPackages', function ($q) {
                $q->where('is_locked', false)
                    ->where('is_sold', false)
                    ->whereRaw('qty > sold_qty');
            })
            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();

        if ($listings->isEmpty()) {
            $this->warn('No listings eligible for cutoff.');
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
                ->chunk(5) // batch size per seller
                ->each(function ($chunk) use (&$jobs) {

                    $jobs[] = new JobProductListing($chunk->toArray());
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
            ->name('CutOff Product Listing Batch (Grouped by Seller)')
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }



    //
}
