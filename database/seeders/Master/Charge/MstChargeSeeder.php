<?php

namespace Database\Seeders\Master\Charge;

use App\Enum\Charge\ChargesEnum;
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
                // 'code' => ChargesEnum::PLATFORM_FEE->value,
                // 'name' => 'Platform Fee',
                // 'description' => 'Charge applied for platform usage',
                // 'is_active' => true,
                // 'created_at' => now(),
                // 'updated_at' => now(),

                'code' => ChargesEnum::PLATFORM_FEE->value,
                'name' => 'Platform Fee',

                'description' => 'Platform usage service charge',
                'is_taxable' => true,
                'cgst_percent' => 9.00,
                'sgst_percent' => 9.00,
                'utgst_percent' => 9.00,
                'igst_percent' => 18.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                // 'code' => ChargesEnum::DELIVERY_FEE->value,
                // 'name' => 'Delivery Fee',
                // 'description' => 'Charge applied for order delivery',
                // 'is_active' => true,
                // 'created_at' => now(),
                // 'updated_at' => now(),

                'code' => ChargesEnum::DELIVERY_FEE->value,
                'name' => 'Delivery Fee',

                'description' => 'Transportation of raw agricultural produce',
                'is_taxable' => false,
                'cgst_percent' => 0,
                'sgst_percent' => 0,
                'utgst_percent' => 0,
                'igst_percent' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            //
        ]);
    }
}
