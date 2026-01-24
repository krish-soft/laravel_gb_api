<?php

namespace App\Enum\Admin;

enum AdminRoleEnum: string
{
    //

    case SUPERADMIN = 'super_admin'; // Super Admin with all modules and permissions
    case ADMIN = 'admin'; // Admin with limited moduels and permissions
    case SUPERVISOR = 'supervisor'; // Supervisor


    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
    //
}
