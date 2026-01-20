<?php

namespace App\Enum\Accounting;

enum AccountEntryTypeEnum: string
{
    // 🔹 Order main amount (taxable base of full order)
    case ORDER_BASE_AMOUNT          = 'order_base_amount';
    case ORDER_TAX_AMOUNT           = 'order_tax_amount';

        // 🔹 Platform charges (order-level)
    case ORDER_PLATFORM_FEE_BASE    = 'order_platform_fee_base';
    case ORDER_PLATFORM_FEE_TAX     = 'order_platform_fee_tax';

        // 🔹 Delivery charges (order-level)
    case ORDER_DELIVERY_FEE_BASE    = 'order_delivery_fee_base';
    case ORDER_DELIVERY_FEE_TAX     = 'order_delivery_fee_tax';

        // 🔹 Adjustments
    case ORDER_PENALTY              = 'order_penalty';
    case ORDER_PROMOTION            = 'order_promotion';

        // 🔹 Advance / recovery (bank verify, etc)
    case ADVANCE                    = 'advance';
    case RECOVERY                   = 'recovery';

        // 🔹 Settlement
    case PAYOUT                     = 'payout';

        // 🔹 Reversal
    case ORDER_CANCEL               = 'order_cancel';
}
