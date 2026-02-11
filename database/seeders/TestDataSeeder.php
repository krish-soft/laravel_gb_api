<?php

namespace Database\Seeders;

use App\Enum\AddressTypeEnum;
use App\Enum\Admin\AdminRoleEnum;
use App\Enum\Admin\AdminUserTypeEnum;
use App\Enum\Common\Fulfillment\FulfillmentLocationTypeEnum;
use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Delivery\DriverVehicle;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //



        /** 
         *  1. Users
         */


        // 
        $buyer =    User::create([

            'user_code' => 'buyer01',
            'nickname' => User::generateUniqueNickName(UserTypeEnum::TRADER->value),
            'name' => 'Buyer Test',

            'dial_code' => '91',
            'phone_number' => '7777777777',
            'password' => bcrypt('password'),

            'role' => UserRoleEnum::BUYER->value,
            'user_type' => UserTypeEnum::TRADER->value,
            'phone_number_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => true,
            'charge_level_code' => 'B-STD',

            'created_at' => now(),
            'updated_at' => now(),
        ]);



        $seller =   User::create([

            'user_code' => 'seller01',
            'nickname' => User::generateUniqueNickName(UserTypeEnum::FARMER->value),
            'name' => 'Seller Test',

            'dial_code' => '91',
            'phone_number' => '8888888888',
            'password' => bcrypt('password'),

            'role' => UserRoleEnum::SELLER->value,
            'user_type' => UserTypeEnum::FARMER->value,
            'phone_number_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => true,
            'charge_level_code' => 'S-STD',

            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $delivery = User::create([

            'user_code' => 'delivery01',
            'nickname' => User::generateUniqueNickName(UserTypeEnum::DELIVERY->value),
            'name' => 'Delivery Test',

            'dial_code' => '91',
            'phone_number' => '6666666666',
            'password' => bcrypt('password'),

            'role' => UserRoleEnum::DELIVERY->value,
            'user_type' => UserTypeEnum::DELIVERY->value,
            'phone_number_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => true,
            'charge_level_code' => 'D-STD',

            'created_at' => now(),
            'updated_at' => now(),
        ]);


        /**
         *  Address & Fulfillment Location
         */


        $buyerAddress = Address::create([
            'addr_code' => 'ADDR-TEST-BUYER',
            'addr_name' => 'Shop Address',
            'addr_type' => AddressTypeEnum::SHIP->value,
            'address_line1' => 'Kim bazar rd',
            'village' => 'Kim',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'state_iso' => 'GJ',
            'postal_code' => '392330',
            'country' => 'India',
        ]);

        $sellerAddress = Address::create([
            'addr_code' => 'ADDR-TEST-SELLER',
            'addr_name' => 'Farm Address',
            'addr_type' => AddressTypeEnum::PICK->value,
            'address_line1' => 'Near Kim Circle',
            'village' => 'Kim',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'state_iso' => 'GJ',
            'postal_code' => '392330',
            'country' => 'India',
        ]);

        FulfillmentLocation::create([
            'user_id' => $buyer->id, // Seller User ID
            'name' => 'Shop',
            'fl_code' => 'FL-0002',
            'addr_code' => $buyerAddress->addr_code,
            'type' => FulfillmentLocationTypeEnum::SHOP->value,
            'is_active' => true,
        ]);


        // add for Seller user where test
        FulfillmentLocation::create([
            'user_id' => $seller->id, // Seller User ID
            'name' => 'Farm Warehouse',
            'fl_code' => 'FL-0001',
            'addr_code' => $sellerAddress->addr_code,
            'type' => FulfillmentLocationTypeEnum::FARM->value,
            'is_active' => true,
        ]);


        DriverVehicle::create([
            'driver_id' => $delivery->id,
            'vehicle_id' => 3, // Chota Hathi Assuming you have a vehicle with ID 3 in mst_vehicles table
            'driver_vehicle_code' => 'DV-TEST-001',
            'license_plate_number' => 'GJ-01-AB-1234',
            'vehicle_color' => 'White',
            'max_load_capacity_kg' => 1000.00,
            'max_volume_capacity_cft' => 500.00,
            'max_number_of_packages' => 100.00,
            'is_active' => true,
            'is_available_for_delivery' => true,
        ]);







        // 
    }
}
