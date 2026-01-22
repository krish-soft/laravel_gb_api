<?php

namespace App\Enum;

enum AddressTypeEnum: string
{
    //

    // Legal / Finance
    case KYC  = 'kyc';   // KYC address

    case BILL = 'bill';   // Billing / GST address

        // Delivery / Ops
    case SHIP = 'ship';   // Shipping / delivery
    case PICK = 'pick';   // Pickup (farm, source)


        // Depot / Logistics
    case DEPOT  = 'depot';    // Depot / hub / aggregation
    // case HUB  = 'hub';    // Depot / hub / aggregation
    // case XFER = 'xfer';   // Transfer point
    // case SORT = 'sort';   // Sorting / grading center

    // Generic / Fallback
    // case MAIN = 'main';   // Primary address
    // case AUX  = 'aux';    // Secondary / temporary
    //

    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
