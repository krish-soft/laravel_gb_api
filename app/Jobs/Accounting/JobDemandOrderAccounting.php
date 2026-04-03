<?php

namespace App\Jobs\Accounting;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\Order;
use App\Services\Accounting\DemandOrderAccountingService;
use App\Services\Accounting\OrderAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobDemandOrderAccounting implements ShouldQueue
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

                $orders = DemandOrder::with([
                    'payment',
                    'buyer',
                    'demandOrderItems',
                    'demandOrderCharges',
                    'shipmentPackages',
                ])
                    ->whereIn('id', $this->orderIds)
                    ->lockForUpdate()
                    ->get();


                foreach ($orders as $order) {

                    // update delivery status
                    $order->updateDeliveryStatusFromPackages();

                    // check accounting eligibility
                    if ($order->isEligibleForAccounting()) {

                        app(DemandOrderAccountingService::class)
                            ->recordPaidOrder($order, $order->payment);
                    }
                }
            });
        } catch (Throwable $e) {

            Log::error('CutOff Accounting Job Failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
