<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Common\Payment\Payment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Market\MarketOrder;
use App\Models\Market\MarketOrderItem;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MarketOrderAccountingService
{

    public function recordPaidOrder(MarketOrder $marketOrder): void
    {
        DB::transaction(function () use ($marketOrder) {

            $accounting = app(AccountingService::class);

            // Productions
            if (
                // !in_array($marketOrder->payment_status, [
                //     PaymentStatusEnum::PAID->value,
                // ])  || 
                $marketOrder->total_amount <= 0
            ) {
                Log::warning("Market Order ID: {$marketOrder->id} has invalid payment status or total amount for accounting. Payment Status: {$marketOrder->payment_status}, Total Amount: {$marketOrder->total_amount}");
                return;
            }

            /*
            |-------------------------------------------------
            | 1. PLATFORM CLEARING (FULL PAID AMOUNT)
            |-------------------------------------------------
            */
            // $clearing = Account::where('accnt_code', PlatformAccountCodeEnum::PLATFORM_CLEARING->value)->firstOrFail();
            $clearing = Account::getOrCreateByOwner(
                AccountOwnerTypeEnum::PLATFORM->value,
                null,
                PlatformAccountCodeEnum::PLATFORM_TO_MARKET->value,
            );


            if (!$this->ledgerExists(
                $clearing->id,
                AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                MarketOrder::class,
                $marketOrder->id
            )) {
                $accounting->createLedger($clearing, [
                    'description' => "Payment received for Order #{$marketOrder->market_order_number}",
                    'credit' => $marketOrder->total_amount,
                    'debit'  => 0,
                    'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    'status' => LedgerStatusEnum::AVAILABLE->value,
                    'source_type' => MarketOrder::class,
                    'source_id' => $marketOrder->id,
                    'source_code' => $marketOrder->market_order_number,
                    'reference' => $marketOrder->payment_code,
                    'payment_reference' => $marketOrder->gateway_order_id,
                    'common_reference' => $marketOrder->market_market_order_number,
                ]);
            }

            /*
            |-------------------------------------------------
            | 2. SELLER EARNINGS (ITEM TAXABLE AMOUNT ONLY)
            |-------------------------------------------------
            */
            foreach ($marketOrder->marketOrderItems as $item) {

                // seller 
                $seller = $item->seller;
                // if not fail transactions 
                if (!$seller) {
                    throw  new RuntimeException("Seller not found for Order Item ID: {$item->id}");
                    // return;
                }
                // $seller = Account::where('owner_type', AccountOwnerTypeEnum::SELLER->value)
                //     ->where('owner_id', $item->seller_id)
                //     ->firstOrFail();
                $seller = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::SELLER->value,
                    $seller->id
                );


                if ($this->ledgerExists(
                    $seller->id,
                    AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    MarketOrderItem::class,
                    $item->id
                )) {
                    continue;
                }

                $accounting->createLedger($seller, [
                    'description' => "Earnings for Order #{$marketOrder->market_order_number}: {$item->product_name} x {$item->order_qty}",
                    'credit' => $item->taxable_amount,
                    'debit'  => 0,
                    'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    'status' => LedgerStatusEnum::AVAILABLE->value,
                    'source_type' => MarketOrderItem::class,
                    'source_id' => $item->id,
                    'source_code' => $marketOrder->market_order_number,
                    'reference' => null,
                    'payment_reference' => null,
                    'common_reference' => $marketOrder->market_order_number,
                ]);
            }



            /*
            |-------------------------------------------------
            | 5.FINAL CORRECTIONS AND DELIVERY DRIVER TO PAYABLE CONVERSION
            |-------------------------------------------------
            | 
            */

            // Get shipment Packages

            $shipmentPackages = $marketOrder->shipmentPackages;

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
                            // DriverShipmentStatusEnum::PENDING->value,
                            // DriverShipmentStatusEnum::COMPLETED->value,
                        ]
                    )
                ) {
                    Log::warning("Driver shipment status not valid for Shipment ID: {$shipment->id}");
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


                // For Seller if package didnt picked up by driver and seller dropoff then we need to record negative ledger for seller as well because its like marketplace delivery where seller is responsible for delivery and if driver didnt pickup then its sellers responsibility and we will charge them the delivery fee as well
                if ($package->is_seller_dropoff || in_array($package->action_status, [null, ShipmentStatusEnum::NOT_PICKED_UP->value])) {

                    $pkgSeller = $package->seller;
                    $sellerAccount = Account::getOrCreateByOwner(
                        AccountOwnerTypeEnum::SELLER->value,
                        $pkgSeller->id
                    );

                    if ($this->ledgerExists(
                        $sellerAccount->id,
                        AccountEntryTypeEnum::ORDER_CHARGE_AMOUNT->value,
                        ShipmentPackage::class,
                        $package->id
                    )) {
                        Log::warning("Charge Ledger already exists for Seller Account ID: {$sellerAccount->id}, Package ID: {$package->id}");
                        continue;
                    }

                    $accounting->createLedger($sellerAccount, [
                        'description' => "Earning reverse of delivery charges for Order #{$marketOrder->market_order_number}: for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                        'credit' => 0,
                        'debit'  => $totalDeliveryCharge,
                        'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => get_class($package),
                        'source_id' => $package->id,
                        'source_code' => $package->shipment_package_number,
                        'reference' => $package->shipment_package_number,
                        'common_reference' => $marketOrder->market_order_number,
                        // 'payment_reference' => null,
                    ]);

                    if ($this->ledgerExists(
                        $sellerAccount->id,
                        AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                        ShipmentPackage::class,
                        $package->id
                    )) {
                        Log::warning("Order Ledger already exists for Seller Account ID: {$sellerAccount->id}, Package ID: {$package->id}");
                        continue;
                    }

                    $accounting->createLedger($sellerAccount, [
                        'description' => "Earning reverse of order charge for Order #{$marketOrder->market_order_number}: for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                        'credit' => 0,
                        'debit'  => $package->pack_price * $package->qty, // reverse the earning for this package because its not delivered and we are charging delivery fee to seller as well so we need to reverse the earning for this package as well
                        'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => get_class($package),
                        'source_id' => $package->id,
                        'source_code' => $package->shipment_package_number,
                        'reference' => $package->shipment_package_number,
                        'common_reference' => $marketOrder->market_order_number,
                        // 'payment_reference' => null,
                    ]);

                    //
                } else {

                    // Driver has picked up the package so we can consider this delivery charge as platform revenue as well because driver is paid and platform is earning commission on this delivery charge as well so we can record platform revenue ledger for this delivery charge as well


                    $driverId = $driverShipment->driver_id;

                    $driverAccount = Account::getOrCreateByOwner(
                        AccountOwnerTypeEnum::DELIVERY->value,
                        $driverId
                    );

                    if ($this->ledgerExists(
                        $driverAccount->id,
                        AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                        ShipmentPackage::class,
                        $package->id
                    )) {
                        Log::warning("Charge Ledger already exists for Driver Account ID: {$driverAccount->id}, Package ID: {$package->id}");
                        continue;
                    }

                    // Driver has picked up the package so we can consider this delivery charge as platform revenue as well because driver is paid and platform is earning commission on this delivery charge as well so we can record platform revenue ledger for this delivery charge as well
                    $accounting->createLedger($driverAccount, [
                        'description' => "Earnings of delivery Charges for Order #{$marketOrder->market_order_number}:  for Shipment #{$shipment->shipment_number} | Package #{$package->package_number}",
                        'credit' => $totalDeliveryCharge,
                        'debit'  => 0,
                        'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_BASE->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => get_class($package),
                        'source_id' => $package->id,
                        'source_code' => $package->shipment_package_number,
                        'reference' => $package->shipment_package_number,
                        'common_reference' => $marketOrder->market_order_number,
                        // 'payment_reference' => null,
                    ]);
                }


                // calculate driver payable and platform commission for this package and record ledger entries accordingly
            }
        });
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
