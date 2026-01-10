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
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Andhra Pradesh', 'iso_code' => 'IN-AP', 'language' => 'te', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Arunachal Pradesh', 'iso_code' => 'IN-AR', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Assam', 'iso_code' => 'IN-AS', 'language' => 'as', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Bihar', 'iso_code' => 'IN-BR', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Chhattisgarh', 'iso_code' => 'IN-CG', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Goa', 'iso_code' => 'IN-GA', 'language' => 'kok', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Gujarat', 'iso_code' => 'IN-GJ', 'language' => 'gu', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Haryana', 'iso_code' => 'IN-HR', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Himachal Pradesh', 'iso_code' => 'IN-HP', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Jharkhand', 'iso_code' => 'IN-JH', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Karnataka', 'iso_code' => 'IN-KA', 'language' => 'kn', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Kerala', 'iso_code' => 'IN-KL', 'language' => 'ml', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Madhya Pradesh', 'iso_code' => 'IN-MP', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Maharashtra', 'iso_code' => 'IN-MH', 'language' => 'mr', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Manipur', 'iso_code' => 'IN-MN', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Meghalaya', 'iso_code' => 'IN-ML', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Mizoram', 'iso_code' => 'IN-MZ', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Nagaland', 'iso_code' => 'IN-NL', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Odisha', 'iso_code' => 'IN-OD', 'language' => 'or', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Punjab', 'iso_code' => 'IN-PB', 'language' => 'pa', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Rajasthan', 'iso_code' => 'IN-RJ', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Sikkim', 'iso_code' => 'IN-SK', 'language' => 'en', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Tamil Nadu', 'iso_code' => 'IN-TN', 'language' => 'ta', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Telangana', 'iso_code' => 'IN-TS', 'language' => 'te', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Tripura', 'iso_code' => 'IN-TR', 'language' => 'bn', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Uttar Pradesh', 'iso_code' => 'IN-UP', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Uttarakhand', 'iso_code' => 'IN-UK', 'language' => 'hi', 'type' => 'ST', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'West Bengal', 'iso_code' => 'IN-WB', 'language' => 'bn', 'type' => 'ST', 'is_active' => false],

            // ================= UNION TERRITORIES (8) =================
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Andaman and Nicobar Islands', 'iso_code' => 'IN-AN', 'language' => 'en', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Chandigarh', 'iso_code' => 'IN-CH', 'language' => 'hi', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Dadra and Nagar Haveli and Daman and Diu', 'iso_code' => 'IN-DH', 'language' => 'gu', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Delhi', 'iso_code' => 'IN-DL', 'language' => 'hi', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Jammu and Kashmir', 'iso_code' => 'IN-JK', 'language' => 'ur', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Ladakh', 'iso_code' => 'IN-LA', 'language' => 'en', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Lakshadweep', 'iso_code' => 'IN-LD', 'language' => 'ml', 'type' => 'UT', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'name' => 'Puducherry', 'iso_code' => 'IN-PY', 'language' => 'ta', 'type' => 'UT', 'is_active' => false],
        ]);
    }
}
