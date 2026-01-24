<?php

namespace Database\Seeders\Master\Charge;

use App\Enum\Common\Charge\ChargesEnum;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstMinimumOrderChargeRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $platformFeeCharge = DB::table('mst_charges')->where('code', ChargesEnum::PLATFORM_FEE->value)->first();

        $standardPriceLevelBuyer = DB::table('mst_charge_levels')->where('code', 'B-STD')->first();
        $standardPriceLevelSeller = DB::table('mst_charge_levels')->where('code', 'S-STD')->first();
        $standardPriceLevelDelivery = DB::table('mst_charge_levels')->where('code', 'D-STD')->first();

        $minCartOrderAmount = MstPaymentSetting::minCartOrderAmount();


        DB::table('mst_minimum_order_charge_rules')->insert([
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders below or equal to ' . $minCartOrderAmount . ' for Buyer',
                'calc_base' => 'price',
                'calc_type' => 'fixed',
                'calc_condition' => '<=',
                'min_order_price' => $minCartOrderAmount,
                'charge_amount' => 100.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders above ' . $minCartOrderAmount . ' for Buyer',
                'calc_base' => 'price',
                'calc_type' => 'percentage',
                'calc_condition' => '>',
                'min_order_price' => $minCartOrderAmount,
                'charge_amount' => 2.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders below or equal to ' . $minCartOrderAmount . ' for Seller',
                'calc_base' => 'price',
                'calc_type' => 'fixed',
                'calc_condition' => '<=',
                'min_order_price' => $minCartOrderAmount,
                'charge_amount' => 100.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders above ' . $minCartOrderAmount . '  for Seller',
                'calc_base' => 'price',
                'calc_type' => 'percentage',
                'calc_condition' => '>',
                'min_order_price' => $minCartOrderAmount,
                'charge_amount' => 2.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelDelivery->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders below or equal to ' . $minCartOrderAmount . ' for Delivery',
                'calc_base' => 'price',
                'calc_type' => 'fixed',
                'calc_condition' => '<=',
                'min_order_price' => $minCartOrderAmount,
                'charge_amount' => 100.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelDelivery->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders above ' . $minCartOrderAmount . ' for Delivery',
                'calc_base' => 'price',
                'calc_type' => 'percentage',
                'calc_condition' => '>',
                'min_order_price' => $minCartOrderAmount,
                'charge_amount' => 2.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],



        ]);
    }
}
