<?php

namespace App\Jobs\Invoice;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Services\Buyer\Order\OrderInvoiceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobOrderInvoice implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $orderIds;
    protected bool $isEnforce;

    public function __construct(array $orderIds,  $isEnforce = false)
    {
        $this->orderIds = $orderIds;
        $this->isEnforce = $isEnforce;
    }

    public function handle(): void
    {
        $invoiceService = app(OrderInvoiceService::class);

        foreach ($this->orderIds as $orderId) {

            try {

                $order = Order::with('orderInvoice')->findOrFail($orderId);

                // INDUSTRY SAFE:
                // this will create OR repair automatically
                $invoice = $invoiceService->generateInvoiceForOrder($order, $this->isEnforce);

                // Mark Order Invoice
                $order->order_status = OrderStatusEnum::INVOICED->value;
                $order->save();

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
    }
}
