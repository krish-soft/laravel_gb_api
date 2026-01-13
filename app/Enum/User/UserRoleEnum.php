<?php

namespace App\Enum\User;

enum UserRoleEnum: string
{
    //

    case BUYER = 'buyer'; // Trader
    case SELLER = 'seller'; // Farmer
    case DELIVERY = 'delivery'; // Delivery


    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }

}
