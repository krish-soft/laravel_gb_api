<?php

namespace App\Jobs\Accounting;


use App\Models\Market\MarketOrder;
use App\Services\Accounting\MarketOrderAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobMarketOrderAccounting implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public function uniqueId(): string
    {
        return (string) "accnt_market_order_" . $this->orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = MarketOrder::with([
            'market',
            'marketOrderItems.pickupFulfillmentLocation',
            'shippingFulfillmentLocation.address',
            'shipmentPackages.shipment', // important for filtering shipment type if needed
            'shipmentPackages.seller',
        ])
            ->lockForUpdate()
            ->find($this->orderId);

        if (!$order) {
            return;
        }

        try {

            DB::transaction(function () use ($order) {

                // update delivery status based on packages
                // $order->updateDeliveryStatusFromPackages();

                // check eligibility for accounting
                if ($order->isEligibleForAccounting()) {
                    app(MarketOrderAccountingService::class)->recordPaidOrder($order, $order->payment);
                }
            });
        } catch (Throwable $e) {

            // Log::error('CutOff Accounting Job FAILED', [
            //     'message' => $e->getMessage(),
            //     'file'    => $e->getFile(),
            //     'line'    => $e->getLine(),
            // ]);

            throw $e;
        }
    }
}
