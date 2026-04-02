<?php

namespace App\Enum\Common\Order;

enum OrderFlagsEum: string
{
    //

    case SHIPMENT_PACKAGE_MISMATCH = 'shipment_package_mismatch';

    case ORDER_ITEM_SELLER_PACKAGE_UNAVAILABLE = 'order_item_seller_package_unavailable';


    //
}
