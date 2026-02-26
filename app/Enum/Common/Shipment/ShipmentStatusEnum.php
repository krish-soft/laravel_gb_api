<?php

namespace App\Enum\Common\Shipment;

enum ShipmentStatusEnum: string
{
    //

    case PENDING = 'pending';
    case READY_TO_PICKUP = 'ready_for_pickup';
    case PICKED_UP = 'picked_up';
    case NOT_PICKED_UP = 'not_picked_up';

    case IN_TRANSIT = 'in_transit';
    case ARRIVED_AT_DEPOT = 'arrived_at_depot';
    case DISPATCHED_FROM_DEPOT = 'dispatched_from_depot';
    case INTERNAL_TRANSFER = 'internal_transfer';

    case DISPATCHED = 'dispatched';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case OUT_FOR_DELIVERY = 'out_for_delivery';

    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case DAMAGED = 'damaged';
    case LOST = 'lost';

    case ASSIGNED = 'assigned';

        //  grouping status
    case PICKUP = 'pickup';
    case TRANSFER = 'transfer';
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
