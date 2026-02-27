<?php

namespace App\Enum\Accounting;

enum AccountEntryTypeEnum: string
{
    // 🔹 Order main amount (taxable base of full order)
    case ORDER_BASE_AMOUNT          = 'order_base_amount';
    case ORDER_TAX_AMOUNT           = 'order_tax_amount';
    case ORDER_CHARGE_AMOUNT        = 'order_charge_amount';

        // 🔹 Platform charges (order-level)
    case PLATFORM_CHARGE_BASE    = 'platform_charge_base';
    case PLATFORM_CHARGE_TAX     = 'platform_charge_tax';

        // 🔹 Delivery charges (order-level)
    case DELIVERY_CHARGE_BASE    = 'delivery_charge_base';
    case DELIVERY_CHARGE_RETURN  = 'delivery_charge_return';
    case DELIVERY_CHARGE_TAX     = 'delivery_charge_tax';

    case UNDELIVERED_ITEM = 'undelivered_item';

        // 🔹 Adjustments
    case ORDER_PENALTY              = 'order_penalty';
    case ORDER_PROMOTION            = 'order_promotion';

        // 🔹 Advance / recovery (bank verify, etc)
    case ADVANCE                    = 'advance';
    case RECOVERY                   = 'recovery';

        // 🔹 Settlement
    case PAYOUT                     = 'payout';
    case SETTLEMENT                 = 'settlement';

        // 🔹 Reversal
    case ORDER_CANCEL               = 'order_cancel';

    case OPENING_BALANCE            = 'opening_balance';

        //
    case OTHER                      = 'other';




    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
