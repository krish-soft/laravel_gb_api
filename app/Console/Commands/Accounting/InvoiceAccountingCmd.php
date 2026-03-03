<?php

namespace App\Console\Commands\Accounting;

use App\Enum\Common\Invoice\InvoiceStatusEnum;
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
        //

        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Invoice accounting from {$startDate} to {$endDate}");


        $invoices = Invoice::query()
            ->select(['id', 'user_id']) // IMPORTANT → reduce memory         
            // ->where('is_locked', false)          
            ->whereNotIn('status', [InvoiceStatusEnum::ACCOUNTED->value]) // only unaccounted invoices
            ->whereBetween('invoice_date', [
                $startDate,
                $endDate
            ])
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->warn('No invoices eligible for accounting.');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 2 — GROUP BY SELLER
        |--------------------------------------------------------------------------
        */
        $groupedByUsers = $invoices->groupBy('user_id');

        $jobs = [];

        // Log::info("Total invoices for accounting: {$invoices->count()} across " . $groupedByUsers->count() . " users.");

        /*
        |--------------------------------------------------------------------------
        | STEP 3 — Chunk per user
        |--------------------------------------------------------------------------
        */
        foreach ($groupedByUsers as $userId => $userInvoices) {

            $userInvoices->pluck('id')
                ->chunk(15) // batch size per user
                ->each(function ($chunk) use (&$jobs) {

                    $jobs[] = new JobInvoiceAccounting($chunk->toArray());
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 4 — Dispatch batch
        |--------------------------------------------------------------------------
        */
        Bus::batch($jobs)
            ->name('Accounting Invoice Batch (Grouped by User)')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value) // assign entire batch to accounting queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');




        //
    }




    //
}
