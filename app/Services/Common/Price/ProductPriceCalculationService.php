<?php

namespace App\Services\Common\Price;

use App\Models\Master\Price\MstProductPrice;
use App\Models\Master\Price\MstProductPriceRule;

class ProductPriceCalculationService
{

    /**
     * Get latest base price of product
     */
    public function getProductPrice(int $productId): ?object
    {
        $price = MstProductPrice::where('product_id', $productId)
            ->latest('price_date')
            ->first(['price_date', 'price', 'max_price', 'min_price']);

        if (!$price) {
            return null;
        }

        return (object)[
            'price_date' => $price->price_date,
            'price' => (float)$price->price,
            'max_price' => (float)$price->max_price,
            'min_price' => (float)$price->min_price,
        ];
    }


    /**
     * Calculate final price based on pricing rule
     */
    public function calculateFinalPrice(
        int $productId,
        string $chargeLevelCode,
        string $userType,
        int $packSize,
        string $packUnit = 'kg'
    ): ?object {

        $productPrice = $this->getProductPrice($productId);

        if (!$productPrice || $productPrice->price <= 0) {
            return null;
        }

        $basePrice = $productPrice->price;

        $rule = MstProductPriceRule::whereHas('chargeLevel', function ($q) use ($chargeLevelCode) {
            $q->where('code', $chargeLevelCode);
        })
            ->where('user_type', $userType)
            ->where('pack_unit', $packUnit)
            ->active()
            ->first();

        $finalPrice = $basePrice;
        $ruleNo = null;

        if ($rule) {

            $column = $packSize . '_pkg';

            if (array_key_exists($column, $rule->getAttributes())) {

                $ruleValue = (float)$rule->$column;

                if ($ruleValue > 0) {

                    $ruleNo = $rule->rule_no;

                    if ($rule->calc_type === 'percentage') {
                        $finalPrice += ($basePrice * $ruleValue / 100);
                    } else {
                        $finalPrice += $ruleValue;
                    }
                }
            }
        }

        return (object)[
            'rule_no' => $ruleNo,
            'price_date' => $productPrice->price_date,

            'base_price' => round($basePrice, 2),
            'final_price' => round($finalPrice, 2),

            'max_price' => round($productPrice->max_price, 2),
            'min_price' => round($productPrice->min_price, 2),
        ];
    }
}
