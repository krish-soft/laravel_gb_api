<?php

namespace Database\Seeders\Master\Price;

use App\Enum\User\UserRoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstProductPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $firstProduct = DB::table('mst_products')->first();
        $secondProduct = DB::table('mst_products')->skip(1)->first();

        // Insert default charges
        DB::table('mst_product_prices')->insert([
            [
                'product_id' =>  $firstProduct->id,
                'product_code' => $firstProduct->product_code,
                'price' => 30.00,
                'max_price' => 120.00,
                'min_price' => 10.00,
                'market_id' => null,
                'depot_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' =>  $secondProduct->id,
                'product_code' => $secondProduct->product_code,
                'price' => 15.00,
                'max_price' => 120.00,
                'min_price' => 10.00,
                'market_id' => null,
                'depot_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
