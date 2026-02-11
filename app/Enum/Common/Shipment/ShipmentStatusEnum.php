<?php

namespace App\Enum\Common\Shipment;

enum ShipmentStatusEnum: string
{
    //

    case PENDING = 'pending';
    case READY_TO_PICKUP = 'ready_for_pickup';
    case PICKED_UP = 'picked_up';

    case IN_TRANSIT = 'in_transit';
    case ARRIVED_AT_DEPOT = 'arrived_at_depot';
    case DISPATCHED_FROM_DEPOT = 'dispatched_from_depot';

    case DISPATCHED = 'dispatched';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';

    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case DAMAGED = 'damaged';
    case LOST = 'lost';

        //  grouping status
    case PICKUP = 'pickup';
    case DISPATCH = 'dispatch';
    case GROUPED = 'grouped';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }

    //
}
