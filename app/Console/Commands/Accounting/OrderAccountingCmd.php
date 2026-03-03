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
        //

        // we can proceed with order accounting cutoff around 13 once all order reached

        // 1. All Orders

        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");


        $orders = Order::query()
            // ->whereBetween('created_at', [
            //     \Carbon\Carbon::parse($startDate)->startOfDay(),
            //     \Carbon\Carbon::parse($endDate)->endOfDay(),
            // ])
            ->whereBetween('order_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('buyer_id')
            ->orderBy('id')
            ->get();


        if ($orders->isEmpty()) {
            $this->warn('No orders eligible for cutoff.');
            return;
        }


        $groupedByBuyers = $orders->groupBy('buyer_id');

        $jobs = [];

        //

        foreach ($groupedByBuyers as $buyerId => $buyerOrders) {

            $buyerOrders->pluck('id')
                ->chunk(15) // batch size per buyer
                ->each(function ($chunk) use (&$jobs) {
                    $jobs[] = new JobOrderAccounting($chunk->toArray());
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        Bus::batch($jobs)
            ->name('CutOff Order Accounting Batch (Grouped by Buyer)')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value) // assign entire batch to cutoff queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
