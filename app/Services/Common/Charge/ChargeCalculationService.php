<?php

namespace App\Services\Common\Charge;

use App\Enum\Common\Charge\ChargesEnum;
use App\Models\Master\Charge\MstCharge;
use App\Models\Master\Charge\MstChargeLevel;
use RuntimeException;

class ChargeCalculationService
{
    public function calculate(
        string $chargeLevelCode,
        float  $orderAmount,
        array  $packages
    ): array {

        if ($orderAmount < 0) {
            throw new RuntimeException(__('messages.error_messages.invalid_order_amount'), 422);
        }

        $level = MstChargeLevel::active()
            ->where('code', $chargeLevelCode)
            ->first();

        if (!$level) {
            throw new RuntimeException(__('messages.error_messages.invalid_charge_level'), 422);
        }

        // --------------------------------------------------
        // DERIVED TOTALS FROM PACKAGES (SINGLE SOURCE)
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

        $chargeMasters = MstCharge::active()
            ->where('charge_level_id', $level->id)
            ->with([
                'minimumRuleCharges' => fn($q) => $q->active()->orderBy('rule_no'),
                'deliveryRuleCharges' => fn($q) => $q->active()->orderBy('rule_no'),
            ])
            ->get();

        foreach ($chargeMasters as $charge) {

            // ==================================================
            // PLATFORM / MINIMUM ORDER CHARGES
            // ==================================================
            if (
                $charge->code === ChargesEnum::PLATFORM_FEE->value &&
                $charge->minimumRuleCharges->isNotEmpty()
            ) {

                $rule = $this->applyMinimumOrderRule(
                    $charge->minimumRuleCharges,
                    $orderAmount,
                    $totalQty,
                    $totalWeight
                );

                if ($rule) {
                    $taxArr = $this->calculateTax($charge, $rule['amount']);

                    $charges[] = [
                        'charge_code' => $charge->code,
                        'charge_name' => $charge->name,
                        'rule_type' => 'minimum_order',
                        'rule_no' => $rule['rule_no'],
                        'rule_desc' => $rule['description'],
                        'taxable_amount' => round($rule['amount'], 2),
                        'tax_amount' => round($taxArr['charge_tax'], 2),
                        'total_amount' => round($rule['amount'] + $taxArr['charge_tax'], 2),
                    ];

                    $totalCharge += $rule['amount'];
                    $totalTax += $taxArr['charge_tax'];
                }
            }

            // ==================================================
            // DELIVERY CHARGES (PER PACKAGE)
            // ==================================================
            if (
                $charge->code === ChargesEnum::DELIVERY_FEE->value &&
                $charge->deliveryRuleCharges->isNotEmpty()
            ) {

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
                        'charge_code' => $charge->code,
                        'charge_name' => $charge->name,
                        'rule_type' => 'delivery',
                        'rule_no' => $rule['rule_no'],
                        'rule_desc' => $rule['description'],
                        'taxable_amount' => round($lineAmount, 2),
                        'tax_amount' => round($taxArr['tax_amount'], 2),
                        'total_amount' => round((float)$lineAmount + $taxArr['tax_amount'], 2),
                    ];

                    $totalCharge += $lineAmount;
                    $totalTax += $taxArr['tax_amount'];
                }
            }
        }

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
}
