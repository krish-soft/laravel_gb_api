<?php

namespace App\Enum\Common\Order;

enum OrderStatusEnum: string
{
    //

    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';

    case CANCELLED = 'cancelled';
    case FAILED_PAYMENT = 'failed_payment';
    case REFUNDED = 'refunded';

    // Keeping for future use
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';

    //
}
