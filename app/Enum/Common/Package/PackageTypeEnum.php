<?php

namespace App\Enum\Common\Package;

enum PackageTypeEnum: string
{
    //

    case DIRECT_ORDER = 'direct_order';
    case MARKET_ORDER = 'market_order';

    case DEMAND_ORDER_FARMER = 'demand_order_farmer';
    case DEMAND_ORDER_MARKET = 'demand_order_market';
    
    case LISTING = 'listing';
}
