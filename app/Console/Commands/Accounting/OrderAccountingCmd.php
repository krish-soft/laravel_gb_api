<?php

namespace App\Console\Commands\Accounting;

use App\Enum\Queue\QueueEnum;
use App\Jobs\Accounting\JobOrderAccounting;
use App\Models\Buyer\Order\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class OrderAccountingCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:order
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Order accounting process.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");

        $orders = Order::query()
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
            $jobs[] = new JobOrderAccounting($order->id);
        }

        Bus::batch($jobs)
            ->name('CutOff Order Accounting Batch')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value)
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
