<?php

namespace App\Enum\Accounting;

enum AccountOwnerTypeEnum: string
{
    //

    case PLATFORM = 'platform';
    case SELLER = 'seller';
    case DELIVERY = 'delivery';
    case GOVERNMENT = 'government';
}
