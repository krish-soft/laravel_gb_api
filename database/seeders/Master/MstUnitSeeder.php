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
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Gram', 'unit' => 'g'],
            ['created_at' => now(), 'updated_at' => now(), 'is_active' => false, 'name' => 'Litre', 'unit' => 'l'],

        ];

        foreach ($units as $unit) {
            DB::table('mst_units')->insert($unit);
        }
    }
}
