<?php

namespace App\Jobs\Invoice;

use App\Models\Buyer\Order\Order;
use App\Services\Invoice\InvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobOrderInvoicing implements ShouldQueue, ShouldBeUnique
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
        return (string) "inv_direct_order_" . $this->orderId;
    }

    public function handle(): void
    {
        //
        $invoiceService = app(InvoiceService::class);

        $order = Order::with('invoices')
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
                $invoice = $invoiceService->generateOrderInvoiceData($order, $this->isEnforce);
            }

            // optional log
            // Log::info("Invoice ready for {$order->order_number}");

        } catch (Throwable $e) {

            // Log::error('Order Invoice Job Failed', [
            //     'order_id' => $this->orderId,
            //     'message'  => $e->getMessage(),
            //     'file'     => $e->getFile(),
            //     'line'     => $e->getLine(),
            // ]);

            throw $e;
        }





        //
    }
}
