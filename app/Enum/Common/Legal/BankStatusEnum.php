<?php

namespace App\Enum\Common\Legal;

enum BankStatusEnum: string
{
    //
    case PENDING  = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case LOCKED   = 'locked';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
