<?php

namespace App\Http\Controllers\Api\v1\Admin\Master;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\MstPackType;
use Illuminate\Http\Request;

class MstPackTypeApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        $mstPackTypes = MstPackType::all();
        return $this->successResponse(__('messages.success_messages.success_get'), $mstPackTypes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:50|unique:mst_pack_types,name',
            'unit' => 'required|string|max:50|unique:mst_pack_types,unit',
        ]);

        $mstPackType = MstPackType::create($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'pack_type_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstPackType), // SUBJECT TYPE (what was affected)
            $mstPackType->id,              // SUBJECT ID
            $mstPackType->unit,       // SUBJECT CODE (human readable)
            [
                'name' => $mstPackType->name,
                'unit' => $mstPackType->unit,
            ]
        );
        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstPackType $mstPackType)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstPackType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstPackType $mstPackType)
    {
        //
        $request->validate([
            'name' => 'required|string|max:50|unique:mst_pack_types,name,' . $mstPackType->id,
            'unit' => 'required|string|max:50|unique:mst_pack_types,unit,' . $mstPackType->id,
        ]);

        $mstPackType->update($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'pack_type_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstPackType), // SUBJECT TYPE (what was affected)
            $mstPackType->id,              // SUBJECT ID
            $mstPackType->unit,       // SUBJECT CODE (human readable)
            [
                'name' => $mstPackType->name,
                'unit' => $mstPackType->unit,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstPackType $mstPackType)
    {
        //
        // Log activity
        $user = request()->user();
        logActivity(
            'pack_type_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstPackType), // SUBJECT TYPE (what was affected)
            $mstPackType->id,              // SUBJECT ID
            $mstPackType->unit,       // SUBJECT CODE (human readable)
            [
                'name' => $mstPackType->name,
                'unit' => $mstPackType->unit,
            ]
        );

        $mstPackType->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
