<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstVehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        DB::table('mst_vehicles')->insert([
            [
                'vehicle_name' => 'Bicycle Cart',
                'vehicle_code' => 'cart',
                'body_type' => 'cart',
                'fuel_type' => 'human_powered',
                'capacity_class' => 'micro',
                'max_weight_kg' => 150,
                'max_volume_cft' => 25,
                'max_crates' => 6,
                'priority_order' => 3,
                'is_active' => true,
                'description' => 'Crowded area delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'vehicle_name' => 'Auto Rickshaw',
                'vehicle_code' => 'rickshaw',
                'body_type' => 'rickshaw',
                'fuel_type' => 'petrol/cng',
                'capacity_class' => 'small',
                'max_weight_kg' => 500,
                'max_volume_cft' => 60,
                'max_crates' => 15,
                'priority_order' => 1,
                'is_active' => true,
                'description' => 'Local transport',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'vehicle_name' => 'Pickup Van',
                'vehicle_code' => 'pickup_van',
                'body_type' => 'van',
                'fuel_type' => 'diesel',
                'capacity_class' => 'medium',
                'max_weight_kg' => 1000,
                'max_volume_cft' => 120,
                'max_crates' => 30,
                'priority_order' => 2,
                'is_active' => true,
                'description' => 'Light goods transport',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'vehicle_name' => 'Small Tempo',
                'vehicle_code' => 'sm_tempo',
                'body_type' => 'truck',
                'fuel_type' => 'diesel',
                'capacity_class' => 'large',
                'max_weight_kg' => 2500,
                'max_volume_cft' => 300,
                'max_crates' => 80,
                'priority_order' => 4,
                'is_active' => true,
                'description' => '(Chota Hathi)',
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);
    }
}
