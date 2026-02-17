<?php

namespace App\Jobs\CutOff;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Services\Accounting\OrderAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobCutOffOrderAccounting implements ShouldQueue
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
        //


        try {

            DB::transaction(function () {

                $orders = Order::with([
                    'buyer',
                    'orderItems.pickupFulfillmentLocation',

                    'orderCharges',
                    'shippingFulfillmentLocation.address', // actual shipping location
                    // 'billingAddress', // for invoice
                    // 'shippingAddress', // for invoice

                    // shipment packages for this order
                    'shipmentPackages.buyer',
                    'shipmentPackages.seller',
                ])
                    ->whereIn('id', $this->orderIds)
                    ->lockForUpdate()
                    ->get();



                foreach ($orders as $order) {


                    if (
                        in_array($order->order_status, [
                            OrderStatusEnum::SHIPPED->value,
                            OrderStatusEnum::DELIVERED->value,
                            OrderStatusEnum::CONFIRMED->value,
                        ])
                        &&
                        in_array($order->payment_status, [
                            PaymentStatusEnum::PAID->value,
                        ])
                    ) {

                        app(OrderAccountingService::class)
                            ->recordPaidOrder($order, $order->payment);

                        // 
                        $order->order_status = OrderStatusEnum::COMPLETED->value;
                        $order->save();
                    }
                }
                //
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
