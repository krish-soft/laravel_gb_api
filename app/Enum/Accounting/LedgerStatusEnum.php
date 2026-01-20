<?php

namespace App\Enum\Accounting;

enum LedgerStatusEnum: string
{
    //

    case PENDING = 'pending'; // initial state   
    case AVAILABLE = 'available';
    case SETTLED = 'settled';
}
