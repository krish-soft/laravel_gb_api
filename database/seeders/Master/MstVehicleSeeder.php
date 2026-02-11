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
                'vehicle_name'    => 'Bicycle Cart',
                'vehicle_code'    => 'cart',
                'body_type'       => 'cart',
                'fuel_type'       => 'human_powered',
                'capacity_class'  => 'micro',
                'max_weight_kg'   => 150,
                'max_volume_cft'  => 35,
                'max_crates'      => 10,
                'priority_order'  => 3,
                'is_active'       => true,
                'description'     => 'Handcart / Thela for crowded mandi delivery',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],

            [
                'vehicle_name'    => 'Auto Rickshaw (Goods)',
                'vehicle_code'    => 'rickshaw',
                'body_type'       => 'rickshaw',
                'fuel_type'       => 'petrol/cng',
                'capacity_class'  => 'small',
                'max_weight_kg'   => 400,
                'max_volume_cft'  => 70,
                'max_crates'      => 20,
                'priority_order'  => 1,
                'is_active'       => true,
                'description'     => 'Local short-distance farm goods transport',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],

            [
                'vehicle_name'    => 'Tata Ace / Pickup Van',
                'vehicle_code'    => 'pickup_van',
                'body_type'       => 'mini_truck',
                'fuel_type'       => 'diesel',
                'capacity_class'  => 'medium',
                'max_weight_kg'   => 900,
                'max_volume_cft'  => 140,
                'max_crates'      => 40,
                'priority_order'  => 2,
                'is_active'       => true,
                'description'     => 'Light commercial farm transport',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],

            [
                'vehicle_name'    => 'Small Tempo (Chota Hathi)',
                'vehicle_code'    => 'sm_tempo',
                'body_type'       => 'truck',
                'fuel_type'       => 'diesel',
                'capacity_class'  => 'large',
                'max_weight_kg'   => 1800,
                'max_volume_cft'  => 260,
                'max_crates'      => 65,
                'priority_order'  => 4,
                'is_active'       => true,
                'description'     => 'Small goods carrier used in mandi transport',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],

            [
                'vehicle_name'    => 'Medium Truck (Tata 407 / Eicher)',
                'vehicle_code'    => 'md_truck',
                'body_type'       => 'truck',
                'fuel_type'       => 'diesel',
                'capacity_class'  => 'xlarge',
                'max_weight_kg'   => 3000,
                'max_volume_cft'  => 450,
                'max_crates'      => 110,
                'priority_order'  => 5,
                'is_active'       => true,
                'description'     => 'Inter-city agriculture logistics',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],

            [
                'vehicle_name'    => 'Large Truck (14ft)',
                'vehicle_code'    => 'lg_truck',
                'body_type'       => 'truck',
                'fuel_type'       => 'diesel',
                'capacity_class'  => 'heavy',
                'max_weight_kg'   => 7000,
                'max_volume_cft'  => 900,
                'max_crates'      => 220,
                'priority_order'  => 6,
                'is_active'       => true,
                'description'     => 'Bulk farm transport and long distance delivery',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],

        ]);
    }
}
