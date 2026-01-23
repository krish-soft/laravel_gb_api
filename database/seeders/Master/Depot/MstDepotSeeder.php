<?php

namespace Database\Seeders\Master\Depot;

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


        DB::table('mst_depots')->insert([
            [
                'zone_id' => $southGujaratZoneId,
                'name' => 'Kim Depot',
                'code' => 'DPT0001',
                'max_capacity_kg' => 5000,
                'buyer_cutoff_time' => '08:00:00',
                'seller_cutoff_time' => '09:00:00',
                'contact_name' => 'Divyesh Patel',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'zone_id' => $southGujaratZoneId,
                'name' => 'Kosamba Depot',
                'code' => 'DPT0002',
                'max_capacity_kg' => 5000,
                'buyer_cutoff_time' => '08:00:00',
                'seller_cutoff_time' => '09:00:00',
                'is_active' => true,
                'contact_name' => 'Ketan Patel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
