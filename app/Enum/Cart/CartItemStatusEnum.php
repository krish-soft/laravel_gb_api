<?php

namespace App\Enum\Cart;

enum CartItemStatusEnum : string
{
    //
    case ACTIVE = 'active';
    case OUT_OF_STOCK = 'out_of_stock';
    case PRICE_CHANGED = 'price_changed';
    case CONFIRMED = 'confirmed';
}
