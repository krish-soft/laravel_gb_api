<?php

namespace App\Enum\Setting;

enum LocaleEnum: string
{
    //

    case ENGLISH = 'en';
    case GUJARATI = 'gu';
    case HINDI = 'hi';


    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
