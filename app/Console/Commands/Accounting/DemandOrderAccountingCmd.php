<?php

namespace App\Console\Commands\Accounting;

use App\Enum\Queue\QueueEnum;
use App\Jobs\Accounting\JobDemandOrderAccounting;
use App\Models\Buyer\Order\DemandOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DemandOrderAccountingCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:demand-order
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demand order accounting process.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");

        $orders = DemandOrder::query()
            ->whereBetween('order_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->eligibleForAccounting()
            ->orderBy('buyer_id')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->warn('No orders eligible for cutoff.');
            return;
        }

        $jobs = [];

        foreach ($orders as $order) {
            $jobs[] = new JobDemandOrderAccounting($order->id);
        }

        Bus::batch($jobs)
            ->name('CutOff Demand Order Accounting Batch')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value)
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
