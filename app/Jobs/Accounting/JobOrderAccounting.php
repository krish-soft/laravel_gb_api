<?php

namespace App\Jobs\Accounting;

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

class JobOrderAccounting implements ShouldQueue
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


                    ## We are not checking any status of packages in any cutfoff so first we have to check the delivery status of each pacakges its delivered and tehre is no pending then mark order as Delivered and then we can check the payment status and order status for accounting entry.
                    $packages = $order->shipmentPackages;
                    $pendingPackageCount = $packages->where('status', OrderStatusEnum::PENDING->value)->count();
                    $deliveredPackageCount = $packages->where('status', OrderStatusEnum::DELIVERED->value)->count();

                    if ($pendingPackageCount <= 0 && $deliveredPackageCount > 0) {
                        $order->delivery_status = OrderStatusEnum::DELIVERED->value;
                        $order->save();
                    }


                    if (
                        (in_array($order->order_status, [
                            OrderStatusEnum::CONFIRMED->value,
                            OrderStatusEnum::ACCOUNTED->value,
                            OrderStatusEnum::INVOICED->value,
                        ])
                            &&  in_array($order->delivery_status, [OrderStatusEnum::DELIVERED->value]))
                        && in_array($order->payment_status, [PaymentStatusEnum::PAID->value])
                        // && !in_array($order->order_status, [OrderStatusEnum::REFUNDED->value, OrderStatusEnum::CANCELLED->value])
                    ) {
                        app(OrderAccountingService::class)
                            ->recordPaidOrder($order, $order->payment);
                        // 

                    }
                }
                //
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
