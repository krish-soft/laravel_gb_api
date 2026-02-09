<?php

namespace App\Enum\Common\Shipment;

enum ShipmentStatusEnum: string
{
    //

    case PENDING = 'pending';
    case READY_TO_PICKUP = 'ready_for_pickup';
    case PICKED_UP = 'picked_up';

    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';

    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }

    //
}
