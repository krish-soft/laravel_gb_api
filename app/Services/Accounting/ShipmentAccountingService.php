<?php

namespace App\Services\Accounting;

use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Accounting\AccountLedger;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentAccountingService
{
    public function recordPaidOrder(Order $order): void
    {
        try {

            DB::transaction(function () use ($order) {
                // Get shipment Packages

                $shipmentPackages = $order->shipmentPackages;

                foreach ($shipmentPackages as $package) {

                    // get shipment group and shipment and base on it get driver shipment and driver
                    $shipmentGroup = $package->packageGroup;
                    if (!$shipmentGroup) {
                        Log::warning("Shipment group not found for Shipment Package ID: {$package->id}");
                        continue;
                    }

                    $shipment = $shipmentGroup->shipment;
                    if (!$shipment) {
                        Log::warning("Shipment not found for Shipment Group ID: {$shipmentGroup->id}");
                        continue;
                    }

                    // Assume when pacakge is picked up or not base on ship qty or shipment Package status
                    $driverShipment = $shipment->driverShipment;

                    if (
                        !$driverShipment
                        || in_array(
                            $driverShipment->status,
                            [
                                DriverShipmentStatusEnum::CANCELLED->value,
                                DriverShipmentStatusEnum::PENDING->value,
                            ]
                        )
                    ) {
                        Log::warning("Driver shipment status not valid for Shipment Number: {$shipment->shipment_number}");
                        continue;
                    }

                    $chargeService = app(ChargeCalculationService::class);
                    $chargesData = $chargeService->calculateDeliveryCharges(
                        $driverShipment->driver?->charge_level_code,
                        [
                            [
                                'order_qty'  => $package->qty,
                                'pack_size'  => $package->pack_size,
                                'pack_price' => $package->pack_price,
                                'pack_unit'  => $package->pack_unit,
                                'pack_type_unit' => $package->pack_type_unit,
                            ]
                        ],
                        false,
                        $package->is_seller_dropoff,
                    );

                    $totalDeliveryCharge = $chargesData['total_charge_amount'];



                    //


                }
            });
        } catch (\Exception $e) {
            Log::error("Order Accounting for Order ID: {$order->id}, Error: " . $e->getMessage());
            throw $e;
        }
    }



    /**
     * Proper idempotency guard
     */
    private function ledgerExists(
        int $accountId,
        string $entryType,
        string $sourceType,
        int $sourceId
    ): bool {
        return AccountLedger::where('account_id', $accountId)
            ->where('entry_type', $entryType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }
}
