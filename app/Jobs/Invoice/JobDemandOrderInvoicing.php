<?php

namespace App\Jobs\Invoice;

use App\Models\Buyer\Order\DemandOrder;
use App\Services\Invoice\InvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobDemandOrderInvoicing implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected int $orderId;
    protected bool $isEnforce;

    public function __construct(int $orderId,  $isEnforce = false)
    {
        $this->orderId = $orderId;
        $this->isEnforce = $isEnforce;
    }

    public function uniqueId(): string
    {
        return (string) "inv_demand_order_" . $this->orderId;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $invoiceService = app(InvoiceService::class);

        $order = DemandOrder::with('invoices')
            ->lockForUpdate()
            ->find($this->orderId);

        if (!$order) {
            return;
        }

        try {

            if (count($order->invoices) > 0) {
                // Invoice already exists, we can choose to skip or update
                // Log::info("Invoice already exists for Order Number: {$order->order_number}, skipping invoice data generation.");
                return;
            }

            // INDUSTRY SAFE:
            // this will create OR repair automatically
            if ($order->isEligibleForInvoicing() || $this->isEnforce) {
                $invoice = $invoiceService->generateDemandOrderInvoiceData($order, $this->isEnforce);
            }
        } catch (Throwable $e) {

            // Log::error('Demand order Invoice Job Failed', [
            //     'order_id' => $this->orderId,
            //     'message'  => $e->getMessage(),
            //     'file'     => $e->getFile(),
            //     'line'     => $e->getLine(),
            // ]);

            throw $e;
        }
    }




    //

}
