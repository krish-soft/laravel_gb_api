<?php

namespace App\Services\Common\Charge;

use App\Enum\Common\Charge\ChargesEnum;
use App\Models\Master\Charge\MstCharge;
use App\Models\Master\Charge\MstChargeLevel;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChargeCalculationService
{


    public function calculate(
        string $chargeLevelCode,
        float  $orderAmount,
        array  $packages,
        bool   $isBuyerPickup = false,
        bool   $isSellerDropOff = false
    ): array {

        if (!$chargeLevelCode) {
            throw new RuntimeException(__('messages.error_messages.missing_charge_level_code'), 422);
        }

        if ($orderAmount < 0) {
            throw new RuntimeException(__('messages.error_messages.invalid_order_amount'), 422);
        }

        $level = MstChargeLevel::active()
            ->where('code', $chargeLevelCode)
            ->first();

        if (!$level) {
            throw new RuntimeException(__('messages.error_messages.invalid_charge_level'), 422);
        }

        $chargeMasters = MstCharge::active()
            ->with([
                'minimumRuleCharges' => fn($q) => $q->active()
                    ->where('charge_level_id', $level->id)
                    ->orderBy('rule_no'),

                'deliveryRuleCharges' => fn($q) => $q->active()
                    ->where('charge_level_id', $level->id)
                    ->orderBy('rule_no'),
            ])
            ->get();

        // Remove delivery if pickup/drop
        if ($isBuyerPickup || $isSellerDropOff) {
            $chargeMasters = $chargeMasters->filter(
                fn($c) => $c->code !== ChargesEnum::DELIVERY_FEE->value
            );
        }

        if ($chargeMasters->isEmpty()) {
            throw new RuntimeException(__('messages.error_messages.missing_charge_level_pricing_config'), 422);
        }

        // --------------------------------------------------
        // TOTALS
        // --------------------------------------------------
        $totalQty = 0;
        $totalWeight = 0;

        foreach ($packages as $pkg) {
            $qty = (float)($pkg['order_qty'] ?? 0);
            $size = (float)($pkg['pack_size'] ?? 0);

            $totalQty += $qty;
            $totalWeight += ($qty * $size);
        }

        $charges = [];
        $totalCharge = 0;
        $totalTax = 0;

        // --------------------------------------------------
        // MAIN LOOP
        // --------------------------------------------------
        foreach ($chargeMasters as $charge) {

            $chargeAmount = 0;
            $ruleNo = null;
            $ruleDesc = null;
            $deliveryBreakdown = [];

            // ==================================================
            // MINIMUM / PLATFORM
            // ==================================================
            if ($charge->minimumRuleCharges->isNotEmpty()) {

                $rule = $this->applyMinimumOrderRule(
                    $charge->minimumRuleCharges,
                    $orderAmount,
                    $totalQty,
                    $totalWeight
                );

                if ($rule) {
                    $chargeAmount = $rule['amount'];
                    $ruleNo = $rule['rule_no'];
                    $ruleDesc = $rule['description'];
                }
            }

            // ==================================================
            // DELIVERY (PER PACKAGE + BREAKDOWN)
            // ==================================================
            if (
                $chargeAmount == 0 &&
                $charge->deliveryRuleCharges->isNotEmpty()
            ) {

                $packageChargeTotal = 0;

                foreach ($packages as $pkgIndex => $pkg) {

                    foreach ($charge->deliveryRuleCharges as $rule) {

                        if (
                            $rule->measure_unit === $pkg['pack_unit'] &&
                            (float)$rule->measure_value === (float)$pkg['pack_size'] &&
                            ($rule->pack_type_unit === $pkg['pack_type_unit'])
                        ) {

                            $lineAmount = $rule->charge_amount * (float)$pkg['order_qty'];

                            $packageChargeTotal += $lineAmount;

                            $deliveryBreakdown[] = [
                                'package_index' => $pkgIndex,
                                'charge_code' => $charge->code,
                                'charge_name' => $charge->name,
                                'rule_no' => $rule->rule_no,
                                'rule_desc' => $rule->description,
                                'measure_value' => $rule->measure_value,
                                'measure_unit' => $rule->measure_unit,
                                'pack_type_unit' => $rule->pack_type_unit,

                                //
                                'order_qty' => (float)$pkg['order_qty'],
                                'rate' => (float)$rule->charge_amount,
                                'amount' => round($lineAmount, 2),
                            ];

                            break;
                        }
                    }
                }

                if ($packageChargeTotal > 0) {
                    $chargeAmount = $packageChargeTotal;
                }
            }

            // ==================================================
            // FINAL PUSH
            // ==================================================
            if ($chargeAmount > 0) {

                $taxArr = $this->calculateTax($charge, $chargeAmount);

                $charges[] = [
                    'charge_code' => $charge->code,
                    'charge_name' => $charge->name,
                    'qty' => $totalQty,

                    'rule_type' => $ruleNo ? 'rule_based' : 'delivery',
                    'rule_no' => $ruleNo,
                    'rule_desc' => $ruleDesc,

                    'taxable_amount' => round($chargeAmount, 2),
                    'tax_amount' => round($taxArr['charge_tax'], 2),
                    'total_amount' => round($chargeAmount + $taxArr['charge_tax'], 2),

                    // 👇 IMPORTANT: breakdown added here
                    'breakdown' => !empty($deliveryBreakdown) ? $deliveryBreakdown : null,

                    'applicable_state_code'=> $charge->applicable_state_code,
                ];

                $totalCharge += $chargeAmount;
                $totalTax += $taxArr['charge_tax'];
            }
        }

        // --------------------------------------------------
        // RESPONSE
        // --------------------------------------------------
        return [
            'charges' => $charges,
            'charge_taxable' => round($totalCharge, 2),
            'charge_tax' => round($totalTax, 2),
            'total_charge_amount' => round($totalCharge + $totalTax, 2),
        ];
    }
    // =========================================================
    // MINIMUM ORDER RULE (PRICE / QTY / WEIGHT – ANY COMBO)
    // =========================================================

    protected function applyMinimumOrderRule(
        $rules,
        float $orderAmount,
        float $totalQty,
        float $totalWeight
    ): ?array {

        foreach ($rules as $rule) {

            $matched = true;

            if (!is_null($rule->min_order_price)) {
                $matched = $matched && $this->compare(
                    $orderAmount,
                    $rule->calc_condition,
                    $rule->min_order_price
                );
            }

            if (!is_null($rule->min_order_qty)) {
                $matched = $matched && $this->compare(
                    $totalQty,
                    $rule->calc_condition,
                    $rule->min_order_qty
                );
            }

            if (!is_null($rule->min_order_weight)) {
                $matched = $matched && $this->compare(
                    $totalWeight,
                    $rule->calc_condition,
                    $rule->min_order_weight
                );
            }

            if (!$matched) {
                continue;
            }

            $amount = $rule->calc_type === 'percentage'
                ? ($orderAmount * $rule->charge_amount / 100)
                : $rule->charge_amount;

            return [
                'rule_no' => $rule->rule_no,
                'description' => $rule->description,
                'amount' => $amount,
            ];
        }

        return null;
    }

    // =========================================================
    // DELIVERY RULE (PACK SIZE + UNIT + TYPE)
    // =========================================================

    protected function applyDeliveryRule($rules, array $pkg): ?array
    {
        foreach ($rules as $rule) {

            if (
                $rule->measure_unit === $pkg['pack_unit'] &&
                (float)$rule->measure_value === (float)$pkg['pack_size'] &&
                (!$rule->pack_type_unit || $rule->pack_type_unit === ($pkg['pack_type_unit'] ?? null))
            ) {
                return [
                    'rule_no' => $rule->rule_no,
                    'description' => $rule->description,
                    'amount' => $rule->charge_amount,
                ];
            }
        }

        return null;
    }

    // =========================================================
    // TAX
    // =========================================================
    protected function calculateTax(
        MstCharge $charge,
        float     $amount,
        string    $buyerStateCode = 'GJ',
        string    $supplierStateCode = 'GJ',
        bool      $isUnionTerritory = false
    ): array {

        if (!$charge->is_taxable || $amount <= 0) {
            return [
                'cgst' => 0,
                'sgst' => 0,
                'utgst' => 0,
                'igst' => 0,
                'charge_tax' => 0,
            ];
        }

        // 🟢 Intra-state
        if ($buyerStateCode === $supplierStateCode) {

            // UT case
            if ($isUnionTerritory && ($charge->utgst_percent ?? 0) > 0) {
                $cgst = round($amount * ($charge->cgst_percent ?? 0) / 100, 2);
                $utgst = round($amount * ($charge->utgst_percent ?? 0) / 100, 2);

                return [
                    'cgst' => $cgst,
                    'sgst' => 0,
                    'utgst' => $utgst,
                    'igst' => 0,
                    'charge_tax' => $cgst + $utgst,
                ];
            }

            // Normal State
            $cgst = round($amount * ($charge->cgst_percent ?? 0) / 100, 2);
            $sgst = round($amount * ($charge->sgst_percent ?? 0) / 100, 2);

            return [
                'cgst' => $cgst,
                'sgst' => $sgst,
                'utgst' => 0,
                'igst' => 0,
                'charge_tax' => $cgst + $sgst,
            ];
        }

        // 🔵 Inter-state → IGST
        $igst = round($amount * ($charge->igst_percent ?? 0) / 100, 2);

        return [
            'cgst' => 0,
            'sgst' => 0,
            'utgst' => 0,
            'igst' => $igst,
            'charge_tax' => $igst,
        ];
    }


    //    protected function calculateTax(MstCharge $charge, float $amount): float
    //    {
    //        if (!($charge->igst_percent ?? 0)) {
    //            return 0;
    //        }
    //
    //        return round(($amount * $charge->igst_percent) / 100, 2);
    //    }

    // =========================================================
    // COMPARE HELPER
    // =========================================================

    protected function compare(float $left, string $operator, float $right): bool
    {
        return match ($operator) {
            '<=' => $left <= $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '>' => $left > $right,
            '=' => $left == $right,
            default => false,
        };
    }


    //** */

    public function calculatePlatformFee(
        string $chargeLevelCode,
        float $orderAmount,
        array $packages
    ): array {

        $level = MstChargeLevel::active()
            ->where('code', $chargeLevelCode)
            ->firstOrFail();

        $charge = MstCharge::active()
            ->where('code', ChargesEnum::PLATFORM_FEE->value)
            ->with([
                'minimumRuleCharges' => fn($q) =>
                $q->active()
                    ->where('charge_level_id', $level->id)
                    ->orderBy('rule_no')
            ])
            ->first();

        if (!$charge || $charge->minimumRuleCharges->isEmpty()) {
            return [];
        }

        // Derived totals (same logic as main calculate)
        $totalQty = 0;
        $totalWeight = 0;

        foreach ($packages as $pkg) {
            $qty = (float)($pkg['order_qty'] ?? 0);
            $size = (float)($pkg['pack_size'] ?? 0);

            $totalQty += $qty;
            $totalWeight += ($qty * $size);
        }

        $rule = $this->applyMinimumOrderRule(
            $charge->minimumRuleCharges,
            $orderAmount,
            $totalQty,
            $totalWeight
        );

        if (!$rule) {
            return [];
        }

        $taxArr = $this->calculateTax($charge, $rule['amount']);

        return [
            'charge_code'   => $charge->code,
            'charge_name'   => $charge->name,
            'qty'           => 1, // Platform fee is generally a single charge per order
            'rule_no'       => $rule['rule_no'],
            'rule_desc'     => $rule['description'],
            'taxable_amount' => round($rule['amount'], 2),
            'tax_amount'    => round($taxArr['charge_tax'], 2),
            'total_amount'  => round($rule['amount'] + $taxArr['charge_tax'], 2),
        ];
    }

    public function calculateDeliveryCharges(
        string $chargeLevelCode,
        array $packages,
        bool $isBuyerPickup = false,
        bool $isSellerDropOff = false
    ): array {

        if ($isBuyerPickup || $isSellerDropOff) {
            return [];
        }

        $level = MstChargeLevel::active()
            ->where('code', $chargeLevelCode)
            ->firstOrFail();

        $charge = MstCharge::active()
            ->where('code', ChargesEnum::DELIVERY_FEE->value)
            ->with([
                'deliveryRuleCharges' => fn($q) =>
                $q->active()
                    ->where('charge_level_id', $level->id)
                    ->orderBy('rule_no')
            ])
            ->first();

        if (!$charge || $charge->deliveryRuleCharges->isEmpty()) {
            return [];
        }

        $charges = [];
        $totalCharge = 0;
        $totalTax = 0;

        foreach ($packages as $pkg) {

            $rule = $this->applyDeliveryRule(
                $charge->deliveryRuleCharges,
                $pkg
            );

            if (!$rule) {
                continue;
            }

            $lineAmount = $rule['amount'] * (float)$pkg['order_qty'];
            $taxArr = $this->calculateTax($charge, $lineAmount);

            $charges[] = [
                'charge_code'   => $charge->code,
                'charge_name'   => $charge->name,
                'qty'           => (float)$pkg['order_qty'],
                'rule_no'       => $rule['rule_no'],
                'rule_desc'     => $rule['description'],
                'taxable_amount' => round($lineAmount, 2),
                'tax_amount'    => round($taxArr['charge_tax'], 2),
                'total_amount'  => round($lineAmount + $taxArr['charge_tax'], 2),
            ];

            $totalCharge += $lineAmount;
            $totalTax += $taxArr['charge_tax'];
        }

        return [
            'charges' => $charges,
            'charge_taxable' => round($totalCharge, 2),
            'charge_tax' => round($totalTax, 2),
            'total_charge_amount' => round($totalCharge + $totalTax, 2),
        ];
    }
}
