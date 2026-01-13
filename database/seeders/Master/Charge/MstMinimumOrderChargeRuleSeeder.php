<?php

namespace Database\Seeders\Master\Charge;

use App\Enum\Common\Charge\ChargesEnum;
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


        DB::table('mst_minimum_order_charge_rules')->insert([
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders below or equal to 2500 for Buyer',
                'calc_type' => 'fixed',
                'calc_condition' => '<=',
                'min_order_price' => 2500.00,
                'charge_amount' => 100.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders above 2500 for Buyer',
                'calc_type' => 'percentage',
                'calc_condition' => '>',
                'min_order_price' => 2500.00,
                'charge_amount' => 2.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders below or equal to 2500 for Seller',
                'calc_type' => 'fixed',
                'calc_condition' => '<=',
                'min_order_price' => 2500.00,
                'charge_amount' => 100.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders above 2500 for Seller',
                'calc_type' => 'percentage',
                'calc_condition' => '>',
                'min_order_price' => 2500.00,
                'charge_amount' => 2.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelDelivery->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders below or equal to 2500 for Delivery',
                'calc_type' => 'fixed',
                'calc_condition' => '<=',
                'min_order_price' => 2500.00,
                'charge_amount' => 100.00,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $platformFeeCharge->id,
                'charge_level_id' => $standardPriceLevelDelivery->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Platform Fee for orders above 2500 for Delivery',
                'calc_type' => 'percentage',
                'calc_condition' => '>',
                'min_order_price' => 2500.00,
                'charge_amount' => 2.00,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],



        ]);
    }
}
