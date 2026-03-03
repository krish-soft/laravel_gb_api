<?php

namespace App\Enum\Common\Invoice;

enum InvoiceStatusEnum: string
{
    //

    case GENERATED = 'generated';
    case ACCOUNTED = 'accounted';

    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    // 
    case SALES = 'sales';
    case PURCHASE = 'purchase';
    case REFUND = 'refund';
    case CREDIT_NOTE = 'credit_note';
    case DEBIT_NOTE = 'debit_note';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }

    //
}
