<?php

namespace App\Http\Controllers\Api\v1\Admin\Seller\Product;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\User;
use App\Services\Seller\Product\ProductListingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminProductListingApiController extends ApiResponseWithAuthController
{
    protected ProductListingService $service;

    public function __construct(ProductListingService $service)
    {
        $this->service = $service;
    }


    public function index(Request $request)
    {
        
    }


    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->createRules());

            $subjectUser = User::findOrFail($data['user_id']);

            $listing = $this->service->createListing(
                $subjectUser,
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

            $subjectUser = User::findOrFail($data['user_id']);

            $package = $this->service->updatePackage(
                $subjectUser,
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
            'user_id' => 'required|integer|exists:users,id',
            'fulfillment_location_id' => 'required|integer|exists:fulfillment_locations,id',

            'is_sell_to_market' => 'boolean',
            'is_seller_delivery' => 'boolean',
            'is_buyer_pickup' => 'boolean',

            'productListingItems' => 'required|array|min:1',

            'productListingItems.*.product_id' =>
            'required|integer|exists:mst_products,id',

            'productListingItems.*.product_variant_id' =>
            'nullable|integer|exists:mst_product_variants,id',

            'productListingItems.*.productListingPackages' =>
            'required|array|min:1',

            'productListingItems.*.productListingPackages.*.qty' =>
            'required|numeric|min:0.01',

            'productListingItems.*.productListingPackages.*.pack_size' =>
            'required|numeric|min:0.01',

            'productListingItems.*.productListingPackages.*.pack_unit' =>
            'required|string|max:20',

            'productListingItems.*.productListingPackages.*.pack_type_unit' =>
            'nullable|string|max:50',

            'productListingItems.*.productListingPackages.*.pack_price' =>
            'required|numeric|min:0',

            'productListingItems.*.productListingPackages.*.per_kg_price' =>
            'required|numeric|min:0',

            'productListingItems.*.productListingPackages.*.discount_amount' =>
            'nullable|numeric|min:0',

            'productListingItems.*.productListingPackages.*.discount_type' =>
            'nullable|in:flat,percentage',
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
        ];
    }
}
