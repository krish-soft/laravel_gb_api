<?php

namespace Database\Seeders\Master\Charge;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert default charges
        DB::table('mst_charges')->insert([
            [
                'code' => 'PLATFORM',
                'name' => 'Platform Fee',
                'description' => 'Charge applied for platform usage',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'DELIVERY',
                'name' => 'Delivery Fee',
                'description' => 'Charge applied for order delivery',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            //
        ]);
    }
}
