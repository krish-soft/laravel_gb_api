<?php

namespace App\Enum\Legal;

enum KycStatusEnum:string
{
    //
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
