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

            // ===== VEGETABLES =====

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P001', 'name' => 'Potato | બટાકા | आलू', 'hsn' => '0701', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P002', 'name' => 'Onion | ડુંગળી | प्याज', 'hsn' => '0703', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P003', 'name' => 'Tomato | ટમેટા | टमाटर', 'hsn' => '0702', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P004', 'name' => 'Green Chilli | લીલા મરચા | हरी मिर्च', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P005', 'name' => 'Garlic | લસણ | लहसुन', 'hsn' => '0703', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P006', 'name' => 'Green Garlic | લીલું લસણ | हरा लहसुन', 'hsn' => '0703', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P007', 'name' => 'Ginger | આદુ | अदरक', 'hsn' => '0910', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P008', 'name' => 'Cabbage | કોબી | पत्ता गोभी', 'hsn' => '0704', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P009', 'name' => 'Cauliflower | ફૂલકોબી | फूलगोभी', 'hsn' => '0704', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P010', 'name' => 'Capsicum | શિમલા મરચાં | शिमला मिर्च', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P011', 'name' => 'Brinjal | રીંગણ | बैंगन', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P012', 'name' => 'Okra | ભીંડા | भिंडी', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P013', 'name' => 'Bottle Gourd | દૂધી | लौकी', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P014', 'name' => 'Ridge Gourd | તુરીયા | तुरई', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P015', 'name' => 'Bitter Gourd | કરેલા | करेला', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P016', 'name' => 'Snake Gourd | ચીચીંડા | चिचिंडा', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P017', 'name' => 'Ash Gourd | પેઠા | पेठा', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P018', 'name' => 'Pumpkin | કુમળું | कद्दू', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P019', 'name' => 'Ivy Gourd | ટિંડોરા | कुंदरू', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P020', 'name' => 'Pointed Gourd | પરવાલ | परवल', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P021', 'name' => 'Carrot | ગાજર | गाजर', 'hsn' => '0706', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P022', 'name' => 'Beetroot | બીટ | चुकंदर', 'hsn' => '0706', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P023', 'name' => 'Radish | મૂળા | मूली', 'hsn' => '0706', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P024', 'name' => 'Turnip | શલગમ | शलगम', 'hsn' => '0706', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P025', 'name' => 'Cucumber | કાકડી | खीरा', 'hsn' => '0707', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P026', 'name' => 'Peas | વટાણા | मटर', 'hsn' => '0708', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P027', 'name' => 'Cluster Beans | ગવાર | ग्वार', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P028', 'name' => 'Drumstick | સરગવો | सहजन', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P029', 'name' => 'Spinach | પાલક | पालक', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P030', 'name' => 'Fenugreek Leaves | મેથી | मेथी', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P031', 'name' => 'Coriander Leaves | કોથમીર | धनिया पत्ता', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P032', 'name' => 'Mint Leaves | પુદીના | पुदीना', 'hsn' => '0709', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P033', 'name' => 'Spring Onion | લીલા કાંદા | हरा प्याज', 'hsn' => '0703', 'is_active' => true],

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P034', 'name' => 'Colocasia | અરબી | अरबी', 'hsn' => '0714', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P035', 'name' => 'Elephant Foot Yam | સૂરણ | जिमीकंद', 'hsn' => '0714', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P036', 'name' => 'Sweet Potato | શક્કરકંદ | शकरकंद', 'hsn' => '0714', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P037', 'name' => 'Lotus Stem | કમળના ડાંઠ | कमल ककड़ी', 'hsn' => '0709', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P038', 'name' => 'Mushroom | મશરૂમ | मशरूम', 'hsn' => '0709', 'is_active' => true],


            // ===== FRUITS USED AS VEGETABLES =====

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P039', 'name' => 'Raw Banana | કાચા કેળા | कच्चा केला', 'hsn' => '0803', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P040', 'name' => 'Raw Mango | કાચી કેરી | कच्चा आम', 'hsn' => '0804', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P041', 'name' => 'Raw Papaya | કાચું પપૈયું | कच्चा पपीता', 'hsn' => '0807', 'is_active' => true],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Vegetables']->id, 'product_code' => $categories['Vegetables']->hsn_chapter . 'P042', 'name' => 'Raw Jackfruit | કાચું ફણસ | कच्चा कटहल', 'hsn' => '0801', 'is_active' => true],


            // ===== FRUITS (INACTIVE) =====

            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P001', 'name' => 'Apple | સફરજન | सेब', 'hsn' => '0808', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P002', 'name' => 'Banana | કેળું | केला', 'hsn' => '0803', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P003', 'name' => 'Mango | કેરી | आम', 'hsn' => '0804', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P004', 'name' => 'Orange | નારંગી | संतरा', 'hsn' => '0805', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P005', 'name' => 'Papaya | પપૈયું | पपीता', 'hsn' => '0807', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P006', 'name' => 'Pomegranate | દાડમ | अनार', 'hsn' => '0810', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P007', 'name' => 'Grapes | દ્રાક્ષ | अंगूर', 'hsn' => '0806', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P008', 'name' => 'Guava | જામફળ | अमरूद', 'hsn' => '0804', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P009', 'name' => 'Watermelon | તરબૂચ | तरबूज', 'hsn' => '0807', 'is_active' => false],
            ['created_at' => now(), 'updated_at' => now(), 'category_id' => $categories['Fruits']->id, 'product_code' => $categories['Fruits']->hsn_chapter . 'P010', 'name' => 'Pineapple | અનાનસ | अनानास', 'hsn' => '0804', 'is_active' => false],



            //
        ]);
    }
}
