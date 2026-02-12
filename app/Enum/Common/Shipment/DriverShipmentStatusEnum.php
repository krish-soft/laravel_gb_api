<?php

namespace App\Enum\Common\Shipment;

enum DriverShipmentStatusEnum: string
{
    //
    case PENDING = 'pending';
    case REQUESTED = 'requested';
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case IN_TRANSIT = 'in_transit';
    case COMPLETED = 'completed';
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
