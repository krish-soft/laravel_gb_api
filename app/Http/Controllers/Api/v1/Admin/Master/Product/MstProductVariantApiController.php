<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Product;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Product\MstProductVariant;
use Illuminate\Http\Request;

class MstProductVariantApiController extends ApiResponseWithAdminAuthController
{

    protected $FILE_PATH = '/master/products/variants';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //

        $mstProductVariantsQuery = MstProductVariant::with('product')->latest();
        if ($request->has('is_active')) {
            $mstProductVariantsQuery->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $mstProductVariants = $mstProductVariantsQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $mstProductVariants);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'picture' => 'nullable|image|max:2048|mimes:jpeg,png,jpg',
            'product_id' => 'required|exists:mst_products,id',
            'name' => 'required|string|max:100',
            'description' => 'required|string|min:5|max:255',
            'hsn' => 'nullable|string|max:20',
            'upc' => 'nullable|string|max:20',

            'sku' => 'nullable|string|max:20',
        ]);

        // Exist check
        $existingVariant = MstProductVariant::where('product_id', $request->product_id)
            ->where('hsn', $request->hsn)
            ->first();

        if ($existingVariant) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }



        $filePath = null;
        // Create the product variant
        if ($request->hasFile('picture')) {
            $filePath =  uploadPublicFile(
                $request->file('picture'),
                $this->FILE_PATH,
            );
        }

        $mstProductVariant = MstProductVariant::create(
            $request->merge([
                'picture' => $filePath,
            ])->all()
        );

        // Log activity
        logActivity(
            'product_variant_created',        // EVENT
            $request->user(),                       // ACTOR
            MstProductVariant::class,         // SUBJECT TYPE
            $mstProductVariant->id,           // SUBJECT ID
            $mstProductVariant->variant_code, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductVariant->product_id,
                'variant_name' => $mstProductVariant->name,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_create'), $mstProductVariant, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstProductVariant $mstProductVariant)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstProductVariant);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstProductVariant $mstProductVariant)
    {
        //
        $request->validate([
            'picture' => 'nullable|image|max:2048|mimes:jpeg,png,jpg',
            'product_id' => 'required|exists:mst_products,id',
            'name' => 'required|string|max:100',
            'description' => 'required|string|min:5|max:255',
            'hsn' => 'nullable|string|max:20',
            'upc' => 'nullable|string|max:20',
            'sku' => 'nullable|string|max:20',
        ]);

        // Exist check
        $existingVariant = MstProductVariant::where('product_id', $request->product_id)
            ->where('hsn', $request->hsn)
            ->where('id', '!=', $mstProductVariant->id)
            ->first();

        if ($existingVariant) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }

        $filePath = $mstProductVariant->picture;
        // Update the product variant
        if ($request->hasFile('picture')) {
            $filePath =  uploadPublicFile(
                $request->file('picture'),
                $this->FILE_PATH,
                $filePath // old file path to delete the old file
            );
        }

        $mstProductVariant->update(
            $request->merge([
                'picture' => $filePath,
            ])->all()
        );

        // Log activity
        logActivity(
            'product_variant_updated',        // EVENT
            $request->user(),                       // ACTOR
            MstProductVariant::class,         // SUBJECT TYPE
            $mstProductVariant->id,           // SUBJECT ID
            $mstProductVariant->variant_code, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductVariant->product_id,
                'variant_name' => $mstProductVariant->name,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_update'), $mstProductVariant);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstProductVariant $mstProductVariant)
    {
        //

        if ($mstProductVariant->farmerListingItems()->exists()) {
            return $this->showErrorMessage(
                __('messages.error_messages.cannot_delete_used_in_transactions'),
                409
            );
        }

        // Log activity
        logActivity(
            'product_variant_deleted',        // EVENT
            request()->user(),                       // ACTOR
            MstProductVariant::class,         // SUBJECT TYPE
            $mstProductVariant->id,           // SUBJECT ID 
            $mstProductVariant->variant_code, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductVariant->product_id,
                'variant_name' => $mstProductVariant->name,
            ]
        );

        $mstProductVariant->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
