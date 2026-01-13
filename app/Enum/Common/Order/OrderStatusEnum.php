<?php

namespace App\Enum\Common\Order;

enum OrderStatusEnum: string
{
    //

    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case SUSPENDED = 'suspended';

    //
}
