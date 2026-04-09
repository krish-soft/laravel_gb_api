<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Order\OrderStatusEnum;
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
            if ($marketOrder->total_amount <= 0) {

                $marketOrder->addFlag(OrderFlagsEum::ACCOUNTING_ERROR, "Market order has invalid payment status or total amount for accounting.");

                throw new RuntimeException("Market Order ID: {$marketOrder->id} has invalid payment status or total amount for accounting.Total Amount: {$marketOrder->total_amount}");
                // Log::warning("Market Order ID: {$marketOrder->id} has invalid payment status or total amount for accounting. Payment Status: {$marketOrder->payment_status}, Total Amount: {$marketOrder->total_amount}");
            }

            /*
            |-------------------------------------------------
            | 1. PLATFORM CLEARING (FULL PAID AMOUNT)
            |-------------------------------------------------
            */
            // $clearing = Account::where('accnt_code', PlatformAccountCodeEnum::PLATFORM_CLEARING->value)->firstOrFail();
            $platformMarketAccount = Account::getOrCreateByOwner(
                AccountOwnerTypeEnum::PLATFORM->value,
                null,
                PlatformAccountCodeEnum::PLATFORM_MARKET->value,
            );

            if (!$this->ledgerExists(
                $platformMarketAccount->id,
                AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                MarketOrder::class,
                $marketOrder->id
            )) {
                $accounting->createLedger($platformMarketAccount, [
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

            // Per item we can not do it 
            $seller = $marketOrder->marketOrderItems->first()?->seller;
            // if not fail transactions 
            if (!$seller) {
                throw  new RuntimeException("Seller not found for Order Item ID: {$marketOrder->marketOrderItems->first()?->id}");
                // return;
            }

            $sellerAccount = Account::getOrCreateByOwner(
                AccountOwnerTypeEnum::SELLER->value,
                $seller->id
            );


            if (!$this->ledgerExists(
                $sellerAccount->id,
                AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                MarketOrder::class,
                $marketOrder->id
            )) {

                $accounting->createLedger($sellerAccount, [
                    'description' => "Earnings for Market Order #{$marketOrder->market_order_number}",
                    'credit' => $marketOrder->total_amount,
                    'debit'  => 0,
                    'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    'status' => LedgerStatusEnum::AVAILABLE->value,
                    'source_type' => MarketOrder::class,
                    'source_id' => $marketOrder->id,
                    'source_code' => $marketOrder->market_order_number,
                    'reference' => null,
                    'payment_reference' => null,
                    'common_reference' => $marketOrder->market_order_number,
                ]);
            }

            // 
            $marketOrder->order_status =  OrderStatusEnum::ACCOUNTED->value;
            $marketOrder->is_locked = true; // lock order after accounting
            $marketOrder->removeFlag(OrderFlagsEum::ACCOUNTING_ERROR); // remove accounting error flag if exists
            $marketOrder->save();



            //
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
