<?php

namespace Database\Seeders\Master\Charge;

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
            ['code' => 'B-STD', 'name' => 'Buyer Standard', 'user_role_type' => 'BUYER',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'B-EXT', 'name' => 'Buyer Extended', 'user_role_type' => 'BUYER',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'B-PRO', 'name' => 'Buyer Promo', 'user_role_type' => 'BUYER',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],

            // SELLER
            ['code' => 'S-STD', 'name' => 'Seller Standard', 'user_role_type' => 'SELLER',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'S-EXT', 'name' => 'Seller Extended', 'user_role_type' => 'SELLER',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'S-PRO', 'name' => 'Seller Promo', 'user_role_type' => 'SELLER',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],

            // DELIVERY
            ['code' => 'D-STD', 'name' => 'Delivery Standard', 'user_role_type' => 'DELIVERY',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'D-EXT', 'name' => 'Delivery Extended', 'user_role_type' => 'DELIVERY',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'D-PRO', 'name' => 'Delivery Promo', 'user_role_type' => 'DELIVERY',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],


        ]);
    }
}
