<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Product;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Product\MstProduct;
use Illuminate\Http\Request;

class MstProductApiController extends ApiResponseWithAdminAuthController
{

    protected static $FILE_PATH = '/master/products';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $productQuery = MstProduct::with('category')->latest();
        if ($request->has('is_active')) {
            $productQuery->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $mstProducts = $productQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $mstProducts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:150',
            'category_id' => 'required|exists:mst_product_categories,id',
            'description' => 'required|string|min:3|max:255',
            'hsn' => 'required|string|max:15',

            'upc' => 'nullable|string|max:20',
            'sku' => 'nullable|string|max:30',
            'grade' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'origin' => 'nullable|string|max:50',
            'picture' => 'nullable|image|max:2048|mimes:jpeg,png,jpg',
        ]);

        // Check Already Exist any of key except name & descriptions
        $existingProduct = MstProduct::where(function ($query) use ($request) {
            if ($request->filled('upc')) {
                $query->orWhere('upc', $request->upc);
            }
            if ($request->filled('hsn')) {
                $query->orWhere('hsn', $request->hsn);
            }
            if ($request->filled('sku')) {
                $query->orWhere('sku', $request->sku);
            }
        })->first();

        if ($existingProduct) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }

        $filePath = null;
        if ($request->hasFile('picture')) {
            $filePath =  uploadPublicFile(
                $request->file('picture'),
                self::$FILE_PATH,
            );
        }

        $mstProduct = MstProduct::create($request->merge([
            'picture' => $filePath,
            'is_active' => true,
        ])->all());


        // Log activity
        logActivity(
            'product_created',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstProduct), // SUBJECT TYPE (what was affected)
            $mstProduct->id,              // SUBJECT ID
            $mstProduct->name,       // SUBJECT CODE (human readable)
            [
                'name' => $mstProduct->name,
                'product_code' => $mstProduct->product_code,
            ]
        );


        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstProduct $mstProduct)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstProduct);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstProduct $mstProduct)
    {
        //

        $request->validate([
            'name' => 'required|string|max:150',
            'category_id' => 'required|exists:mst_product_categories,id',
            'description' => 'required|string|min:3|max:255',
            'hsn' => 'required|string|max:15',

            'upc' => 'nullable|string|max:20',
            'sku' => 'nullable|string|max:30',
            'grade' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'origin' => 'nullable|string|max:50',
            'picture' => 'nullable|image|max:2048|mimes:jpeg,png,jpg',
        ]);

        // Check Already Exist any of key except name & descriptions
        // $existingProduct = MstProduct::where('id', '!=', $mstProduct->id)
        //     ->where(function ($query) use ($request) {
        //         if ($request->filled('upc')) {
        //             $query->orWhere('upc', $request->upc);
        //         }
        //         if ($request->filled('hsn')) {
        //             $query->orWhere('hsn', $request->hsn);
        //         }
        //         if ($request->filled('sku')) {
        //             $query->orWhere('sku', $request->sku);
        //         }
        //     })
        //     ->first();

        // if ($existingProduct) {
        //     return $this->showErrorMessage(
        //         __('messages.error_messages.already_exists'),
        //         409
        //     );
        // }


        $filePath = $mstProduct->picture;
        if ($request->hasFile('picture')) {
            $filePath =  uploadPublicFile(
                $request->file('picture'),
                self::$FILE_PATH,
                $mstProduct->picture,
            );
        }

        $mstProduct->update($request->merge([
            'picture' => $filePath,
        ])->all());

        // Log activity
        logActivity(
            'product_updated',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstProduct), // SUBJECT TYPE (what was affected)
            $mstProduct->id,              // SUBJECT ID
            $mstProduct->name,       // SUBJECT CODE (human readable)
            [
                'name' => $mstProduct->name,
                'product_code' => $mstProduct->product_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstProduct $mstProduct)
    {
        //

        if ($mstProduct->variants()->exists() || $mstProduct->packages()->exists() || $mstProduct->farmerListingItems()->exists()) {
            return $this->showErrorMessage(
                __('messages.error_messages.cannot_delete_used_in_transactions'),
                409
            );
        }

        // Log activity
        $user = request()->user();
        logActivity(
            'product_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstProduct), // SUBJECT TYPE (what was affected)
            $mstProduct->id,              // SUBJECT ID
            $mstProduct->name,       // SUBJECT CODE (human readable)
            [
                'name' => $mstProduct->name,
                'product_code' => $mstProduct->product_code,
            ]
        );

        $mstProduct->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
