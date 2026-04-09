<?php

namespace App\Console\Commands\Invoice;

use App\Enum\Queue\QueueEnum;
use App\Jobs\Invoice\JobDemandOrderInvoicing;
use App\Models\Buyer\Order\DemandOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DemandOrderInvoicingCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoicing:demand-order
                            {startDate?} 
                            {endDate?} {isEnforce=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices data from demand orders that do not have invoices yet.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $isEnforce = filter_var($this->argument('isEnforce'), FILTER_VALIDATE_BOOLEAN);

        $this->info("Invoicing data from {$startDate} to {$endDate} " . ($isEnforce ? '(Enforce Rebuild)' : ''));

        $orders = DemandOrder::query()
            ->select(['id']) // reduce memory
            ->whereBetween('order_date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->eligibleForInvoicing()
            ->orderBy('buyer_id')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->warn('No demand orders eligible for invoicing.');
            return;
        }

        $jobs = [];

        foreach ($orders as $order) {
            $jobs[] = new JobDemandOrderInvoicing($order->id, $isEnforce);
        }

        Bus::batch($jobs)
            ->name('Demand Order Invoicing Batch')
            ->onQueue(QueueEnum::INVOICING_CUTOFF->value)
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
