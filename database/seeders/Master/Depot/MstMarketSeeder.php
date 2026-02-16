<?php

namespace Database\Seeders\Master\Depot;

use App\Enum\Common\Fulfillment\FulfillmentLocationTypeEnum;
use App\Enum\Common\Legal\KycStatusEnum;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstMarketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        $address = Address::create([
            'addr_code' => Address::generateUniqueAddrCode(),
            'addr_name' => 'Sardar Market (APMC-Surat)',
            'addr_type' => 'MARKET',
            'address_line1' => 'Sardar Market, APMC Yard, Surat',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'state_iso' => 'GJ',
            'postal_code' => '395003',
            'country' => 'India',
            'country_iso' => 'IN',
        ]);

        $fulfillmentLocation = FulfillmentLocation::create([
            'name' => 'Sardar Market (APMC-Surat)',
            'fl_code' => 'FL-APMC-SRT',
            'addr_code' => $address->addr_code,
            'type' => FulfillmentLocationTypeEnum::MARKET->value,
            'is_active' => true,
            // Verification audit fields
            'status' => KycStatusEnum::APPROVED->value,
            'verification_mode' => 'auto',
            'verified_at' => now(),
            'verified_by' => 'System',
            'verified_user_id' => null,
        ]);


        DB::table('mst_markets')->insert([
            [
                'name' => 'Sardar Market (APMC-Surat)',
                'code' => 'MKT0001',
                'fulfillment_location_id' => $fulfillmentLocation->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);
    }
}
