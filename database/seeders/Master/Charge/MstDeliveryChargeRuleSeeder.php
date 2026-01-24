<?php

namespace Database\Seeders\Master\Charge;

use App\Enum\Common\Charge\ChargesEnum;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstDeliveryChargeRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $deliveryFeeCharge = DB::table('mst_charges')->where('code', ChargesEnum::DELIVERY_FEE->value)->first();

        $standardPriceLevelBuyer = DB::table('mst_charge_levels')->where('code', 'B-STD')->first();
        $standardPriceLevelSeller = DB::table('mst_charge_levels')->where('code', 'S-STD')->first();
        // $standardPriceLevelDelivery = DB::table('mst_charge_levels')->where('code', 'D-STD')->first();


        DB::table('mst_delivery_charge_rules')->insert([
            // Buyer Delivery Fees
            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,

                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Buyer',
              
                'measure_value' => 20.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 40.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,

                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Buyer',
              
                'measure_value' => 20.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'crate',
                'charge_amount' => 40.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,

                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Buyer',
              
                'measure_value' => 10.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 25.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelBuyer->id,
                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Buyer',
              
                'measure_value' => 5.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 10.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Seller Delivery Fees
            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,

                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Seller',
              
                'measure_value' => 20.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 40.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,

                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Seller',
              
                'measure_value' => 20.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'crate',
                'charge_amount' => 40.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,

                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Seller',
              
                'measure_value' => 10.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 25.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'charge_id' => $deliveryFeeCharge->id,
                'charge_level_id' => $standardPriceLevelSeller->id,

                'rule_no' => MstSeqCodeGenerator::getNextRuleNo(),
                'description' => 'Delivery Fee for Seller',
              
                'measure_value' => 5.00,
                'measure_unit' => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 10.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],


        ]);
    }
}
