<?php

namespace App\Http\Controllers\Api\v1\User\Buyer\Demand;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Master\Product\MstProduct;
use Illuminate\Http\Request;

class BuyerDemandProductListingApiController extends ApiResponseWithAuthController
{
    public function getBuyerProductSummary(Request $request)
    {
        $limit  = min((int)$request->get('limit', 50), 100);
        $offset = (int)$request->get('offset', 0);
        $sortBy = $request->get('sort_by', 'product_name');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = MstProduct::with('category:id,name,category_code')
            ->where('is_active', true);

        // DB sorting (optimized)
        // if ($sortBy === 'product_name') {
        //     $query->orderBy('name', $sortDir);
        // }

        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $data);
    }

    public function getBuyerProductPackages(Request $request, $productId)
    {
        $product = MstProduct::with([
            'category:id,name,category_code',
            'packagings' => function ($q) {
                $q->select('id', 'product_id', 'pack_size', 'pack_unit', 'pack_type_unit')
                    ->where('is_active', true);
            }
        ])
            ->where('is_active', true)
            ->findOrFail($productId);

        // TODO: Pack Price pending to add here later from Pricing Modules
        foreach ($product->packagings as $packaging) {
            $packaging->prices = [
                'date_price' => null, // Placeholder for future price
                'market_price' => null, // Placeholder for future price
                'pack_sell_price' => null,  // Placeholder for future price
            ];
        }

        $data = [
            'product' => $product,
            'packages' => $product->packagings
        ];

        return $this->successResponse(__('messages.success_messages.success_get'), $data);
    }
}
