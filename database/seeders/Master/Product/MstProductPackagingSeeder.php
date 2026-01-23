<?php

namespace Database\Seeders\Master\Product;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstProductPackagingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        // Fetch all active products
        $vegetableCategoryId = DB::table('mst_product_categories')
            ->where('category_code', '07C0001')
            ->where('is_active', true)
            ->value('id');

        $products = DB::table('mst_products')
            ->where('is_active', true)
            ->where('category_id', $vegetableCategoryId)
            ->pluck('id');

        if ($products->isEmpty()) {
            return;
        }

        // Standard packaging combinations
        $packagings = [
            [
                'pack_size' => 5,
                'pack_unit' => 'kg',
                'pack_type_unit' => 'BAG',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pack_size' => 10,
                'pack_unit' => 'kg',
                'pack_type_unit' => 'BAG',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pack_size' => 20,
                'pack_unit' => 'kg',
                'pack_type_unit' => 'BAG',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'pack_size' => 20,
                'pack_unit' => 'kg',
                'pack_type_unit' => 'CRATE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $insertData = [];

        foreach ($products as $productId) {
            foreach ($packagings as $pkg) {
                $insertData[] = [
                    'product_id' => $productId,
                    'pack_size' => $pkg['pack_size'],
                    'pack_unit' => $pkg['pack_unit'],
                    'pack_type_unit' => $pkg['pack_type_unit'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('mst_product_packagings')->insert($insertData);
    }
}
