<?php

namespace App\Enum\Accounting;

enum LedgerStatusEnum: string
{
    //

    case PENDING = 'pending'; // initial state   
    case AVAILABLE = 'available';
    case SETTLED = 'settled';


     public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
