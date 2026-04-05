<?php

namespace App\Jobs\Accounting;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Market\MarketOrder;
use App\Services\Accounting\MarketOrderAccountingService;
use App\Services\Accounting\OrderAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobMarketOrderAccounting implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $orderIds;

    public function __construct(array $orderIds)
    {
        $this->orderIds = $orderIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {

            DB::transaction(function () {

                $marketOrders = MarketOrder::with([
                    'market',
                    'marketOrderItems.pickupFulfillmentLocation',
                    'shippingFulfillmentLocation.address',
                    'shipmentPackages.shipment', // important for filtering shipment type if needed
                    'shipmentPackages.seller',
                ])
                    ->whereIn('id', $this->orderIds)
                    ->lockForUpdate()
                    ->get();

                $accountingService = app(MarketOrderAccountingService::class);

                foreach ($marketOrders as $order) {

                    // update delivery status based on packages
                    // $order->updateDeliveryStatusFromPackages();

                    // check eligibility for accounting
                    if ($order->isEligibleForAccounting()) {
                        $accountingService->recordPaidOrder($order, $order->payment);
                    }
                }
            });
        } catch (Throwable $e) {

            Log::error('CutOff Accounting Job FAILED', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
