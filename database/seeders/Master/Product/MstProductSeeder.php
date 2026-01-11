<?php

namespace Database\Seeders\Master\Product;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = DB::table('mst_product_categories')
            ->whereIn('name', ['Vegetables', 'Fruits'])
            ->get()
            ->keyBy('name');

        DB::table('mst_products')->insert([

            /*
        |--------------------------------------------------------------------------
        | VEGETABLES – INDIA (HSN 07)
        |--------------------------------------------------------------------------
        */

            // ===== ACTIVE (Top 10) =====
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '001', 'name' => 'Tomato | ટમેટા | टमाटर', 'hsn' => '0702', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '002', 'name' => 'Onion | ડુંગળી | प्याज', 'hsn' => '0703', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '003', 'name' => 'Potato | બટાકા | आलू', 'hsn' => '0701', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '004', 'name' => 'Brinjal | રીંગણ | बैंगन', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '005', 'name' => 'Cabbage | કોબી | पत्ता गोभी', 'hsn' => '0704', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '006', 'name' => 'Cauliflower | ફૂલકોબી | फूलगोभी', 'hsn' => '0704', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '007', 'name' => 'Okra | ભીંડા | भिंडी', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '008', 'name' => 'Green Chilli | લીલા મરચા | हरी मिर्च', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '009', 'name' => 'Carrot | ગાજર | गाजर', 'hsn' => '0706', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '010', 'name' => 'Spinach | પાલક | पालक', 'hsn' => '0709', 'is_active' => true],

           
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '011', 'name' => 'Bottle Gourd | દૂધી | लौकी', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '012', 'name' => 'Bitter Gourd | કરેલા | करेला', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '013', 'name' => 'Ridge Gourd | તુરીયા | तुरई', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '014', 'name' => 'Pumpkin | કુમળું | कद्दू', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '015', 'name' => 'Beetroot | બીટ | चुकंदर', 'hsn' => '0706', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '016', 'name' => 'Radish | મૂળા | मूली', 'hsn' => '0706', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '017', 'name' => 'Cluster Beans | ગવાર | ग्वार', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '018', 'name' => 'Drumstick | સરગવો | सहजन', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '019', 'name' => 'Cucumber | કાકડી | खीरा', 'hsn' => '0707', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P' . '020', 'name' => 'Peas | વટાણા | मटर', 'hsn' => '0708', 'is_active' => true],

            /*
        |--------------------------------------------------------------------------
        | FRUITS – INDIA (HSN 08)
        |--------------------------------------------------------------------------
        */

            // ===== ACTIVE (Top 10) =====
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '001', 'name' => 'Apple | સફરજન | सेब', 'hsn' => '0808', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '002', 'name' => 'Banana | કેળું | केला', 'hsn' => '0803', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '003', 'name' => 'Mango | કેરી | आम', 'hsn' => '0804', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '004', 'name' => 'Orange | નારંગી | संतरा', 'hsn' => '0805', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '005', 'name' => 'Papaya | પપૈયું | पपीता', 'hsn' => '0807', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '006', 'name' => 'Pomegranate | દાડમ | अनार', 'hsn' => '0810', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '007', 'name' => 'Grapes | દ્રાક્ષ | अंगूर', 'hsn' => '0806', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '008', 'name' => 'Guava | જામફળ | अमरूद', 'hsn' => '0804', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '009', 'name' => 'Watermelon | તરબૂચ | तरबूज', 'hsn' => '0807', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '010', 'name' => 'Pineapple | અનાનસ | अनानास', 'hsn' => '0804', 'is_active' => false],

            // ===== INACTIVE =====
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '011', 'name' => 'Chikoo | ચીકૂ | चीकू', 'hsn' => '0804', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '012', 'name' => 'Custard Apple | સીતાફળ | सीताफल', 'hsn' => '0810', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '013', 'name' => 'Litchi | લીચી | लीची', 'hsn' => '0810', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '014', 'name' => 'Jackfruit | ફણસ | कटहल', 'hsn' => '0810', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P' . '015', 'name' => 'Strawberry | સ્ટ્રોબેરી | स्ट्रॉबेरी', 'hsn' => '0810', 'is_active' => false],


        ]);
    }
}
