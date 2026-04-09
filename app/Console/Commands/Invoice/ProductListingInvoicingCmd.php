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
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $this->info("Invoicing from {$startDate} to {$endDate}");

        $listings = ProductListing::query()
            ->select(['id']) // reduce memory
            ->where('is_active', true)
            ->where('is_expired', true)
            ->where('is_cutoff', true)
            ->whereBetween('listing_date', [$startDate, $endDate])
            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();

        if ($listings->isEmpty()) {
            $this->warn('No listings eligible for invoicing.');
            return;
        }

        $jobs = [];

        foreach ($listings as $listing) {
            $jobs[] = new JobProductListingInvoicing($listing->id);
        }

        Bus::batch($jobs)
            ->name('Product Listing Invoicing Batch')
            ->onQueue(QueueEnum::INVOICING_CUTOFF->value)
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }


    //
}
