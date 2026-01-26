<?php

namespace App\Enum\Admin;

enum AdminActionEnum: string
{
    case VIEW_LIST   = 'view_list';
    case STORE       = 'store';
    case UPDATE      = 'update';
    case DELETE      = 'delete';
    case RESTORE     = 'restore';
    case FORCE_DELETE = 'force_delete';
    case ALL = '*';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
