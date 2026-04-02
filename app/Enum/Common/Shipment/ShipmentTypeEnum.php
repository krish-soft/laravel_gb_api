<?php

namespace App\Enum\Common\Shipment;

enum ShipmentTypeEnum: string
{
    //

    case PICKUP = 'pickup';
    case DISPATCH = 'dispatch';
    case TRANSFER = 'transfer';

    case MARKET_PICKUP = 'market_pickup';
    case MARKET_DISPATCH  = 'market_dispatch';


    ///
    case DEPOT = 'depot';
    case FULFILLMENT_LOCATION = 'fulfillment_location';
    case MARKET = 'market';
}
