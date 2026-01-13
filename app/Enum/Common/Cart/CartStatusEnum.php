<?php

namespace App\Enum\Common\Cart;

enum CartStatusEnum: string
{
    //
    case ACTIVE = 'active';
    case LOCKED = 'locked';
    case CONVERTED = 'converted';
    case ABANDONED = 'abandoned';
}
