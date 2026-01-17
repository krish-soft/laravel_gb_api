
<?php

namespace App\Enum\Common;

enum EntityTypeEnum: string
{

    case PLATFORM = 'platform';
    case SELLER = 'seller';
    case BUYER = 'buyer';
    case DELIVERY = 'delivery';

    case USER = 'user'; // common to sue like wallet recahrge, etc.
    case BANK = 'bank'; // payouts to bank
    case GATEWAY = 'gateway'; // verification, fees, etc.
}
