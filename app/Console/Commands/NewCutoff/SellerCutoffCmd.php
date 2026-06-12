<?php

namespace App\Console\Commands\NewCutoff;

use App\Enum\Queue\QueueEnum;
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
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $this->info("Seller cutoff from {$startDate} to {$endDate}");

        $listings = ProductListing::query()
            ->select(['id']) // reduce memory
            ->where('is_active', true)
            ->where('is_cutoff', false)
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
            $jobs[] = new JobSellerCutoff($listing->id);
        }

        Bus::batch($jobs)
            ->name('Seller Cutoff Batch')
            ->onQueue(QueueEnum::SELLER_CUTOFF->value)
            ->dispatch();

        $this->info('Seller Cutoff batch dispatched successfully.');
    }
}
