<?php

namespace Database\Seeders\Master\Charge;

use App\Enum\Common\Charge\ChargesEnum;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstDeliveryChargeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $deliveryFeeCharge = DB::table('mst_charges')
            ->where('code', ChargesEnum::DELIVERY_FEE->value)
            ->first();

        if (!$deliveryFeeCharge) {
            return;
        }

        // --------------------------------------------------
        // CHARGE LEVELS (Buyer / Seller / Driver)
        // --------------------------------------------------
        $levels = DB::table('mst_charge_levels')
            ->whereIn('code', ['B-STD', 'S-STD', 'D-STD'])
            ->pluck('id', 'code');

        // --------------------------------------------------
        // MASTER RULE MATRIX (DEFINE ONLY ONCE)
        // --------------------------------------------------
        $ruleMatrix = [
            [
                'description'   => 'Delivery Fee Standard',
                'measure_value' => 20.00,
                'measure_unit'  => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 40.00,
            ],
            [
                'description'   => 'Delivery Fee Standard',
                'measure_value' => 20.00,
                'measure_unit'  => 'kg',
                'pack_type_unit' => 'crate',
                'charge_amount' => 40.00,
            ],
            [
                'description'   => 'Delivery Fee Standard',
                'measure_value' => 10.00,
                'measure_unit'  => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 25.00,
            ],
            [
                'description'   => 'Delivery Fee Standard',
                'measure_value' => 5.00,
                'measure_unit'  => 'kg',
                'pack_type_unit' => 'bag',
                'charge_amount' => 10.00,
            ],
        ];

        // --------------------------------------------------
        // BUILD INSERT DATA FOR ALL LEVELS
        // --------------------------------------------------
        $insertData = [];

        foreach ($levels as $levelCode => $levelId) {

            foreach ($ruleMatrix as $rule) {

                $insertData[] = [
                    'charge_id'       => $deliveryFeeCharge->id,
                    'charge_level_id' => $levelId,

                    'rule_no'     => MstSeqCodeGenerator::getNextRuleNo(),
                    'description' => $rule['description'],

                    'measure_value'   => $rule['measure_value'],
                    'measure_unit'    => $rule['measure_unit'],
                    'pack_type_unit'  => $rule['pack_type_unit'],
                    'charge_amount'   => $rule['charge_amount'],

                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // --------------------------------------------------
        // BULK INSERT
        // --------------------------------------------------
        DB::table('mst_delivery_charge_rules')->insert($insertData);
    }
}
