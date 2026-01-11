<?php

namespace App\Enum\Action;

enum ActionCodeEnum: int
{
    case FORCE_APP_UPDATE      = 1001;
    case FORCE_MAINTENANCE     = 1002;

    case FORCE_LOGIN           = 1100;
    case FORCE_ACCOUNT_BLOCKED = 1101;

    case FORCE_KYC             = 1200;
    case FORCE_RE_KYC          = 1201;

    case FORCE_ADD_ADDRESS     = 1500;
    case FORCE_SELECT_PAYMENT  = 1501;

    case FORCE_CHANGE_LOCATION = 1800;
}
