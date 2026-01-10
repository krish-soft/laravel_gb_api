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
                'code' => 'DPT-0001',
                'max_capacity_kg' => 25000,
                'buyer_cutoff_time' => '14:00:00',
                'seller_cutoff_time' => '18:00:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'zone_id' => $southGujaratZoneId,
                'name' => 'Kosamba Depot',
                'code' => 'DPT-0002',
                'max_capacity_kg' => 25000,
                'buyer_cutoff_time' => '14:00:00',
                'seller_cutoff_time' => '18:00:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
