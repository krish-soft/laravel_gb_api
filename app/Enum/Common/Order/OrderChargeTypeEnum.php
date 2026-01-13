<?php

namespace App\Enum\Common\Order;


enum OrderChargeTypeEnum: string
{
    case DELIVERY = 'DELIVERY';
    case PLATFORM_FEE = 'PLATFORM_FEE';
    case LISTING_FEE = 'LISTING_FEE';
    case TAX = 'TAX';
    case DISCOUNT = 'DISCOUNT';
}
