<?php

namespace App\Enum\Common\Legal;

enum LegalDocumentTypeEnum: string
{
    //

    // KYC Documents
    case AADHAAR = 'aadhaar';
    case PAN_CARD = 'pan_card';
    case DRIVING_LICENSE = 'driving_license';
    case SELFIE = 'selfie';

    case RC_BOOK = 'rc_book';
    case INSURANCE_POLICY = 'insurance_policy';
    case VEHICLE_PHOTO = 'vehicle_photo';

    case PHOTO = 'photo';
    case OTHER = 'other';

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
