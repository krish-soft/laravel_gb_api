<?php

namespace App\Enum\Common\Invoice;

enum InvoiceTypeEnum: string
{
    //

    case SALES = 'sales';
    case SALES_RETURN = 'sales_return';
    case PURCHASE = 'purchase';
    case PURCHASE_RETURN = 'purchase_return';
    // case REFUND = 'refund';


    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }

    //
}
