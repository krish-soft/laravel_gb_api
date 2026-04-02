<?php

namespace App\Console\Commands\Cutoff;

use App\Enum\Queue\QueueEnum;
use App\Jobs\CutOff\JobCutOffProductListing;
use App\Jobs\Cutoff\JobSellerCutoff;
use App\Models\Seller\Product\ProductListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SellerCutoffCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cutoff:seller
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to perform seller cutoff operations.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Seller cutoff from {$startDate} to {$endDate}");

        // TODO:: Notify sellers about cutoff

        // Product listing cutoff logic will be implemented here, similar to CutOffProductListing command.

        $listings = ProductListing::query()
            ->select(['id', 'seller_id']) // IMPORTANT → reduce memory
            ->where('is_active', true)
            // ->where('is_expired', false)
            ->where('is_cutoff', false)
            ->whereBetween('listing_date', [
                $startDate,
                $endDate
            ])
            ->whereHas('listingItems.listingPackages', function ($q) {
                // $q->where('is_locked', false)
                //     ->where('is_sold', false)
                //     ->whereRaw('qty > sold_qty');
                $q->whereRaw('qty > sold_qty');
            })
            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();

        if ($listings->isEmpty()) {
            $this->warn('No listings eligible for cutoff.');
            return;
        }

        $groupedBySeller = $listings->groupBy('seller_id');

        $jobs = [];

        foreach ($groupedBySeller as $sellerId => $sellerListings) {

            $sellerListings->pluck('id')
                ->chunk(15) // batch size per seller
                ->each(function ($chunk) use (&$jobs) {

                    $jobs[] = new JobSellerCutoff($chunk->toArray());
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        Bus::batch($jobs)
            ->name('CutOff Product Listing Batch (Grouped by Seller)')
            ->onQueue(QueueEnum::SELLER_CUTOFF->value) // assign entire batch to cutoff queue
            ->dispatch();

        $this->info('Seller Cutoff batch dispatched successfully.');


        //
    }
}
