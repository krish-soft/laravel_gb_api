<?php

namespace App\Enum\Common\Wallet;

enum WalletStatusEnum: string
{
    case PENDING   = 'pending';
    case HOLD      = 'hold';
    case COMPLETED = 'completed';
    case RELEASED  = 'released';
    case CANCELLED = 'cancelled';
}
