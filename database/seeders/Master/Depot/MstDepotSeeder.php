<?php

namespace Database\Seeders\Master\Depot;

use App\Enum\AddressTypeEnum;
use App\Models\Common\Address;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstDepotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        // Fetch South Gujarat Zone ID
        $southGujaratZoneId = DB::table('mst_zones')
            ->where('code', 'GJ-SOUTH')
            ->value('id');

        if (!$southGujaratZoneId) {
            return; // safety
        }

        $marketId = 1;

        $kimAddress = Address::create([
            'addr_code' => 'ADDR-DPT-KIM',
            'addr_name' => 'Khet Bajar (Kim Depot)',
            'addr_type' => AddressTypeEnum::DEPOT->value,
            'address_line1' => 'Near kimamli patiya',
            'address_line2' => 'anita-kim road',
            'village' => 'Kim',
            'taluka' => 'Olpad',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'state_iso' => 'GJ',
            'postal_code' => '394110',
            'country' => 'India',
            'latitude' => 21.411122,
            'longitude' => 72.9023465,
            'created_at' => now(),
            'updated_at' => now(),
        ]);



        DB::table('mst_depots')->insert([
            [
                'zone_id' => $southGujaratZoneId,
                'market_id' => $marketId,
                'name' => 'Kim Depot',
                'code' => 'DPT0001',
                'short_code' => 'KIM',
                'max_capacity_kg' => 5000,
                'buyer_cutoff_time' => '08:00:00',
                'seller_cutoff_time' => '09:00:00',
                'contact_name' => 'Divyesh Patel',
                'addr_code' => $kimAddress->addr_code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'zone_id' => $southGujaratZoneId,
                'market_id' => $marketId,
                'name' => 'Kosamba Depot',
                'code' => 'DPT0002',
                'short_code' => 'KSM',
                'max_capacity_kg' => 5000,
                'buyer_cutoff_time' => '08:00:00',
                'seller_cutoff_time' => '09:00:00',
                'contact_name' => 'Ketan Chauhan',
                'addr_code' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
