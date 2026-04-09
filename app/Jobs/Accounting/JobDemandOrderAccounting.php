<?php

namespace App\Jobs\Accounting;

use App\Models\Buyer\Order\DemandOrder;
use App\Services\Accounting\DemandOrderAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobDemandOrderAccounting implements ShouldQueue, ShouldBeUnique
{
    use Queueable, Batchable;

    protected  int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public function uniqueId(): string
    {
        return (string) "accnt_demand_order_" . $this->orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = DemandOrder::with([
            'payment',
            'buyer',
            'demandOrderItems',
            'demandOrderCharges',
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

                    app(DemandOrderAccountingService::class)
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
