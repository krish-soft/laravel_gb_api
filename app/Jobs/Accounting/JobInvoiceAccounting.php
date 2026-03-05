<?php

namespace App\Jobs\Accounting;

use App\Enum\Common\Invoice\InvoiceStatusEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Invoice\Invoice;
use App\Models\Seller\Product\ProductListing;
use App\Services\Accounting\InvoiceAccountingService;
use App\Services\Accounting\OrderAccountingService;
use App\Services\Accounting\ProductListingAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobInvoiceAccounting implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $invoiceIds;

    public function __construct(array $invoiceIds)
    {
        $this->invoiceIds = $invoiceIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //


        try {

            DB::transaction(function () {

                $invoices = Invoice::whereIn('id', $this->invoiceIds)->get();

                foreach ($invoices as $invoice) {
                    // Log::info("Processing Invoice ID: {$invoice->id} for User ID: {$invoice->user_id}");
                    app(InvoiceAccountingService::class)
                        ->recordInvoice($invoice);
                }
                //
            });
        } catch (Throwable $e) {

            Log::error('Invoice Accounting Job Failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
