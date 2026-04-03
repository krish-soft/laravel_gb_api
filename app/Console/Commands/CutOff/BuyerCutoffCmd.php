<?php

namespace App\Console\Commands\Cutoff;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Queue\QueueEnum;
use App\Jobs\CutOff\JobBuyerCutoffDemandOrder;
use App\Jobs\CutOff\JobBuyerCutoffDirectOrder;
use App\Jobs\Cutoff\JobCutOffProductListing;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\Order;
use App\Models\Seller\Product\ProductListing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class BuyerCutoffCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cutoff:buyer
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to perform buyer cutoff operations.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $startDate = Carbon::parse(
            $this->argument('startDate') ?? now()->subDay()->toDateString()
        )->startOfDay();

        $endDate = Carbon::parse(
            $this->argument('endDate') ?? now()->toDateString()
        )->endOfDay();

        $this->info("Buyer cutoff from {$startDate->toDateString()} to {$endDate->toDateString()}");

        $orderJobs = $this->buildJobs(
            Order::class,
            JobBuyerCutoffDirectOrder::class,
            $startDate,
            $endDate
        );

        $demandJobs = $this->buildJobs(
            DemandOrder::class,
            JobBuyerCutoffDemandOrder::class,
            $startDate,
            $endDate
        );


        $listingJobs = self::buildListingJobs(
            $startDate,
            $endDate
        );

        $batches = [];

        // Do not change the order of batches

        if (!empty($orderJobs)) {
            $batches[] = Bus::batch($orderJobs)
                ->allowFailures(false)
                ->name('Buyer Direct Order Cutoff')
                ->onQueue(QueueEnum::BUYER_CUTOFF->value);
        }

        if (!empty($demandJobs)) {
            $batches[] = Bus::batch($demandJobs)
                ->allowFailures(false)
                ->name('Buyer Demand Order Cutoff')
                ->onQueue(QueueEnum::BUYER_CUTOFF->value);
        }

        if (!empty($listingJobs)) {
            $batches[] = Bus::batch($listingJobs)
                ->allowFailures(false)
                ->name('Seller Listing Cutoff')
                ->onQueue(QueueEnum::LISTING_CUTOFF->value);
        }

        if (!empty($batches)) {
            Bus::chain($batches)->dispatch();
        }

        $this->info('Buyer cutoff chain dispatched.');
    }


    // Common method to build jobs for both Order and DemandOrder models
    private function buildJobs(string $model, string $jobClass, Carbon $startDate, Carbon $endDate): array
    {
        $jobs = [];

        $model::query()
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('delivery_status', OrderStatusEnum::PENDING->value)
            ->where('payment_status', PaymentStatusEnum::PAID->value)
            ->where('is_cutoff', false) // Important to avoid re-processing already cutoff orders
            ->orderBy('buyer_id')
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$jobs, $jobClass) {

                $orders->groupBy('buyer_id')->each(function ($buyerOrders) use (&$jobs, $jobClass) {

                    $buyerOrders->pluck('id')
                        ->chunk(15)
                        ->each(function ($chunk) use (&$jobs, $jobClass) {
                            $jobs[] = new $jobClass($chunk->toArray());
                        });
                });
            });

        return $jobs;
    }


    public static function buildListingJobs(Carbon $startDate, Carbon $endDate): array
    {
        $jobs = [];

        Log::info("Date Range for Listing Cutoff: {$startDate->toDateString()} to {$endDate->toDateString()}");

        ProductListing::query()
            ->select(['id', 'seller_id'])
            ->where('is_active', true)
            ->where('is_expired', false)
            ->whereBetween(
                'listing_date',
                [
                    $startDate->toDateString(),
                    $endDate->toDateString()

                    // $startDate,
                    // $endDate
                ]
            )
            ->chunkById(500, function ($listings) use (&$jobs) {

                $listings->groupBy('seller_id')->each(function ($sellerListings) use (&$jobs) {

                    $sellerListings->pluck('id')
                        ->chunk(15)
                        ->each(function ($chunk) use (&$jobs) {

                            $jobs[] = new JobCutOffProductListing(
                                $chunk->toArray()
                            );
                        });
                });
            });

        Log::info("Total Listing Cutoff Jobs Generated: " . count($jobs));

        return $jobs;
    }

    //
}
