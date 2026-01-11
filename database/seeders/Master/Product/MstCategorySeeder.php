<?php

namespace Database\Seeders\Master\Product;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('mst_product_categories')->insert([
            [
                'hsn_chapter' => '07',
                'category_code' => '07C0001',
                'name' => 'Vegetables',
                'description' => 'Fresh farm vegetables',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'hsn_chapter' => '08',
                'category_code' => '08C0001',
                'name' => 'Fruits',
                'description' => 'Fresh and dry fruits',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'hsn_chapter' => '08',
                'category_code' => '08C0002',
                'name' => 'Dry Fruits',
                'description' => 'Nuts and dry fruits',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
