<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Delivery\DriverShipment;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverShipmentAccountingService
{



    public function recordDriverShipmentAccount(DriverShipment $driverShipment): void
    {
        // Log::info("Recording accounting for Driver Shipment ID: {$driverShipment->id}, Shipment Number: {$driverShipment->shipment->shipment_number}");

        try {

            DB::transaction(function () use ($driverShipment) {

                // Log::info(json_encode($driverShipment->toArray(), JSON_PRETTY_PRINT));

                if (in_array(
                    $driverShipment->status,
                    [
                        DriverShipmentStatusEnum::CANCELLED->value,
                        DriverShipmentStatusEnum::PENDING->value
                    ]
                )) {
                    Log::warning("Driver shipment status not valid for Shipment Number: {$driverShipment->shipment->shipment_number}");
                    return;
                }

                $accounting = app(AccountingService::class);
                $chargeService = app(ChargeCalculationService::class);

                $shipment = $driverShipment->shipment;
                $shipmentGroups = $shipment->shipmentGroups ?? $shipment->shipment_groups;

                $driverId = $driverShipment->driver_id;
                $driverAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::DELIVERY->value,
                    $driverId
                );

                foreach ($shipmentGroups as $group) {

                    $package = $group->shipmentPackage ?? $group->shipment_package;
                    $packageType = $package->type;

                    if (!$package) {
                        Log::warning("No package found for Shipment Group ID: {$group->id}");
                        continue;
                    }

                    $orderNumber = null;
                    $isMarketOrder = false;

                    if ($packageType == 'market') {
                        $isMarketOrder = true;
                        $orderNumber = $package->marketOrder?->market_order_number ?? $package->market_order?->market_order_number;
                    } else {
                        $orderNumber = $package->order?->order_number;
                    }

                    // Calculate delivery charge for the package
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

                    // Log::info("Calculated delivery charge for Driver Package ID: {$package->package_number}, Charge: {$totalDeliveryCharge}");

                    // We need to pay to driver doesnt matter if its picked or not or delivered or not, as long as its not cancelled or pending
                    // So we record the ledger entry as soon as the driver shipment is processed in this job


                    $isPickup = $shipment->shipment_type === ShipmentStatusEnum::PICKUP->value;
                    $isDispatch = $shipment->shipment_type === ShipmentStatusEnum::DISPATCH->value;
                    $isTransfer = $shipment->shipment_type === ShipmentStatusEnum::TRANSFER->value;

                    // Record ledger entry for driver payout

                    if ($isPickup) {
                        // Means Farmer to Depot

                        // $package->is_seller_dropoff its already converted in shipment package to pick or not so we can ignore logic

                        if ($package->seller_status == ShipmentStatusEnum::NOT_PICKED_UP->value) {

                            // From Seller need to deduct
                            $pkgSeller = $package->seller;
                            $sellerAccount = Account::getOrCreateByOwner(
                                AccountOwnerTypeEnum::SELLER->value,
                                $pkgSeller->id
                            );

                            // Delivery charge
                            if (!$this->ledgerExists(
                                $sellerAccount->id,
                                AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                                ShipmentPackage::class,
                                $package->id,
                                $packageType
                            )) {
                                // Driver has picked up the package so we can consider this delivery charge as platform revenue as well because driver is paid and platform is earning commission on this delivery charge as well so we can record platform revenue ledger for this delivery charge as well
                                $accounting->createLedger($sellerAccount, [
                                    'description' => "Reversal delivery Charges for Order #{$orderNumber}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                                    'credit' => 0,
                                    'debit'  =>  $totalDeliveryCharge,
                                    'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => get_class($package),
                                    'source_id' => $package->id,
                                    'source_code' => $package->shipment_package_number,
                                    'reference' => $package->shipment_package_number,
                                    'common_reference' => $orderNumber,
                                ]);
                            }

                            // Item charge need to reverse 
                            if (!$this->ledgerExists(
                                $sellerAccount->id,
                                AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                                ShipmentPackage::class,
                                $package->id,
                                $packageType
                            )) {
                                // Driver has picked up the package so we can consider this item charge as platform revenue as well because driver is paid and platform is earning commission on this item charge as well so we can record platform revenue ledger for this item charge as well
                                $accounting->createLedger($sellerAccount, [
                                    'description' => "Reversal item charges for Order #{$orderNumber}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                                    'credit' => 0,
                                    'debit'  =>  $package->pack_price * $package->qty, // we can consider pack_price as item price for simplicity and because we dont have direct link to order item here, we can improve this in future by linking order item in shipment package and then getting item price from order item
                                    'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => get_class($package),
                                    'source_id' => $package->id,
                                    'source_code' => $package->shipment_package_number,
                                    'reference' => $package->shipment_package_number,
                                    'common_reference' => $orderNumber,
                                ]);
                            }

                            // To Buyer need to add
                            $pkgBuyer = $package->buyer;
                            $buyerAccount = Account::getOrCreateByOwner(
                                AccountOwnerTypeEnum::BUYER->value,
                                $pkgBuyer->id
                            );

                            // Delivery charge
                            if (!$this->ledgerExists(
                                $buyerAccount->id,
                                AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                                ShipmentPackage::class,
                                $package->id,
                                $packageType
                            )) {
                                // Driver has picked up the package so we can consider this delivery charge as platform revenue as well because driver is paid and platform is earning commission on this delivery charge as well so we can record platform revenue ledger for this delivery charge as well
                                $accounting->createLedger($buyerAccount, [
                                    'description' => "Credits of delivery charges for Order #{$orderNumber}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                                    'credit' => $totalDeliveryCharge,
                                    'debit'  => 0,
                                    'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => get_class($package),
                                    'source_id' => $package->id,
                                    'source_code' => $package->shipment_package_number,
                                    'reference' => $package->shipment_package_number,
                                    'common_reference' => $orderNumber,
                                ]);
                            }

                            if (!$this->ledgerExists(
                                $buyerAccount->id,
                                AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                                ShipmentPackage::class,
                                $package->id,
                                $packageType
                            )) {
                                // Driver has picked up the package so we can consider this delivery charge as platform revenue as well because driver is paid and platform is earning commission on this delivery charge as well so we can record platform revenue ledger for this delivery charge as well
                                $accounting->createLedger($buyerAccount, [
                                    'description' => "Credits of item Charges for Order #{$orderNumber}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                                    'credit' => $package->pack_price * $package->qty, // we can consider pack_price as item price for simplicity and because we dont have direct link to order item here, we can improve this in future by linking order item in shipment package and then getting item price from order item
                                    'debit'  => 0,
                                    'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => get_class($package),
                                    'source_id' => $package->id,
                                    'source_code' => $package->shipment_package_number,
                                    'reference' => $package->shipment_package_number,
                                    'common_reference' => $orderNumber,
                                ]);
                            }


                            // Driver is not earning

                        } else {

                            // Driver is earning

                            if (!$this->ledgerExists(
                                $driverAccount->id,
                                AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                                ShipmentPackage::class,
                                $package->id,
                                $packageType
                            )) {
                                // Driver has picked up the package so we can consider this delivery charge as platform revenue as well because driver is paid and platform is earning commission on this delivery charge as well so we can record platform revenue ledger for this delivery charge as well
                                $accounting->createLedger($driverAccount, [
                                    'description' => "Earnings of delivery Charges for Order #{$orderNumber}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                                    'credit' => $totalDeliveryCharge,
                                    'debit'  => 0,
                                    'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => get_class($package),
                                    'source_id' => $package->id,
                                    'source_code' => $package->shipment_package_number,
                                    'reference' => $package->shipment_package_number,
                                    'common_reference' => $orderNumber,
                                ]);
                            }
                        }
                    } else if ($isDispatch || $isTransfer) {
                        // Means Depot to Buyer or Depot to Depot

                        // Driver is earning
                        if (!$this->ledgerExists(
                            $driverAccount->id,
                            AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                            ShipmentPackage::class,
                            $package->id,
                            $packageType
                        )) {
                            $accounting->createLedger($driverAccount, [
                                'description' => "Earnings of regular delivery Charges for Order #{$orderNumber}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                                'credit' => $totalDeliveryCharge,
                                'debit'  => 0,
                                'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => get_class($package),
                                'source_id' => $package->id,
                                'source_code' => $package->shipment_package_number,
                                'reference' => $package->shipment_package_number,
                                'common_reference' => $orderNumber,
                            ]);
                        }

                        if ($package->buyer_status == ShipmentStatusEnum::RETURNED->value || $package->transfer_status == ShipmentStatusEnum::RETURNED->value) {

                            // Driver is earning extra because of return

                            if (!$this->ledgerExists(
                                $driverAccount->id,
                                AccountEntryTypeEnum::DELIVERY_CHARGE_RETURN->value,
                                ShipmentPackage::class,
                                $package->id,
                                $packageType
                            )) {
                                $accounting->createLedger($driverAccount, [
                                    'description' => "Earnings of return delivery Charges for Order #{$orderNumber}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                                    'credit' => $totalDeliveryCharge,
                                    'debit'  => 0,
                                    'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_RETURN->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => get_class($package),
                                    'source_id' => $package->id,
                                    'source_code' => $package->shipment_package_number,
                                    'reference' => $package->shipment_package_number,
                                    'common_reference' => $orderNumber,
                                ]);
                            }
                        }


                        //
                    }


                    //
                }

                //



            });
        } catch (\Exception $e) {
            // Log::error("Order Accounting for Driver Shipment ID: {$driverShipment->id}, Error: " . $e->getMessage());
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
        int $sourceId,
        string $otherReference = null
    ): bool {
        return AccountLedger::where('account_id', $accountId)
            ->where('entry_type', $entryType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('other_reference', $otherReference) // for driver charges reversal or any other use
            ->exists();
    }
}
