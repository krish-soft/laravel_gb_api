<?php

namespace App\Jobs\Accounting;

use App\Models\Common\Invoice\Invoice;
use App\Services\Accounting\InvoiceAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobInvoiceAccounting implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected int $invoiceId;

    public function __construct(int $invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    public function uniqueId(): string
    {
        return (string) "accnt_invoice_" . $this->invoiceId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $invoice = Invoice::lockForUpdate()->find($this->invoiceId);

        if (!$invoice) {
            return;
        }
        try {
            DB::transaction(function () use ($invoice) {

                // Log::info("Processing Invoice ID: {$invoice->id} for User ID: {$invoice->user_id}");
                app(InvoiceAccountingService::class)
                    ->recordInvoice($invoice);

                //
            });
        } catch (Throwable $e) {

            // Log::error('Invoice Accounting Job Failed', [
            //     'message' => $e->getMessage(),
            //     'file'    => $e->getFile(),
            //     'line'    => $e->getLine(),
            // ]);

            throw $e;
        }
    }
}
