<?php

namespace Database\Seeders\Fulfillment;

use App\Enum\Common\Fulfillment\FulfillmentLocationTypeEnum;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use Illuminate\Database\Seeder;

class FulfillmentLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        // add for Seller user where test
        FulfillmentLocation::create([
            'user_id' => 4, // Seller User ID
            'name' => 'Farm Warehouse',
            'fl_code' => 'FL-0001',
            'type' => FulfillmentLocationTypeEnum::FARM->value,
            'is_active' => true,
        ]);
    }
}
