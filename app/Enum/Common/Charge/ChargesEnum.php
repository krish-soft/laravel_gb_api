<?php

namespace App\Enum\Common\Charge;


enum ChargesEnum: string
{
    case PLATFORM_FEE = 'PLATFORM_FEE';
    case DELIVERY_FEE = 'DELIVERY_FEE';


    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
