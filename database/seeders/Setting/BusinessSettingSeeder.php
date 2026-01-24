<?php

namespace Database\Seeders\Setting;

use App\Enum\AddressTypeEnum;
use App\Models\Common\Address;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstBusinessSetting;
use Illuminate\Database\Seeder;

class BusinessSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //


        $addr = Address::create([
            'addr_code' => 'HO001',
            'addr_name' => 'Head Office',
            'addr_type' => AddressTypeEnum::BILL->value,

            'address_line1' => '108, Tulsi Market',
            'address_line2' => 'Ring Road',

            'city' => 'Surat',
            'state' => 'Gujarat',
            'state_iso' => 'GJ',
            'postal_code' => '395002',
            'country' => 'India',
            'country_iso' => 'IN',

            // 'contact_name' => '',
            'dial_code' => '+91',
            'phone_number' => '0261-3918139',

            'latitude' => '21.1702',
            'longitude' => '72.8311',

        ]);




        MstBusinessSetting::create([
            // App identity
            'legal_name' => 'Krishna Software Pvt Ltd',
            'trade_name' => 'Green Bazar',

            'bill_addr_code' => $addr->addr_code,

            //
            'website' => 'https://greenbazar.net.in',

            'is_active' => true,

        ]);
    }
}
