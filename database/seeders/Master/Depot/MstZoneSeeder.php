<?php

namespace Database\Seeders\Master\Depot;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        // Fetch Gujarat state id
        $gujaratId = DB::table('mst_states')
            ->where('iso_code', 'GJ')
            ->value('id');

        if (!$gujaratId) {
            return; // safety check
        }

        DB::table('mst_zones')->insert([
            [
                'state_id'  => $gujaratId,
                'name'      => 'South Gujarat Zone',
                'code'      => 'GJ-SOUTH',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'state_id'  => $gujaratId,
                'name'      => 'Central Gujarat Zone',
                'code'      => 'GJ-CENTRAL',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'state_id'  => $gujaratId,
                'name'      => 'North Gujarat Zone',
                'code'      => 'GJ-NORTH',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'state_id'  => $gujaratId,
                'name'      => 'Saurashtra Zone',
                'code'      => 'GJ-SAURASHTRA',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'state_id'  => $gujaratId,
                'name'      => 'Kutch Zone',
                'code'      => 'GJ-KUTCH',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
