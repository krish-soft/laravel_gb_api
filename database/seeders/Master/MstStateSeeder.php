<?php

namespace Database\Seeders\Master;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstStateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        DB::table('mst_states')->insert([

            // ================= STATES (28) =================
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Andhra Pradesh', 'iso_code' => 'AP', 'language' => 'te', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Arunachal Pradesh', 'iso_code' => 'AR', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Assam', 'iso_code' => 'AS', 'language' => 'as', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Bihar', 'iso_code' => 'BR', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Chhattisgarh', 'iso_code' => 'CG', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Goa', 'iso_code' => 'GA', 'language' => 'kok', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Gujarat', 'iso_code' => 'GJ', 'language' => 'gu', 'type' => 'ST', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Haryana', 'iso_code' => 'HR', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Himachal Pradesh', 'iso_code' => 'HP', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Jharkhand', 'iso_code' => 'JH', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Karnataka', 'iso_code' => 'KA', 'language' => 'kn', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Kerala', 'iso_code' => 'KL', 'language' => 'ml', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Madhya Pradesh', 'iso_code' => 'MP', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Maharashtra', 'iso_code' => 'MH', 'language' => 'mr', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Manipur', 'iso_code' => 'MN', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Meghalaya', 'iso_code' => 'ML', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Mizoram', 'iso_code' => 'MZ', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Nagaland', 'iso_code' => 'NL', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Odisha', 'iso_code' => 'OD', 'language' => 'or', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Punjab', 'iso_code' => 'PB', 'language' => 'pa', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Rajasthan', 'iso_code' => 'RJ', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Sikkim', 'iso_code' => 'SK', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Tamil Nadu', 'iso_code' => 'TN', 'language' => 'ta', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Telangana', 'iso_code' => 'TS', 'language' => 'te', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Tripura', 'iso_code' => 'TR', 'language' => 'bn', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Uttar Pradesh', 'iso_code' => 'UP', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'Uttarakhand', 'iso_code' => 'UK', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => false, 'country_iso_code' => 'IN', 'name' => 'West Bengal', 'iso_code' => 'WB', 'language' => 'bn', 'type' => 'ST', 'is_active' => false],

            // ================= UNION TERRITORIES (8) =================
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Andaman and Nicobar Islands', 'iso_code' => 'AN', 'language' => 'en', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Chandigarh', 'iso_code' => 'CH', 'language' => 'hi', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Dadra and Nagar Haveli and Daman and Diu', 'iso_code' => 'DH', 'language' => 'gu', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Delhi', 'iso_code' => 'DL', 'language' => 'hi', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Jammu and Kashmir', 'iso_code' => 'JK', 'language' => 'ur', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Ladakh', 'iso_code' => 'LA', 'language' => 'en', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Lakshadweep', 'iso_code' => 'LD', 'language' => 'ml', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'is_ut' => true, 'country_iso_code' => 'IN', 'name' => 'Puducherry', 'iso_code' => 'PY', 'language' => 'ta', 'type' => 'UT', 'is_active' => false],
        ]);
    }
}
