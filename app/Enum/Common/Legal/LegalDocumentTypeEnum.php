<?php

namespace App\Enum\Common\Legal;

enum LegalDocumentTypeEnum: string
{
    //

    // KYC Documents
    case AADHAAR = 'aadhaar';
    case PAN_CARD = 'pan_card';
    case DRIVING_LICENSE = 'driving_license';

    case RC_BOOK = 'rc_book';
    case INSURANCE_POLICY = 'insurance_policy';

    case PASSPORT = 'passport';
    case VOTER_ID = 'voter_id';
    case UTILITY_BILL = 'utility_bill';
    case BANK_STATEMENT = 'bank_statement';
    case ADDRESS_PROOF = 'address_proof';
    case ID_PROOF = 'id_proof';
    case PHOTO = 'photo';
    case OTHER = 'other';
}
