<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([

            // Financial Year Seeder
            Master\MstFinancialSeeder::class,

            // Accounting Seeders
            \Database\Seeders\Accounting\AccountSeeder::class, 

            // App Settings
            \Database\Seeders\Setting\AppSettingSeeder::class,
            \Database\Seeders\Setting\BusinessSettingSeeder::class,

            // User Testing
            \Database\Seeders\User\UserSeeder::class,

            // Other seeders can be added here

            // Master Data Seeders
            \Database\Seeders\Master\MstUnitSeeder::class,
            \Database\Seeders\Master\MstPackTypeSeeder::class,

            // Depot
            \Database\Seeders\Master\MstStateSeeder::class,
            \Database\Seeders\Master\Depot\MstZoneSeeder::class,
            \Database\Seeders\Master\Depot\MstDepotSeeder::class,

            // Vehicle
            \Database\Seeders\Master\MstVehicleSeeder::class,

            // Products
            \Database\Seeders\Master\Product\MstCategorySeeder::class,
            \Database\Seeders\Master\Product\MstProductSeeder::class,
            \Database\Seeders\Master\Product\MstProductVariantSeeder::class,
            \Database\Seeders\Master\Product\MstProductPackagingSeeder::class,


            // Charge Seeders
            \Database\Seeders\Master\Charge\MstChargeSeeder::class,
            \Database\Seeders\Master\Charge\MstChargeLevelSeeder::class,
            \Database\Seeders\Master\Charge\MstMinimumOrderChargeRuleSeeder::class,
            \Database\Seeders\Master\Charge\MstDeliveryChargeRuleSeeder::class,

            // Fulfillment Location Seeder
            \Database\Seeders\Fulfillment\FulfillmentLocationSeeder::class,


        ]);
    }
}
