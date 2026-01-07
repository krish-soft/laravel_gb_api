<?php

namespace App\Enum\User;

enum UserRoleEnum: string
{
    //

    case BUYER = 'buyer'; // Trader
    case SELLER = 'seller'; // Farmer
    case DELIVERY = 'delivery'; // Delivery 


}
