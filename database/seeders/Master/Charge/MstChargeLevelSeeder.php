<?php

namespace Database\Seeders\Master\Charge;

use App\Enum\User\UserRoleEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstChargeLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert default charges
        DB::table('mst_charge_levels')->insert([
            // BUYER
            ['code' => 'B-STD', 'name' => 'Buyer Standard', 'description' => 'Standard charge level for buyers', 'user_role_type' => UserRoleEnum::BUYER->value,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'B-EXT', 'name' => 'Buyer Extended', 'description' => 'Extended charge level for buyers', 'user_role_type' => UserRoleEnum::BUYER->value,  'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'B-PRO', 'name' => 'Buyer Promo', 'description' => 'Promotional charge level for buyers', 'user_role_type' => UserRoleEnum::BUYER->value,  'is_active' => false, 'created_at' => now(), 'updated_at' => now()],

            // SELLER
            ['code' => 'S-STD', 'name' => 'Seller Standard', 'description' => 'Standard charge level for sellers', 'user_role_type' => UserRoleEnum::SELLER->value,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'S-EXT', 'name' => 'Seller Extended', 'description' => 'Extended charge level for sellers', 'user_role_type' => UserRoleEnum::SELLER->value,  'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'S-PRO', 'name' => 'Seller Promo', 'description' => 'Promotional charge level for sellers', 'user_role_type' => UserRoleEnum::SELLER->value,  'is_active' => false, 'created_at' => now(), 'updated_at' => now()],

            // DELIVERY
            ['code' => 'D-STD', 'name' => 'Delivery Standard', 'description' => 'Standard charge level for delivery', 'user_role_type' => UserRoleEnum::DELIVERY->value,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'D-EXT', 'name' => 'Delivery Extended', 'description' => 'Extended charge level for delivery', 'user_role_type' => UserRoleEnum::DELIVERY->value,  'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'D-PRO', 'name' => 'Delivery Promo', 'description' => 'Promotional charge level for delivery', 'user_role_type' => UserRoleEnum::DELIVERY->value,  'is_active' => false, 'created_at' => now(), 'updated_at' => now()],


        ]);
    }
}
