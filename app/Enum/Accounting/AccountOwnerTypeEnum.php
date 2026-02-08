<?php

namespace App\Enum\Accounting;

enum AccountOwnerTypeEnum: string
{
    //

    case PLATFORM = 'platform';
    case SELLER = 'seller';
    case DELIVERY = 'delivery';
    case GOVERNMENT = 'government';
    case BANK = 'bank';
    case CASH = 'cash';

     public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
