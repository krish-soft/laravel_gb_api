<?php

namespace App\Enum\Common\Legal;

enum BankStatusEnum: string
{
    //
    case PENDING  = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case LOCKED   = 'locked';
}
