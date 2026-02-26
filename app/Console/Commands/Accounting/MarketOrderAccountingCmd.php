<?php

namespace App\Console\Commands\Accounting;

use App\Enum\Queue\QueueEnum;
use App\Jobs\Accounting\JobMarketOrderAccounting;
use App\Models\Market\MarketOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class MarketOrderAccountingCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
        */
        protected $signature = 'accounting:market-order
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cut off market order accounting process.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //

        // we can proceed with order accounting cutoff around 13 once all order reached

        // 1. All Orders

        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");


        $orders = MarketOrder::query()
            // ->whereBetween('created_at', [
            //     \Carbon\Carbon::parse($startDate)->startOfDay(),
            //     \Carbon\Carbon::parse($endDate)->endOfDay(),
            // ])
            ->whereBetween('order_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('market_id')
            ->orderBy('id')
            ->get();


        if ($orders->isEmpty()) {
            $this->warn('No orders eligible for cutoff.');
            return;
        }


        $groupedByMarkets = $orders->groupBy('market_id');

        $jobs = [];

        //

        foreach ($groupedByMarkets as $marketId => $marketOrders) {
             $marketOrders->pluck('id')
                ->chunk(10) // batch size per market
                ->each(function ($chunk) use (&$jobs) {
                    $jobs[] = new JobMarketOrderAccounting($chunk->toArray());
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        Bus::batch($jobs)
            ->name('CutOff Market Order Accounting Batch (Grouped by Market)')  
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value) // assign entire batch to cutoff queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    

    }
}
