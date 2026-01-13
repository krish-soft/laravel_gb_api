<?php

namespace App\Enum\Common\Wallet;

enum WalletTypeEnum: string
{
    case CREDIT = 'credit';
    case DEBIT  = 'debit';
}
