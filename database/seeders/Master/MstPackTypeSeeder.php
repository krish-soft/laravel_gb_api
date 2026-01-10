<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstPackTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        $units = [
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => true, 'name' => 'Bag', 'unit' => 'BAG'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => true, 'name' => 'Crate', 'unit' => 'CRATE'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Box', 'unit' => 'BOX'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Packet', 'unit' => 'PACKET'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Piece', 'unit' => 'PIECE'],


        ];

        foreach ($units as $unit) {
            DB::table('mst_pack_types')->insert($unit);
        }
    }
}
