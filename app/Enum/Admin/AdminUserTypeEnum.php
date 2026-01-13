<?php

namespace App\Enum\Admin;

enum AdminUserTypeEnum: string
{
    //

    ## Internal Types
    case DEVELOPER = 'developer'; // Developer
    case ACCOUNTANT = 'accountant'; // Accountant
    case OPERATIONS = 'operations'; // Operations
    case SALES = 'sales'; // Sales
    case SUPPORT = 'support'; // Support
    case STAFF = 'staff'; // Staff


}
