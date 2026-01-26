<?php

namespace App\Enum\Common\Legal;

enum KycReviewEnum: string
{
    //

    case APPROVE  = 'approve';


    case NEED_SELFIE  = 'need_selfie';
    case NEED_PAN_CARD  = 'need_pan_card';
    case NEED_AADHAAR  = 'need_aadhaar';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }

    //
}
