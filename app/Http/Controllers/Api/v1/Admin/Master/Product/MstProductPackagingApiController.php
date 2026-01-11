<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Product;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Product\MstProductPackaging;
use Illuminate\Http\Request;

class MstProductPackagingApiController extends ApiResponseWithAdminAuthController
{

    protected $FILE_PATH = '/master/products/packagings';


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $mstProductPackagings = MstProductPackaging::all();

        return $this->successResponse(__('messages.success_messages.success_get'), $mstProductPackagings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'product_id' => 'required|exists:mst_products,id',

            'pack_size' => 'required|numeric',
            'pack_unit' => 'required|string|exists:mst_units,unit',
            'pack_type_unit' => 'required|string|exists:mst_pack_types,unit',

            // Optional fields
            'length_in' => 'nullable|numeric|min:0',
            'width_in' => 'nullable|numeric|min:0',
            'height_in' => 'nullable|numeric|min:0',
            'weight_kg' => 'nullable|numeric|min:0',
            'volume_cu_in' => 'nullable|numeric|min:0',
        ]);
        // Exist check can be added here if needed
        $existingPackaging = MstProductPackaging::where('product_id', $request->product_id)
            ->where('pack_size', $request->pack_size)
            ->where('pack_unit', $request->pack_unit)
            ->where('pack_type_unit', $request->pack_type_unit)
            ->first();

        if ($existingPackaging) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }

        $mstProductPackaging = MstProductPackaging::create($request->all());

        // Log activity
        logActivity(
            'product_packaging_created',        // EVENT
            $request->user(),                       // ACTOR
            MstProductPackaging::class,         // SUBJECT TYPE
            $mstProductPackaging->id,           // SUBJECT ID
            null, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductPackaging->product_id,
                'pack_size' => $mstProductPackaging->pack_size,
                'pack_unit' => $mstProductPackaging->pack_unit,
                'pack_type_unit' => $mstProductPackaging->pack_type_unit,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstProductPackaging $mstProductPackaging)
    {
        //
        return $this->successResponse(__('messages.success_messages.success_get'), $mstProductPackaging);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstProductPackaging $mstProductPackaging)
    {
        //
        $request->validate([
            'product_id' => 'required|exists:mst_products,id',

            'pack_size' => 'required|numeric',
            'pack_unit' => 'required|string|exists:mst_units,unit',
            'pack_type_unit' => 'required|string|exists:mst_pack_types,unit',

            // Optional fields
            'length_in' => 'nullable|numeric|min:0',
            'width_in' => 'nullable|numeric|min:0',
            'height_in' => 'nullable|numeric|min:0',
            'weight_kg' => 'nullable|numeric|min:0',
            'volume_cu_in' => 'nullable|numeric|min:0',
        ]);

        // Check for existing packaging with same details (excluding current record)
        $existingPackaging = MstProductPackaging::where('product_id', $request->product_id)
            ->where('pack_size', $request->pack_size)
            ->where('pack_unit', $request->pack_unit)
            ->where('pack_type_unit', $request->pack_type_unit)
            ->where('id', '!=', $mstProductPackaging->id)
            ->first();

        if ($existingPackaging) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }


        $mstProductPackaging->update($request->all());

        // Log activity
        logActivity(
            'product_packaging_updated',        // EVENT
            $request->user(),                       // ACTOR
            MstProductPackaging::class,         // SUBJECT TYPE
            $mstProductPackaging->id,           // SUBJECT ID
            null, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductPackaging->product_id,
                'pack_size' => $mstProductPackaging->pack_size,
                'pack_unit' => $mstProductPackaging->pack_unit,
                'pack_type_unit' => $mstProductPackaging->pack_type_unit,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstProductPackaging $mstProductPackaging)
    {
        //

        // Log activity
        logActivity(
            'product_packaging_deleted',        // EVENT
            request()->user(),                       // ACTOR
            MstProductPackaging::class,         // SUBJECT TYPE
            $mstProductPackaging->id,           // SUBJECT ID
            null, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductPackaging->product_id,
                'pack_size' => $mstProductPackaging->pack_size,
                'pack_unit' => $mstProductPackaging->pack_unit,
                'pack_type_unit' => $mstProductPackaging->pack_type_unit,
            ]
        );

        $mstProductPackaging->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
