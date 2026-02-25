<?php

namespace App\Console\Commands\Invoice;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Queue\QueueEnum;
use App\Jobs\Invoice\JobOrderInvoice;
use App\Models\Buyer\Order\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class OrderInvoiceCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:order
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices for orders that do not have invoices yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //

        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Orders from {$startDate} to {$endDate}");


        // 
        $orders = Order::query()
            // ->whereBetween('created_at', [
            //     \Carbon\Carbon::parse($startDate)->startOfDay(),
            //     \Carbon\Carbon::parse($endDate)->endOfDay(),
            // ])
            ->whereBetween('order_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->whereIn('order_status', [
                OrderStatusEnum::SHIPPED->value,
                OrderStatusEnum::DELIVERED->value,
                OrderStatusEnum::COMPLETED->value
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
                ->chunk(10) // batch size per buyer
                ->each(function ($chunk) use (&$jobs) {
                    $jobs[] = new JobOrderInvoice($chunk->toArray());
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        Bus::batch($jobs)
            ->name('Order Invoice Generation Batch (Grouped by Buyer)')
            ->onQueue(QueueEnum::INVOICE->value) // assign entire batch to cutoff queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
