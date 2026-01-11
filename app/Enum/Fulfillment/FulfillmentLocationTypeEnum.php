<?php

namespace App\Enum\Fulfillment;

enum FulfillmentLocationTypeEnum: string
{
    //

    // Producer / Seller side
    case FARM = 'farm';
    case SHOP = 'shop';
    case COLLECTION_CENTER = 'collection_center';


        // Storage / Processing
    case WAREHOUSE = 'warehouse';
    case COLD_STORAGE = 'cold_storage';
    case PROCESSING_UNIT = 'processing_unit';

        // Logistics
    case HUB = 'hub';
    case CROSS_DOCK = 'cross_dock';

        // Returns / Exceptions
    case RETURN_CENTER = 'return_center';
    case DISPOSAL_CENTER = 'disposal_center';


    public static function casesAsValues(): array
    {
        return array_map(
            fn(self $case) => $case->value,
            self::cases()
        );
    }
}
