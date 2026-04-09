<?php

namespace App\Jobs\Accounting;


use App\Models\Buyer\Order\Order;
use App\Services\Accounting\OrderAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobOrderAccounting implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public function uniqueId(): string
    {
        return (string) "accnt_direct_order_" . $this->orderId;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::with([
            'payment',
            'buyer',
            'orderItems',
            'orderCharges',
            'shippingFulfillmentLocation.address',
            'shipmentPackages',
        ])
            ->lockForUpdate()
            ->find($this->orderId);

        if (!$order) {
            return;
        }

        try {

            DB::transaction(function () use ($order) {
                // update delivery status
                $order->updateDeliveryStatusFromPackages();
                // check accounting eligibility
                if ($order->isEligibleForAccounting()) {

                    app(OrderAccountingService::class)
                        ->recordPaidOrder($order, $order->payment);
                }
            });
        } catch (Throwable $e) {

            // Log::error('CutOff Accounting Job Failed', [
            //     'message' => $e->getMessage(),
            //     'file'    => $e->getFile(),
            //     'line'    => $e->getLine(),
            // ]);

            throw $e;
        }
    }
}
