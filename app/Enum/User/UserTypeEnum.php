<?php

namespace App\Enum\User;

enum UserTypeEnum: string
{
    //

    case FARMER = 'farmer'; // Farmer
    case TRADER = 'trader'; // Trader
    case DELIVERY = 'delivery'; // Delivery


        ## Internal Types  
    case DEVELOPER = 'developer'; // Developer 
    case SUPERVISOR = 'supervisor'; // Supervisor
    case MANAGER = 'manager'; // Manager 
    case STAFF = 'staff'; // Staff 
    case OPERATIONS = 'operations'; // Operations
    case SALES = 'sales'; // Sales
    case SUPPORT = 'support'; // Support 


}
