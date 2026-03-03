<?php

namespace App\Console\Commands\Invoice;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Queue\QueueEnum;
use App\Jobs\Invoice\JobOrderInvoice;
use App\Jobs\Invoice\JobOrderInvoicing;
use App\Models\Buyer\Order\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class OrderInvoicingCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoicing:order
                            {startDate?} 
                            {endDate?} {isEnforce=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices data from order/marketorder/productlising and settlement data for orders that do not have invoices yet.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //

        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $isEnforce = filter_var($this->argument('isEnforce'), FILTER_VALIDATE_BOOLEAN); // To Rebuild again in case setltment done again or any reason to enforce rebuild

        $this->info("Invoicing data from {$startDate} to {$endDate} " . ($isEnforce ? '(Enforce Rebuild)' : ''));


        // 
        $orders = Order::query()
            ->whereBetween('order_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->whereIn('order_status', [
                OrderStatusEnum::CONFIRMED->value,
                OrderStatusEnum::ACCOUNTED->value,
                OrderStatusEnum::INVOICED->value
            ])->WhereIn('delivery_status', [
                OrderStatusEnum::SHIPPED->value,
                OrderStatusEnum::DELIVERED->value,
            ])
            ->orderBy('buyer_id')
            ->orderBy('id')
            ->get();


        if ($orders->isEmpty()) {
            $this->warn('No buyer orders eligible for invoicing.');
            return;
        }

        $groupedByBuyers = $orders->groupBy('buyer_id');

        $jobs = [];

        //
        foreach ($groupedByBuyers as $buyerId => $buyerOrders) {

            $buyerOrders->pluck('id')
                ->chunk(15) // batch size per buyer // for each buyer we can create one job with all orders, or chunk if needed
                ->each(function ($chunk) use (&$jobs, $isEnforce) {
                    $jobs[] = new JobOrderInvoicing($chunk->toArray(), $isEnforce);
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        Bus::batch($jobs)
            ->name('Order Invoicing Batch (Grouped by Buyer)')
            ->onQueue(QueueEnum::INVOICING->value) // assign entire batch to invoicing queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
