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
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");

        $orders = MarketOrder::query()
            ->select(['id']) // reduce memory
            ->whereBetween('order_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->eligibleForAccounting()
            ->orderBy('market_id')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->warn('No orders eligible for cutoff.');
            return;
        }

        $jobs = [];

        foreach ($orders as $order) {
            $jobs[] = new JobMarketOrderAccounting($order->id);
        }

        Bus::batch($jobs)
            ->name('CutOff Market Order Accounting Batch')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value)
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
