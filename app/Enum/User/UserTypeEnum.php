<?php

namespace App\Enum\User;

enum UserTypeEnum: string
{
    //

    case FARMER = 'farmer'; // Farmer
    case TRADER = 'trader'; // Trader
    case DELIVERY = 'delivery'; // Delivery

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
