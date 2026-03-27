<?php

namespace Database\Seeders\Master\Price;

use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstProductPriceRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $ruleNo = MstSeqCodeGenerator::getNextRuleNo();

        $standardPriceLevelBuyer = DB::table('mst_charge_levels')->where('code', 'B-STD')->first();

        // Insert default charges
        DB::table('mst_product_price_rules')->insert([
            [
                'rule_no' => $ruleNo,
                'charge_level_id' => $standardPriceLevelBuyer->id, // Buyer Standard
                'user_type' => UserTypeEnum::RESTAURANT->value, // buyer
                'pack_unit' => 'kg',
                'calc_type' => 'percentage',

                '1_pkg' => 3.00,  // percentage
                '2_pkg' => 5.00, // 2 Kg Pkg price
                '3_pkg' => 5.00, // 3 Kg Pkg price
                '5_pkg' => 10.00, // 5 Kg Pkg price
                '10_pkg' => 10.00, // 10 Kg Pkg price
                '20_pkg' => 15.00, // 20 Kg Pkg price

                'is_active' => true,
            ],


        ]);
    }
}
