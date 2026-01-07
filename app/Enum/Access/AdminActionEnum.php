<?php

namespace App\Enum\Access;

enum AdminActionEnum: string
{
    case VIEW_LIST   = 'view_list';
    case STORE       = 'store';
    case UPDATE      = 'update';
    case DELETE      = 'delete';
    case RESTORE     = 'restore';
    case FORCE_DELETE = 'force_delete';
}
