<?php

namespace App\Console\Commands\Accounting;

use App\Enum\Common\Invoice\InvoiceStatusEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Queue\QueueEnum;
use App\Jobs\Accounting\JobInvoiceAccounting;
use App\Models\Common\Invoice\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class InvoiceAccountingCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:invoice
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices for accounting purposes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate') ?? now()->toDateString();

        $this->info("Invoice accounting from {$startDate} to {$endDate}");

        $invoices = Invoice::query()
            ->select(['id']) // reduce memory
            ->whereNotIn('status', [
                InvoiceStatusEnum::ACCOUNTED->value,
                OrderStatusEnum::INVOICED->value
            ])
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->warn('No invoices eligible for accounting.');
            return;
        }

        $jobs = [];

        foreach ($invoices as $invoice) {
            $jobs[] = new JobInvoiceAccounting($invoice->id);
        }

        Bus::batch($jobs)
            ->name('Accounting Invoice Batch')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value)
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }



    //
}
