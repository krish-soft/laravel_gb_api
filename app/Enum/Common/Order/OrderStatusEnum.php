<?php

namespace App\Enum\Common\Order;

enum OrderStatusEnum: string
{
    //

    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case INVOICED = 'invoiced';

    case CANCELLED = 'cancelled';
    case FAILED_PAYMENT = 'failed_payment';
    case REFUNDED = 'refunded';
    case RECONCILED = 'reconciled';
    case ACCOUNTED = 'accounted';


        // Keeping for future use
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case SETTLED = 'settled';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }

    //
}
