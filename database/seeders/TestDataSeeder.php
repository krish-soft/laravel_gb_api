<?php

namespace Database\Seeders;

use App\Enum\AddressTypeEnum;
use App\Enum\Admin\AdminRoleEnum;
use App\Enum\Admin\AdminUserTypeEnum;
use App\Enum\Common\Fulfillment\FulfillmentLocationTypeEnum;
use App\Enum\Common\Legal\KycStatusEnum;
use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Fulfillment\FulfillmentLocationDepot;
use App\Models\Common\User\Legal\UserKyc;
use App\Models\Common\User\Legal\VehicleKyc;
use App\Models\Common\User\UserDepot;
use App\Models\Delivery\DriverVehicle;
use App\Models\Master\Vehicle\MstVehicle;
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

            'user_code' => 'BUY001',
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

        UserDepot::create([
            'user_id' => $buyer->id,
            'depot_id' => 1, // Assuming you have a depot with ID 1 in mst_depots table
            'is_primary' => true,
        ]);

        UserKyc::create([
            'kyc_code' => UserKyc::generateUniqueKycCode(),
            'user_id' => $buyer->id,
            'legal_name' => $buyer->name,
            'status' => KycStatusEnum::APPROVED->value,
            'verified_at' => now(),
            'verified_by' => 'System',
        ]);

        Account::getOrCreateByOwner(
            Account::getOwnerTypeByUser($buyer),
            $buyer->id
        );




        $seller =   User::create([

            'user_code' => 'SEL001',
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


        Account::getOrCreateByOwner(
            Account::getOwnerTypeByUser($seller),
            $seller->id
        );

        UserDepot::create([
            'user_id' => $seller->id,
            'depot_id' => 1, // Assuming you have a depot with ID 1 in mst_depots table
            'is_primary' => true,
        ]);

        UserKyc::create([
            'kyc_code' => UserKyc::generateUniqueKycCode(),
            'user_id' => $seller->id,
            'legal_name' => $seller->name,
            'status' => KycStatusEnum::APPROVED->value,
            'verified_at' => now(),
            'verified_by' => 'System',
        ]);


        $delivery = User::create([

            'user_code' => 'DRV001',
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

        Account::getOrCreateByOwner(
            Account::getOwnerTypeByUser($delivery),
            $delivery->id
        );

        UserDepot::create([
            'user_id' => $delivery->id,
            'depot_id' => 1, // Assuming you have a depot with ID 1 in mst_depots table
            'is_primary' => true,
        ]);

        UserKyc::create([
            'kyc_code' => UserKyc::generateUniqueKycCode(),
            'user_id' => $delivery->id,
            'legal_name' => $delivery->name,
            'status' => KycStatusEnum::APPROVED->value,
            'verified_at' => now(),
            'verified_by' => 'System',
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

        $buyerFulfillment =  FulfillmentLocation::create([
            'user_id' => $buyer->id, // Seller User ID
            'name' => 'Shop',
            'fl_code' => 'FL-0002',
            'addr_code' => $buyerAddress->addr_code,
            'type' => FulfillmentLocationTypeEnum::SHOP->value,
            'is_active' => true,
            // Verification audit fields
            'status' => KycStatusEnum::APPROVED->value,
            'verification_mode' => 'auto',
            'verified_at' => now(),
            'verified_by' => 'System',
            'verified_user_id' => null,


        ]);

        FulfillmentLocationDepot::create([
            'fulfillment_location_id' => $buyerFulfillment->id,
            'depot_id' => 1, // Assuming you have a depot with ID 1 in mst_depots table
            'is_primary' => true,
        ]);


        // add for Seller user where test
        $sellerFulfillment = FulfillmentLocation::create([
            'user_id' => $seller->id, // Seller User ID
            'name' => 'Farm Warehouse',
            'fl_code' => 'FL-0001',
            'addr_code' => $sellerAddress->addr_code,
            'type' => FulfillmentLocationTypeEnum::FARM->value,
            'is_active' => true,

            // Verification audit fields
            'status' => KycStatusEnum::APPROVED->value,
            'verification_mode' => 'auto',
            'verified_at' => now(),
            'verified_by' => 'System',
            'verified_user_id' => null,
        ]);

        FulfillmentLocationDepot::create([
            'fulfillment_location_id' => $sellerFulfillment->id,
            'depot_id' => 1, // Assuming you have a depot with ID 1 in mst_depots table
            'is_primary' => true,
        ]);


        /**
         *  Driver and Vehicle
         */

        VehicleKyc::create([
            'user_id' => $delivery->id,
            'mst_vehicle_id' => 3, // Chota Hathi Assuming you have a vehicle with ID 3 in mst_vehicles table

            'user_code' => $delivery->user_code,
            'picture' => null,

            'vehicle_kyc_code' => VehicleKyc::generateUniqueKycCode(),
            'license_plate_number' => 'GJ-01-AB-1234',
            'driving_license_number' => 'DL-1234567890',
            'registration_number' => 'REG-1234567890',
            'insurance_policy_number' => 'INS-1234567890',

            // Vehicle details
            'vehicle_maker' => 'Tata',
            'vehicle_model' => 'Ace',
            'vehicle_color' => 'White',
            'vehicle_type' => 'Mini Truck',
            'vehicle_fuel_type' => 'Diesel',
            'vehicle_category' => 'Light Commercial Vehicle',

            'seating_capacity' => 2,
            'load_capacity_kg' => 1500.00,
            'engine_cc' => 700,
            'transmission_type' => 'Manual',
            'vehicle_condition' => 'Good',

            'chassis_number' => 'CHS-1234567890',
            'engine_number' => 'ENG-1234567890',
            'vehicle_unique_mark' => null,
            'vehicle_branding' => null,

            // Verification fields
            'status' => KycStatusEnum::APPROVED->value,

            'is_verified' => true,
            'verification_mode' => 'auto',
            'verified_at' => now(),
            'verified_by' => 'System',
            'verified_user_id' => null,
        ]);

        $mstVehicle = MstVehicle::find(3);

        DriverVehicle::create([
            'driver_id' => $delivery->id,
            'vehicle_id' => 3, // Chota Hathi Assuming you have a vehicle with ID 3 in mst_vehicles table
            'driver_vehicle_code' => 'DV-TEST-001',
            'license_plate_number' => 'GJ-01-AB-1234',
            'vehicle_color' => 'White',
            'max_load_capacity_kg' =>   $mstVehicle->max_weight_kg,
            'max_volume_capacity_cft' => $mstVehicle->max_volume_cft,
            'max_number_of_packages' => $mstVehicle->max_crates,
            'is_active' => true,
            'is_available_for_delivery' => true,
        ]);










        // 
    }
}
