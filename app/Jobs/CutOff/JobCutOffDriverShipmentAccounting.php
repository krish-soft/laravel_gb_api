<?php

namespace App\Jobs\CutOff;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Delivery\DriverShipment;
use App\Services\Accounting\OrderAccountingService;
use App\Services\Accounting\ShipmentAccountingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobCutOffDriverShipmentAccounting implements ShouldQueue
{
    use Queueable, Batchable;

    protected array $driverShipmentIds;

    public function __construct(array $driverShipmentIds)
    {
        $this->driverShipmentIds = $driverShipmentIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        // Log::info('Starting CutOff Driver Shipment Accounting Job', [
        //     'driver_shipment_ids' => $this->driverShipmentIds,
        // ]);

        try {

            DB::transaction(function () {

                $driverShipments = DriverShipment::with([
                    'shipment.shipmentGroups.shipmentPackage',
                    'shipment.shipmentGroups.shipmentPackage.order',
                    'shipment.shipmentGroups.shipmentPackage.marketOrder',
                    'driver',
                ])->whereIn('id', $this->driverShipmentIds)
                    ->lockForUpdate()
                    ->get();


                foreach ($driverShipments as $driverShipment) {


                    if (
                        !in_array($driverShipment->status, [
                            OrderStatusEnum::PENDING->value,
                            OrderStatusEnum::CANCELLED->value,
                            // OrderStatusEnum::COMPLETED->value,
                        ])

                    ) {

                        app(ShipmentAccountingService::class)
                            ->recordDriverShipmentAccount($driverShipment);

                        // 
                        $driverShipment->status = OrderStatusEnum::COMPLETED->value;
                        $driverShipment->save();
                    }
                }
                //
            });
        } catch (Throwable $e) {

            Log::error('CutOff Driver Shipment Accounting Job Failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
