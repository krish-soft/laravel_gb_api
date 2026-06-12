<?php

namespace App\Http\Controllers\Api\v1\User\Seller\Product;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Seller\Product\ProductListing;
use App\Services\Seller\Product\ProductListingChargePreviewService;
use App\Services\Seller\Product\ProductListingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProductListingApiController extends ApiResponseWithAuthController
{
    protected ProductListingService $service;

    public function __construct(ProductListingService $service)
    {
        $this->service = $service;
    }

    public function getProductListing(Request $request)
    {
        if (! $request->user()->isSeller()) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        }

        $request->validate([
            'start_date' => 'nullable|date|required_with:end_date|before_or_equal:end_date',
            'end_date' => 'nullable|date|required_with:start_date|after_or_equal:start_date',
        ]);

        // Log::info("API_QUERY :" . json_encode([
        //     'endpoint' => 'getProductListing',
        //     'user_id' => $request->user()->id,
        //     'query_params' => $request->all(),
        // ], JSON_PRETTY_PRINT));

        $productListingQuery = ProductListing::latest()->with([
            'fulfillmentLocation',
            'listingItems.product',
            'listingItems.productVariant',
            'listingItems.listingPackages',
            'shipmentPackages',
        ])->where('seller_id', $request->user()->id);

        if ($request->has('is_active')) {
            $productListingQuery->where('is_active', $request->input('is_active'));
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();

            $productListingQuery->whereBetween('listing_date', [$startDate, $endDate]);
        } else {
            // Log::info("Default");
            $productListingQuery
                ->where('is_active', true)
                ->where('is_expired', false);
        }

        $productListings = $productListingQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $productListings, 200);
    }

    public function previewWithCharges(Request $request, ProductListingChargePreviewService $service)
    {
        try {
            $data = $request->validate([
                'is_seller_dropoff' => 'required|boolean',

                'packages' => 'required|array|min:1',

                'packages.*.order_qty' => 'required|numeric|min:1',
                'packages.*.pack_size' => 'required|numeric|min:0.01',
                'packages.*.pack_unit' => 'required|string|max:20',
                'packages.*.pack_type_unit' => 'nullable|string|max:50',
                'packages.*.pack_price' => 'required|numeric|min:0',
            ]);

            $chargeLevelcode = $request->user()->charge_level_code;
            $isSellerDropOff = $request->is_seller_dropoff;

            if (empty($chargeLevelcode)) {
                throw new RuntimeException(
                    __('messages.error_messages.invalid_charge_level'),
                    422
                );
            }

            $result = $service->preview(
                $data['packages'],
                $chargeLevelcode,
                $isSellerDropOff
            );

            return $this->successResponse(
                $result,
                __('messages.success_messages.success_preview')
            );
        } catch (\Exception $e) {
            return $this->showErrorMessage($e->getMessage(), $e->getCode());
        }
    }

    public function createListing(Request $request)
    {

        try {
            $data = $request->validate($this->createRules());

            $listing = $this->service->createListing(
                $request->user(),
                $data
            );

            return $this->showSuccessMessage(__('messages.success_messages.success_create'), 200);
        } catch (Exception $e) {
            return $this->showErrorMessage($e->getMessage(), $e->getCode());
        }
    }

    public function updatePackage(Request $request, int $packageId)
    {
        try {
            $data = $request->validate($this->updatePackageRules());

            $package = $this->service->updatePackage(
                $request->user(),
                $packageId,
                $data
            );

            return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
        } catch (Exception $e) {
            return $this->showErrorMessage($e->getMessage(), $e->getCode());
        }
    }

    /* =========================================================
       ================= CANCEL PACKAGE ========================
       ========================================================= */

    public function deletePackage(Request $request, int $packageId)
    {
        try {
            $data = $request->validate([
                'reason' => 'required|string|max:255',
            ]);

            $this->service->deletePackage(
                $request->user(),
                $packageId,
                $data['reason']
            );

            return $this->showSuccessMessage(__('messages.success_messages.success_cancel'), 200);
        } catch (Exception $e) {
            return $this->showErrorMessage($e->getMessage(), $e->getCode());
        }
    }

    /* =========================================================
       ================= CANCEL LISTING ========================
       ========================================================= */

    public function cancelListing(Request $request, int $listingId)
    {
        try {
            $data = $request->validate([
                'reason' => 'required|string|max:255',
            ]);

            $this->service->cancelListing(
                $request->user(),
                $listingId,
                $data['reason']
            );

            return $this->showSuccessMessage(__('messages.success_messages.success_cancel'), 200);
        } catch (RuntimeException $e) {
            return $this->showErrorMessage($e->getMessage(), $e->getCode());
        }
    }

    /* =========================================================
       ================= VALIDATION RULES ======================
       ========================================================= */

    protected function createRules(): array
    {
        return [

            // 'fulfillment_location_id' => 'required|integer|exists:fulfillment_locations,id',
            'fulfillment_location_id' => Rule::exists('fulfillment_locations', 'id')->where('user_id', request()->user()->id),

            'is_sell_to_market' => 'required|boolean',
            'is_seller_dropoff' => 'required|boolean',
            'is_buyer_pickup' => 'nullable|boolean',

            'productListingItems' => 'required|array|min:1',
            'productListingItems.*.is_organic' => 'required|boolean', // organic
            'productListingItems.*.product_id' => 'required|integer|exists:mst_products,id',
            'productListingItems.*.product_variant_id' => 'nullable|integer|exists:mst_product_variants,id',

            'productListingItems.*.productListingPackages' => 'required|array|min:1',
            'productListingItems.*.productListingPackages.*.qty' => 'required|numeric|min:0.01',
            'productListingItems.*.productListingPackages.*.pack_size' => 'required|numeric|min:0.01',
            'productListingItems.*.productListingPackages.*.pack_unit' => 'required|string|max:20',
            'productListingItems.*.productListingPackages.*.pack_type_unit' => 'nullable|string|max:50',
            'productListingItems.*.productListingPackages.*.pack_price' => 'required|numeric|min:0',
            'productListingItems.*.productListingPackages.*.per_kg_price' => 'required|numeric|min:0',
            'productListingItems.*.productListingPackages.*.discount_amount' => 'nullable|numeric|min:0',
            'productListingItems.*.productListingPackages.*.discount_type' => 'nullable|in:flat,percentage',
            'productListingItems.*.productListingPackages.*.quality_grade' => 'required|string|max:20',

            // optional images for each package
            'productListingItems.*.productListingPackages.*.picture' => 'nullable|image|max:2048',
            'productListingItems.*.productListingPackages.*.picture2' => 'nullable|image|max:2048',
            'productListingItems.*.productListingPackages.*.picture3' => 'nullable|image|max:2048',
        ];
    }

    protected function updatePackageRules(): array
    {
        return [
            'qty' => 'sometimes|numeric|min:0.01',
            'pack_price' => 'sometimes|numeric|min:0',
            'per_kg_price' => 'sometimes|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:flat,percentage',
            'quality_grade' => 'sometimes|string|max:20',

            // optional images for each package
            'picture' => 'nullable|image|max:2048',
            'picture2' => 'nullable|image|max:2048',
            'picture3' => 'nullable|image|max:2048',
        ];
    }
}
