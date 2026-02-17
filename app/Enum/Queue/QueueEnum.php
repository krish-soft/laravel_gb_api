<?php

namespace App\Enum\Queue;

enum QueueEnum: string
{
    //
    case LISTING_CUTOFF   = 'listing_cutoff';   // product listing lifecycle / expiry // USED
    case ACCOUNTING_CUTOFF   = 'accounting_cutoff';   // accounting lifecycle / expiry 

    case ORDER_PROCESS = 'order_process'; // order creation, updates, status sync
    case PAYMENT_PROCESS  = 'payment_process';  // capture, refund, settlement

    case FULFILLMENT      = 'fulfillment';      // packing, shipment, logistics

}
