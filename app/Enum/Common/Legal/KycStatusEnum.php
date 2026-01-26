<?php

namespace App\Enum\Common\Legal;

enum KycStatusEnum: string
{
    //
    case PENDING  = 'pending'; // awaiting review
    case APPROVED = 'approved'; // verified
    case REJECTED = 'rejected'; // needs re-upload

    case UNDER_REVIEW  = 'under_review'; // being reviewed
    case REQUEST_FOR_REVIEW  = 'request_for_review'; // flagged for review next time

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
