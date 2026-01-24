<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Product;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Product\MstProductCategory;
use Illuminate\Http\Request;

class MstProductCategoryApiController extends ApiResponseWithAdminAuthController
{

    protected $FILE_PATH = '/master/products/categories';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $productCategoryQuery = MstProductCategory::latest();
        if ($request->has('is_active')) {
            $productCategoryQuery->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $mstProductCategories = $productCategoryQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $mstProductCategories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'hsn_chapter' => 'required|string|max:10',
            // 'picture' => 'nullable|image|max:2048|mimes:jpeg,png,jpg', // picture
        ]);
        // exist check
        $existingCategory = MstProductCategory::where('name', $request->name)->orWhere('hsn_chapter', $request->hsn_chapter)->first();
        if ($existingCategory) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }


        $mstProductCategory = MstProductCategory::create($request->all());

        // Log activity
        logActivity(
            'product_category_created',        // EVENT
            $request->user(),                       // ACTOR
            MstProductCategory::class,         // SUBJECT TYPE
            $mstProductCategory->id,           // SUBJECT ID
            $mstProductCategory->category_code, // SUBJECT CODE  [
            [                                       // META
                'name' => $mstProductCategory->name,
                'hsn_chapter' => $mstProductCategory->hsn_chapter,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstProductCategory $mstProductCategory)
    {
        //
        return $this->successResponse(__('messages.success_messages.success_get'), $mstProductCategory);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstProductCategory $mstProductCategory)
    {
        //
        $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'hsn_chapter' => 'required|string|max:10',
            // 'picture' => 'nullable|image|max:2048|mimes:jpeg,png,jpg',
        ]);

        // Check same only hsn not exist
        $existingCategory = MstProductCategory::where('hsn_chapter', $request->hsn_chapter)->where('id', '!=', $mstProductCategory->id)->first();

        if ($existingCategory) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }

        $mstProductCategory->update($request->all());

        // Log activity
        logActivity(
            'product_category_updated',        // EVENT
            $request->user(),                       // ACTOR
            MstProductCategory::class,         // SUBJECT TYPE
            $mstProductCategory->id,           // SUBJECT ID
            $mstProductCategory->category_code, // SUBJECT CODE
            [
                'name' => $mstProductCategory->name,
                'hsn_chapter' => $mstProductCategory->hsn_chapter,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstProductCategory $mstProductCategory)
    {
        //

        if ($mstProductCategory->products()->exists()) {
            return $this->showErrorMessage(
                __('messages.error_messages.cannot_delete_used_in_transactions'),
                409
            );
        }

        // Log activity
        logActivity(
            'product_category_deleted',        // EVENT
            request()->user(),                       // ACTOR
            MstProductCategory::class,         // SUBJECT TYPE
            $mstProductCategory->id,           // SUBJECT ID
            $mstProductCategory->category_code, // SUBJECT CODE
            [
                'name' => $mstProductCategory->name,
                'hsn_chapter' => $mstProductCategory->hsn_chapter,
            ]
        );

        $mstProductCategory->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
