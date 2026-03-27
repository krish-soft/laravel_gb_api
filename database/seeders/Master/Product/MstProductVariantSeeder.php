<?php

namespace Database\Seeders\Master\Product;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $p = DB::table('mst_products')->get()->keyBy('name');

        DB::table('mst_product_variants')->insert([

            /*
        |--------------------------------------------------------------------------
        | VEGETABLES – ACTIVE (Top 10)
        |--------------------------------------------------------------------------
        */

            // Tomato
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Tomato | ટમેટા | टमाटर']->id, 'variant_code' => $p['Tomato | ટમેટા | टमाटर']->product_code . 'V' . '01', 'name' => 'Desi', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Tomato | ટમેટા | टमाटर']->id, 'variant_code' => $p['Tomato | ટમેટા | टमाटर']->product_code . 'V' . '02', 'name' => 'Hybrid', 'is_active' => true],

            // // Onion
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Onion | ડુંગળી | प्याज']->id, 'variant_code' => $p['Onion | ડુંગળી | प्याज']->product_code . 'V' . '01', 'name' => 'Red', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Onion | ડુંગળી | प्याज']->id, 'variant_code' => $p['Onion | ડુંગળી | प्याज']->product_code . 'V' . '02', 'name' => 'White', 'is_active' => true],

            // // Potato
            // // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Potato | બટાકા | आलू']->id, 'variant_code' => $p['Potato | બટાકા | आलू']->product_code . 'V' . '01', 'name' => 'Jyoti', 'is_active' => true],

            // // Brinjal
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Brinjal | રીંગણ | बैंगन']->id, 'variant_code' => $p['Brinjal | રીંગણ | बैंगन']->product_code . 'V' . '01', 'name' => 'Long', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Brinjal | રીંગણ | बैंगन']->id, 'variant_code' => $p['Brinjal | રીંગણ | बैंगन']->product_code . 'V' . '02', 'name' => 'Round', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Brinjal | રીંગણ | बैंगन']->id, 'variant_code' => $p['Brinjal | રીંગણ | बैंगन']->product_code . 'V' . '03', 'name' => 'Desi', 'is_active' => true],

            // Cabbage
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Cabbage | કોબી | पत्ता गोभी']->id, 'variant_code' => $p['Cabbage | કોબી | पत्ता गोभी']->product_code . 'V' . '01', 'name' => 'Green', 'is_active' => true],

            // Cauliflower
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Cauliflower | ફૂલકોબી | फूलगोभी']->id, 'variant_code' => $p['Cauliflower | ફૂલકોબી | फूलगोभी']->product_code . 'V' . '01', 'name' => 'Snowball', 'is_active' => true],

            // Okra
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Okra | ભીંડા | भिंडी']->id, 'variant_code' => $p['Okra | ભીંડા | भिंडी']->product_code . 'V' . '01', 'name' => 'Desi', 'is_active' => true],

            // Green Chilli
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Green Chilli | લીલા મરચા | हरी मिर्च']->id, 'variant_code' => $p['Green Chilli | લીલા મરચા | हरी मिर्च']->product_code . 'V' . '01', 'name' => 'Spicy', 'is_active' => true],

            // Spinach
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Spinach | પાલક | पालक']->id, 'variant_code' => $p['Spinach | પાલક | पालक']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],

            /*
            |--------------------------------------------------------------------------
            | VEGETABLES – INACTIVE
            |--------------------------------------------------------------------------
            */

            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Bottle Gourd | દૂધી | लौकी']->id, 'variant_code' => $p['Bottle Gourd | દૂધી | लौकी']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Bitter Gourd | કરેલા | करेला']->id, 'variant_code' => $p['Bitter Gourd | કરેલા | करेला']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Ridge Gourd | તુરીયા | तुरई']->id, 'variant_code' => $p['Ridge Gourd | તુરીયા | तुरई']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Pumpkin | કુમળું | कद्दू']->id, 'variant_code' => $p['Pumpkin | કુમળું | कद्दू']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Beetroot | બીટ | चुकंदर']->id, 'variant_code' => $p['Beetroot | બીટ | चुकंदर']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Radish | મૂળા | मूली']->id, 'variant_code' => $p['Radish | મૂળા | मूली']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Cluster Beans | ગવાર | ग्वार']->id, 'variant_code' => $p['Cluster Beans | ગવાર | ग्वार']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Drumstick | સરગવો | सहजन']->id, 'variant_code' => $p['Drumstick | સરગવો | सहजन']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Cucumber | કાકડી | खीरा']->id, 'variant_code' => $p['Cucumber | કાકડી | खीरा']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Peas | વટાણા | मटर']->id, 'variant_code' => $p['Peas | વટાણા | मटर']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],

            /*
            |--------------------------------------------------------------------------
            | FRUITS – ACTIVE (Top 10)
            |--------------------------------------------------------------------------
            */

            // Apple
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Apple | સફરજન | सेब']->id, 'variant_code' => $p['Apple | સફરજન | सेब']->product_code . 'V' . '01', 'name' => 'Kashmir', 'is_active' => true],

            // // Banana
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Banana | કેળું | केला']->id, 'variant_code' => $p['Banana | કેળું | केला']->product_code . 'V' . '01', 'name' => 'Robusta', 'is_active' => true],

            // // Mango
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Mango | કેરી | आम']->id, 'variant_code' => $p['Mango | કેરી | आम']->product_code . 'V' . '01', 'name' => 'Alphonso', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Mango | કેરી | आम']->id, 'variant_code' => $p['Mango | કેરી | आम']->product_code . 'V' . '02', 'name' => 'Kesar', 'is_active' => true],

            // // Orange
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Orange | નારંગી | संतरा']->id, 'variant_code' => $p['Orange | નારંગી | संतरा']->product_code . 'V' . '01', 'name' => 'Nagpur', 'is_active' => true],

            // // Papaya
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Papaya | પપૈયું | पपीता']->id, 'variant_code' => $p['Papaya | પપૈયું | पपीता']->product_code . 'V' . '01', 'name' => 'Red Lady', 'is_active' => true],

            // // Pomegranate
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Pomegranate | દાડમ | अनार']->id, 'variant_code' => $p['Pomegranate | દાડમ | अनार']->product_code . 'V' . '01', 'name' => 'Bhagwa', 'is_active' => true],

            // // Grapes
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Grapes | દ્રાક્ષ | अंगूर']->id, 'variant_code' => $p['Grapes | દ્રાક્ષ | अंगूर']->product_code . 'V' . '01', 'name' => 'Green', 'is_active' => true],

            // // Guava
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Guava | જામફળ | अमरूद']->id, 'variant_code' => $p['Guava | જામફળ | अमरूद']->product_code . 'V' . '01', 'name' => 'Allahabad', 'is_active' => true],

            // // Watermelon
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Watermelon | તરબૂચ | तरबूज']->id, 'variant_code' => $p['Watermelon | તરબૂચ | तरबूज']->product_code . 'V' . '01', 'name' => 'Sugar Baby', 'is_active' => true],

            /*
            |--------------------------------------------------------------------------
            | FRUITS – INACTIVE
            |--------------------------------------------------------------------------
            */

            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Chikoo | ચીકૂ | चीकू']->id, 'variant_code' => $p['Chikoo | ચીકૂ | चीकू']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Custard Apple | સીતાફળ | सीताफल']->id, 'variant_code' => $p['Custard Apple | સીતાફળ | सीताफल']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Litchi | લીચી | लीची']->id, 'variant_code' => $p['Litchi | લીચી | लीची']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Jackfruit | ફણસ | कटहल']->id, 'variant_code' => $p['Jackfruit | ફણસ | कटहल']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],
            // ['created_at' => now(), 'updated_at' => now(), 'product_id' => $p['Strawberry | સ્ટ્રોબેરી | स्ट्रॉबेरी']->id, 'variant_code' => $p['Strawberry | સ્ટ્રોબેરી | स्ट्रॉबेरी']->product_code . 'V' . '01', 'name' => 'Local', 'is_active' => true],



        ]);
    }
}
