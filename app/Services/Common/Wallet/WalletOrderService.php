<?php

namespace App\Services\Common\Wallet;

use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Payment;
use App\Models\Common\Wallet\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletOrderService
{
    /**
     * Handle wallet ledger entries after order payment success
     */
    public function createNewOrderWalletTransaction(Order $order, Payment $payment): void
    {
        // 🔒 HARD GUARD — only paid payments allowed
        if (!$payment->isPaid()) {
            return;
        }

        DB::transaction(function () use ($order, $payment) {

            /* =====================================================
             | BUYER → DEBIT (ONCE)
             =====================================================*/

            $buyerTxn = WalletTransaction::where('reference', $payment->payment_code)
                ->lockForUpdate()
                ->first();

            if (!$buyerTxn) {

                $buyerTxn = app(WalletService::class)->createTransaction(
                    $order->buyer->wallet,
                    $payment->amount,
                    WalletTypeEnum::DEBIT,
                    WalletStatusEnum::COMPLETED,
                    [
                        'source_type' => Order::class,
                        'source_id' => $order->id,
                        'source_code' => $order->order_number,
                        'reference' => $payment->payment_code,
                        'description' => 'Order payment for #' . $order->order_number,
                    ]
                );

                app(WalletService::class)->finalizeTransaction($buyerTxn);
            }

            /* =====================================================
             | SELLER → CREDIT (HOLD PER ITEM)
             =====================================================*/

            foreach ($order->orderItems as $orderItem) {

                $seller = $orderItem->productListingItem->seller;
                $sellerWallet = $seller->wallet;

                $sellerTxn = WalletTransaction::where('source_type', OrderItem::class)
                    ->where('source_id', $orderItem->id)
                    ->where('payment_reference', $payment->payment_code)
                    ->lockForUpdate()
                    ->first();

                if ($sellerTxn) {
                    continue;
                }

                $sellerTxn = app(WalletService::class)->createTransaction(
                    $sellerWallet,
                    $orderItem->total_amount,
                    WalletTypeEnum::CREDIT,
                    WalletStatusEnum::HOLD, // Hold until order completion
                    [
                        'source_type' => OrderItem::class,
                        'source_id' => $orderItem->id,
                        'source_code' => $orderItem->order_number,
                        'payment_reference' => $payment->payment_code,
                        'description' => 'Earnings on hold for Order Item #' . $orderItem->order_number,

                        //  LINK BUYER ↔ SELLER
                        'related_wallet_txn_id' => $buyerTxn->id,
                        'related_wallet_txn_code' => $buyerTxn->reference,
                    ]
                );

                app(WalletService::class)->finalizeTransaction($sellerTxn);
            }

            logActivity(
                'order_wallet_entries_created',
                request()?->user() ?? null,
                Order::class,
                $order->id,
                $order->order_number,
                [
                    'payment_code' => $payment->payment_code,
                    'amount' => $payment->amount,
                ]
            );
        });
    }
}
