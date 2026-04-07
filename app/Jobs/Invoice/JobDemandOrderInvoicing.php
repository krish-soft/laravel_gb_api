<?php

namespace App\Jobs\Invoice;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\Order;
use App\Services\Invoice\InvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobDemandOrderInvoicing implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $orderIds;
    protected bool $isEnforce;

    public function __construct(array $orderIds,  $isEnforce = false)
    {
        $this->orderIds = $orderIds;
        $this->isEnforce = $isEnforce;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $invoiceService = app(InvoiceService::class);

        foreach ($this->orderIds as $orderId) {

            try {

                $order = DemandOrder::with('invoices')->findOrFail($orderId);

                if (count($order->invoices) > 0) {
                    // Invoice already exists, we can choose to skip or update
                    Log::info("Invoice already exists for Order Number: {$order->order_number}, skipping invoice data generation.");
                    return;
                }

                // INDUSTRY SAFE:
                // this will create OR repair automatically
                if ($order->isEligibleForInvoicing() || $this->isEnforce) {
                    $invoice = $invoiceService->generateDemandOrderInvoiceData($order, $this->isEnforce);
                }

                // optional log
                // Log::info("Invoice ready for {$order->order_number}");

            } catch (Throwable $e) {

                Log::error('Demand order Invoice Job Failed', [
                    'order_id' => $orderId,
                    'message'  => $e->getMessage(),
                    'file'     => $e->getFile(),
                    'line'     => $e->getLine(),
                ]);

                throw $e;
            }
        }




        //
    }
}
