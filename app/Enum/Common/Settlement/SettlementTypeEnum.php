<?php

namespace App\Enum\Common\Settlement;

enum SettlementTypeEnum: string
{
    //

    case PENDING = 'pending';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';


    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
