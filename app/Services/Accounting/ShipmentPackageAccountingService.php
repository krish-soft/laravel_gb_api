<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Market\MarketOrder;
use App\Models\Market\MarketOrderItem;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShipmentPackageAccountingService
{

    // Buyer Order 
    // Working Everything
    public function processOrderShipmentPackageAccounting(Order $order)
    {

        //
        $accountingService = app(AccountingService::class);

        DB::transaction(function () use ($order, $accountingService) {

            // we can have multiple packages for an order, so we need to process each package separately

            foreach ($order->shipmentPackages as $package) {

                $orderItem = $package->orderItem;

                $buyer = $order->buyer;

                $buyerAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::BUYER->value,
                    $buyer->id
                );

                $seller = $package->seller;
                $sellerAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::SELLER->value,
                    $seller->id
                );



                // 
                // 1. Scenario : if package is not picked up or pending from farmer
                if (in_array($package->seller_status, [ShipmentStatusEnum::PENDING->value, ShipmentStatusEnum::NOT_PICKED_UP->value])) {

                    $buyerDeliveryCharges = $this->getDeliveryCharge($buyerAccount, $package)->total_delivery_charge;
                    $buyerDeliveryChargeTax = $this->getDeliveryCharge($buyerAccount, $package)->total_charge_tax;
                    $packageAmount = $package->qty * $package->pack_price;



                    /**
                     *  Buyer Payment Account -> Debit
                     */

                    if (!$this->ledgerExists(
                        $buyerAccount->id,
                        AccountEntryTypeEnum::UNDELIVERED_ITEM->value,
                        ShipmentPackage::class,
                        $package->id
                    )) {
                        $accountingService->createLedger($buyerAccount, [
                            'description' => "Refund for undelivered item for Order #{$order->order_number}: Package #{$package->package_number} Qty: {$package->qty} [{$package->pack_size} {$package->pack_unit}]",
                            'credit' => $packageAmount,
                            'debit'  => 0,
                            'entry_type' => AccountEntryTypeEnum::UNDELIVERED_ITEM->value,
                            'status' => LedgerStatusEnum::AVAILABLE->value,
                            'source_type' => ShipmentPackage::class,
                            'source_id' => $package->id,
                            'source_code' => $order->order_number,
                            'common_reference' => $order->order_number,
                        ]);
                    }

                    // Delivery Charge Reversal
                    if ($buyerDeliveryCharges > 0) {

                        if (!$this->ledgerExists(
                            $buyerAccount->id,
                            AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                            ShipmentPackage::class,
                            $package->id,
                            'buyer_delivery_charge_reversal' // to make it different from buyer reversal in case of market order

                        )) {
                            $accountingService->createLedger($buyerAccount, [
                                'description' => "Refund for delivery charge for undelivered item for Order #{$order->order_number}: Package #{$package->package_number}",
                                'credit' => $buyerDeliveryCharges,
                                'debit'  => 0,
                                'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => ShipmentPackage::class,
                                'source_id' => $package->id,
                                'source_code' => $order->order_number,
                                'common_reference' => $order->order_number,
                            ]);
                        }



                        // tax if > 0
                        if ($buyerDeliveryChargeTax > 0) {

                            if (!$this->ledgerExists(
                                $buyerAccount->id,
                                AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                                ShipmentPackage::class,
                                $package->id,
                                'buyer_delivery_charge_tax_reversal' // to make it different from buyer reversal in case of market order

                            )) {
                                $accountingService->createLedger($buyerAccount, [
                                    'description' => "Refund for delivery charge tax for undelivered item for Order #{$order->order_number}: Package #{$package->package_number}",
                                    'credit' => $buyerDeliveryChargeTax,
                                    'debit'  => 0,
                                    'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => ShipmentPackage::class,
                                    'source_id' => $package->id,
                                    'source_code' => $order->order_number,
                                    'common_reference' => $order->order_number,
                                ]);
                            }
                        }
                    }

                    // Order Item Table
                    // So We have to create orderItem same to revert and reflect on invoice
                    if (!$this->reverseOrderItemExists($order->id, $package->shipment_package_number)) {

                        $reverseItem = $orderItem->replicate();
                        $reverseItem->fill([
                            'seller_id' => $package->seller_id,
                            'product_name' => "Reversal for undelivered package #{$package->package_number}." . $orderItem->product_name,
                            'order_qty' => -1 * $package->qty,
                            'ship_qty' => -1 * $package->qty,
                            'pack_size' => $package->pack_size,
                            'pack_unit' => $package->pack_unit,
                            'pack_type_unit' => $package->pack_type_unit,
                            'pack_price' => $package->pack_price,
                            'per_unit_price' => $package->per_unit_price,
                            'taxable_amount' => -1 * $packageAmount,
                            'tax_amount' => 0,
                            'total_amount' => -1 * $packageAmount,
                            'is_reverse' => true,
                            'reverse_reference' => $package->shipment_package_number,
                        ]);

                        $order->orderItems()->save($reverseItem);

                        // also need to revert delviery charge on charges invoice so 

                        if ($buyerDeliveryCharges > 0) {

                            $order->orderCharges()->create([
                                'charge_name' => 'Delivery Charge Reversal',
                                'charge_code' => 'DELIVERY_CHARGE_REVERSAL',
                                'rule_type' => null,
                                'rule_no' => null,
                                'rule_desc' => "Reversal of delivery charge for undelivered package #{$package->shipment_package_number}",
                                'taxable_amount' => -1 * ($buyerDeliveryCharges - $buyerDeliveryChargeTax),
                                'tax_amount' => -1 * $buyerDeliveryChargeTax,
                                'total_amount' => -1 * ($buyerDeliveryCharges),
                            ]);
                        }

                        // for seller to prevent duplications 
                        $productListingPackage = $package->productListingPackage()->lockForUpdate()->first();
                        $maxReversible = $productListingPackage->sold_qty - $productListingPackage->reverse_qty;
                        $allowedQty = min($package->qty, max(0, $maxReversible));
                        if ($allowedQty > 0) {
                            $productListingPackage->increment('reverse_qty', $allowedQty);
                            $productListingPackage->increment('reverse_amount', $packageAmount);
                        }
                    }

                    /**
                     *  Seller Account -> Credit (Hold)
                     */

                    $sellerDeliveryCharges = $this->getDeliveryCharge($sellerAccount, $package)->total_delivery_charge;
                    $sellerDeliveryChargeTax = $this->getDeliveryCharge($sellerAccount, $package)->total_charge_tax;

                    if (!$this->ledgerExists(
                        $sellerAccount->id,
                        AccountEntryTypeEnum::UNDELIVERED_ITEM->value,
                        ShipmentPackage::class,
                        $package->id
                    )) {
                        $accountingService->createLedger($sellerAccount, [
                            'description' => "Reversal for undelivered item for Order #{$order->order_number}: Package #{$package->package_number} Qty: {$package->qty} [{$package->pack_size} {$package->pack_unit}]",
                            'credit' => 0,
                            'debit'  => $packageAmount,
                            'entry_type' => AccountEntryTypeEnum::UNDELIVERED_ITEM->value,
                            'status' => LedgerStatusEnum::AVAILABLE->value,
                            'source_type' => ShipmentPackage::class,
                            'source_id' => $package->id,
                            'source_code' => $order->order_number,
                            'common_reference' => $order->order_number,
                        ]);
                    }

                    // Delivery Charge Reversal
                    if ($sellerDeliveryCharges > 0) {

                        if (!$this->ledgerExists(
                            $sellerAccount->id,
                            AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                            ShipmentPackage::class,
                            $package->id,
                            'seller_delivery_charge_reversal' // to make it different from buyer reversal in case of market order

                        )) {
                            $accountingService->createLedger($sellerAccount, [
                                'description' => "Reversal for delivery charge for undelivered item for Order #{$order->order_number}: Package #{$package->package_number}",
                                'credit' => 0,
                                'debit'  => $sellerDeliveryCharges,
                                'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => ShipmentPackage::class,
                                'source_id' => $package->id,
                                'source_code' => $order->order_number,
                                'common_reference' => $order->order_number,
                            ]);
                        }


                        // tax if > 0
                        if ($sellerDeliveryChargeTax > 0) {

                            if (!$this->ledgerExists(
                                $sellerAccount->id,
                                AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                                ShipmentPackage::class,
                                $package->id,
                                'seller_delivery_charge_tax_reversal' // to make it different from buyer reversal in case of market order

                            )) {
                                $accountingService->createLedger($sellerAccount, [
                                    'description' => "Reversal for delivery charge tax  for undelivered item for Order #{$order->order_number}: Package #{$package->package_number}",
                                    'credit' => 0,
                                    'debit'  => $sellerDeliveryChargeTax,
                                    'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                                    'status' => LedgerStatusEnum::AVAILABLE->value,
                                    'source_type' => ShipmentPackage::class,
                                    'source_id' => $package->id,
                                    'source_code' => $order->order_number,
                                    'common_reference' => $order->order_number,
                                ]);
                            }
                        }
                    }




                    // main loop
                }

                // 2. Scenario : if package is picked up but not delivered yet
                // Manually due to whos fault
                // if (!in_array($package->buyer_status, [
                //     ShipmentStatusEnum::DELIVERED->value,
                // ])) {

                //     /**
                //      *  Buyer Payment Account -> Debit (Hold)
                //      */

                //     //
                // }
            }
        });




        //
    }


    // Market Order
    // Have problem

    public function processMarketOrderShipmentPackageAccounting(MarketOrder $order)
    {

        //
        $accountingService = app(AccountingService::class);

        DB::transaction(function () use ($order, $accountingService) {

            // we can have multiple packages for an order, so we need to process each package separately
            foreach ($order->shipmentPackages as $package) {

                // $orderItem = $package->orderItem;
                $seller = $package->seller;
                if (!$seller) {
                    throw new RuntimeException("Seller not found for Shipment Package ID: {$package->id}");
                }

                $sellerAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::SELLER->value,
                    $seller->id
                );

                // 
                // 1. Scenario : if package is not picked up or pending from farmer
                if (in_array($package->seller_status, [
                    ShipmentStatusEnum::PENDING->value,
                    ShipmentStatusEnum::NOT_PICKED_UP->value,
                ])) {

                    // Package amount can not be rever becasue what data ented is what we recived from market

                    $packageAmount = $package->qty * $package->pack_price;

                    // // For market order we do not have to reverse order item 


                    /**
                     *  Seller Account -> Credit (Hold)
                     */
                    $sellerDeliveryCharges = $this->getDeliveryCharge($sellerAccount, $package)->total_delivery_charge;
                    $sellerDeliveryChargeTax = $this->getDeliveryCharge($sellerAccount, $package)->total_charge_tax;

                    // Delivery Charge Reversal
                    if ($sellerDeliveryCharges > 0) {

                        if (!$this->ledgerExists(
                            $sellerAccount->id,
                            AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                            ShipmentPackage::class,
                            $package->id
                        )) {
                            $accountingService->createLedger($sellerAccount, [
                                'description' => "Reversal for delivery charge for undelivered item for market Order #{$order->market_order_number}: Package #{$package->package_number}",
                                'credit' => 0,
                                'debit'  => $sellerDeliveryCharges,
                                'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => ShipmentPackage::class,
                                'source_id' => $package->id,
                                'source_code' => $order->market_order_number,
                                'common_reference' => $order->market_order_number,
                            ]);

                            // seller listing
                            $productListingPackage = $package->productListingPackage()->lockForUpdate()->first();
                            $maxReversible = $productListingPackage->sold_qty - $productListingPackage->reverse_qty;
                            $allowedQty = min($package->qty, max(0, $maxReversible));
                            if ($allowedQty > 0) {
                                $productListingPackage->increment('reverse_qty', $allowedQty);
                                $productListingPackage->increment('reverse_amount', $packageAmount);
                            }
                        }

                        // tax if > 0
                        // if ($sellerDeliveryChargeTax > 0) {

                        //     if (!$this->ledgerExists(
                        //         $sellerAccount->id,
                        //         AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                        //         ShipmentPackage::class,
                        //         $package->id,
                        //         'market_order_seller_delivery_charge_tax_reversal' // to make it different from buyer reversal in case of market order

                        //     )) {
                        //         $accountingService->createLedger($sellerAccount, [
                        //             'description' => "Reversal for delivery charge for undelivered item for market Order #{$order->order_number}: Package #{$package->package_number}",
                        //             'credit' => 0,
                        //             'debit'  => $sellerDeliveryChargeTax,
                        //             'entry_type' => AccountEntryTypeEnum::DELIVERY_CHARGE_REVERSAL->value,
                        //             'status' => LedgerStatusEnum::AVAILABLE->value,
                        //             'source_type' => ShipmentPackage::class,
                        //             'source_id' => $package->id,
                        //             'source_code' => $order->order_number,
                        //             'common_reference' => $order->order_number,
                        //         ]);
                        //     }
                        // }
                    }




                    // main loop
                }

                // 2. Scenario : if package is picked up but not delivered yet
                // Manually due to whos fault
                // if (!in_array($package->buyer_status, [
                //     ShipmentStatusEnum::DELIVERED->value,
                // ])) {

                //     /**
                //      *  Buyer Payment Account -> Debit (Hold)
                //      */

                //     //
                // }
            }
        });




        //
    }




    private function reverseOrderItemExists($orderId, $shipmentPackageNumber)
    {
        return OrderItem::where('order_id', $orderId)
            ->where('is_reverse', true)
            ->where('reverse_reference', $shipmentPackageNumber)
            ->exists();
    }

    private function reverseMarketOrderItemExists($orderId, $shipmentPackageNumber)
    {
        return MarketOrderItem::where('order_id', $orderId)
            ->where('is_reverse', true)
            ->where('reverse_reference', $shipmentPackageNumber)
            ->exists();
    }



    private function getDeliveryCharge(Account $account, ShipmentPackage $shipmentPackage)
    {

        $chargeService = app(ChargeCalculationService::class);

        // Create Arr 
        $pkg =  [
            [
                'order_qty'  => $shipmentPackage->qty,
                'pack_size'  => $shipmentPackage->pack_size,
                'pack_price' => $shipmentPackage->pack_price,
                'pack_unit'  => $shipmentPackage->pack_unit,
                'pack_type_unit' => $shipmentPackage->pack_type_unit,
            ]
        ];
        $deliveryChargesData  = $chargeService->calculateDeliveryCharges(
            $account->user->charge_level_code,
            $pkg,
            $shipmentPackage->is_buyer_pickup ?? false,
            $shipmentPackage->is_seller_dropoff ?? false
        );

        $totalDeliveryCharge = $deliveryChargesData['total_charge_amount'];
        $totalChargeTax = $deliveryChargesData['charge_tax'];
        return (object) ['total_delivery_charge' => $totalDeliveryCharge, 'total_charge_tax' => $totalChargeTax];
    }

    /**
     * Proper idempotency guard
     */
    private function ledgerExists(
        int $accountId,
        string $entryType,
        string $sourceType,
        int $sourceId,
        ?string $otherReference = null
    ): bool {
        return AccountLedger::where('account_id', $accountId)
            ->where('entry_type', $entryType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('other_reference', $otherReference) // for driver charge reversal or any other use
            ->exists();
    }

    // 
}
