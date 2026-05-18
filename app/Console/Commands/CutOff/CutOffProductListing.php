<?php

namespace App\Console\Commands\CutOff;

use App\Enum\Queue\QueueEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use App\Models\Seller\Product\ProductListing;
use App\Jobs\CutOff\JobCutOffProductListing;

class CutOffProductListing extends Command
{
    protected $signature = 'cutoff:product-listing
                            {startDate?} 
                            {endDate?}';

    protected $description = 'Batch cutoff for product listings.';

    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");

        $listings = ProductListing::query()
            ->select(['id']) // reduce memory
            ->where('is_active', true)
            ->where('is_expired', false)
            ->whereBetween('listing_date', [$startDate, $endDate])
            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();

        if ($listings->isEmpty()) {
            $this->warn('No listings eligible for cutoff.');
            return;
        }

        $jobs = [];

        foreach ($listings as $listing) {
            $jobs[] = new JobCutOffProductListing($listing->id);
        }

        Bus::batch($jobs)
            ->name('CutOff Product Listing Batch')
            ->onQueue(QueueEnum::LISTING_CUTOFF->value)
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }



    //
}
