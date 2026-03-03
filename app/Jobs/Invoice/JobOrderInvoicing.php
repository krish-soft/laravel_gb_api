<?php

namespace App\Jobs\Invoice;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Services\Invoice\InvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobOrderInvoicing implements ShouldQueue
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

                $order = Order::with('orderInvoices')->findOrFail($orderId);

                if (count($order->orderInvoices) > 0) {
                    // Invoice already exists, we can choose to skip or update
                    Log::info("Invoice already exists for Order Number: {$order->order_number}, skipping invoice data generation.");
                    return;
                }

                // INDUSTRY SAFE:
                // this will create OR repair automatically
                if (
                    (in_array($order->order_status, [
                        OrderStatusEnum::CONFIRMED->value,
                        OrderStatusEnum::INVOICED->value
                    ]) && in_array($order->delivery_status, [OrderStatusEnum::DELIVERED->value]))
                    && in_array($order->payment_status, [PaymentStatusEnum::PAID->value])
                    // && !in_array($order->order_status, [OrderStatusEnum::REFUNDED->value, OrderStatusEnum::CANCELLED->value])
                ) {
                    $invoice = $invoiceService->generateOrderInvoiceData($order, $this->isEnforce);

                    // Mark Order Invoice
                    $order->order_status = OrderStatusEnum::INVOICED->value;
                    $order->save();
                }

                // optional log
                // Log::info("Invoice ready for {$order->order_number}");

            } catch (Throwable $e) {

                Log::error('Order Invoice Job Failed', [
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
