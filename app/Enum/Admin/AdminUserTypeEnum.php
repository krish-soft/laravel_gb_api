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

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
