<?php

namespace App\Console\Commands\Cutoff;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Queue\QueueEnum;
use App\Jobs\CutOff\JobBuyerCutoffDemandOrder;
use App\Jobs\CutOff\JobBuyerCutoffDirectOrder;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

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

        Bus::chain([
            Bus::batch($orderJobs)
                ->name('Buyer Direct Order Cutoff')
                ->onQueue(QueueEnum::BUYER_CUTOFF->value),

            Bus::batch($demandJobs)
                ->name('Buyer Demand Order Cutoff')
                ->onQueue(QueueEnum::BUYER_CUTOFF->value),

        ])->dispatch();

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
            // ->where('is_cutoff', false)
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


    //
}
