<?php

namespace App\Services\Seller\Product;

use App\Services\Common\Charge\ChargeCalculationService;
use RuntimeException;

class ProductListingChargePreviewService
{
    protected ChargeCalculationService $chargeService;

    public function __construct(ChargeCalculationService $chargeService)
    {
        $this->chargeService = $chargeService;
    }

    public function preview(array $packages, string $chargeLevelCode): array
    {
        if (empty($packages)) {
            throw new RuntimeException(
                __('messages.error_messages.no_packages_provided')
            );
        }

        $grossAmount = 0;
        $totalQty = 0;
        $totalWeight = 0;

        foreach ($packages as $pkg) {
            if (
                !isset(
                    $pkg['order_qty'],
                    $pkg['pack_price'],
                    $pkg['pack_size'],
                    $pkg['pack_unit']
                )
            ) {
                throw new RuntimeException(
                    __('messages.error_messages.invalid_package_data')
                );
            }

            $lineTotal = $pkg['order_qty'] * $pkg['pack_price'];
            $grossAmount += $lineTotal;

            $totalQty += $pkg['order_qty'];
            $totalWeight += ($pkg['order_qty'] * $pkg['pack_size']);
        }

        // 🔹 Calculate charges (platform / delivery / etc)
        $chargeSummary = $this->chargeService->calculate(
            $chargeLevelCode,
            $grossAmount,
            $packages
        );

        return [
            'gross_amount' => round($grossAmount, 2),

            'charges' => $chargeSummary['charges'],
            'charge_taxable' => $chargeSummary['charge_taxable'],
            'charge_tax' => $chargeSummary['charge_tax'],
            'total_charge_amount' => $chargeSummary['total_charge_amount'],

            // Seller payout = gross - charges
            'net_receivable' => round(
                $grossAmount - $chargeSummary['total_charge_amount'],
                2
            ),
        ];
    }
}
