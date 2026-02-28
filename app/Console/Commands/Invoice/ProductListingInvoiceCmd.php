<?php

namespace App\Console\Commands\Invoice;

use App\Enum\Queue\QueueEnum;
use App\Jobs\Invoice\JobProductListingInvoice;
use App\Models\Seller\Product\ProductListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ProductListingInvoiceCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:product-listing
                            {startDate?} 
                            {endDate?} 
                            {isEnforce=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices for product listings that do not have invoices yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //

        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $isEnforce = filter_var($this->argument('isEnforce'), FILTER_VALIDATE_BOOLEAN); // To Rebuild again in case setltment done again or any reason to enforce rebuild


        $this->info("Product listings from {$startDate} to {$endDate} " . ($isEnforce ? '(Enforce Rebuild)' : ''));


        // 
        $productListings = ProductListing::query()
            ->whereBetween('listing_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])

            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();


        if ($productListings->isEmpty()) {
            $this->warn('No product listings eligible for cutoff.');
            return;
        }

        $groupedBySellers = $productListings->groupBy('seller_id');

        $jobs = [];

        //

        foreach ($groupedBySellers as $sellerId => $sellerProductListings) {

            $sellerProductListings->pluck('id')
                ->chunk(10) // batch size per seller
                ->each(function ($chunk) use (&$jobs, $isEnforce) {
                    $jobs[] = new JobProductListingInvoice($chunk->toArray(), $isEnforce);
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        Bus::batch($jobs)
            ->name('Product Listing Invoice Generation Batch (Grouped by Seller)')
            ->onQueue(QueueEnum::INVOICE->value) // assign entire batch to cutoff queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
