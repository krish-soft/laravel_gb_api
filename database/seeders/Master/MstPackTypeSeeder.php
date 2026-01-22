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
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => true, 'name' => 'Bag', 'unit' => 'bag'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => true, 'name' => 'Crate', 'unit' => 'crate'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Box', 'unit' => 'box'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Packet', 'unit' => 'packet'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Piece', 'unit' => 'piece'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Dozen', 'unit' => 'dz'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Set', 'unit' => 'set'],


        ];

        foreach ($units as $unit) {
            DB::table('mst_pack_types')->insert($unit);
        }
    }
}
