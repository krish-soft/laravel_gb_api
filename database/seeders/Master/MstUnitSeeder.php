<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        $units = [
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => true, 'name' => 'Kilogram', 'unit' => 'kg'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => true, 'name' => 'Gram', 'unit' => 'g'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => true, 'name' => 'Crate', 'unit' => 'ct'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Milligram', 'unit' => 'mg'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Litre', 'unit' => 'l'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Millilitre', 'unit' => 'ml'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Piece', 'unit' => 'pc'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Dozen', 'unit' => 'dz'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Meter', 'unit' => 'm'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Centimeter', 'unit' => 'cm'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Millimeter', 'unit' => 'mm'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Packet', 'unit' => 'pkt'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Box', 'unit' => 'box'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Set', 'unit' => 'set'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Pair', 'unit' => 'pair'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Tray', 'unit' => 'tray'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Bundle', 'unit' => 'bundle'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Carton', 'unit' => 'carton'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Quintal', 'unit' => 'q'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Ton', 'unit' => 't'],

        ];

        foreach ($units as $unit) {
            DB::table('mst_units')->insert($unit);
        }
    }
}
