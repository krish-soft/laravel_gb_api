<?php

namespace App\Http\Controllers\Api\v1\Admin\Master;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\MstUnit;
use Illuminate\Http\Request;

class MstUnitApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $mstUnits = MstUnit::all();
        return $this->successResponse(__('messages.success_messages.success_get'), $mstUnits);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:255|unique:mst_units,name',
            'unit' => 'required|string|max:50|unique:mst_units,unit',
        ]);

        $mstUnit = MstUnit::create($request->all());

        $user = $request->user();

        // Log activity
        logActivity(
            'unit_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstUnit), // SUBJECT TYPE (what was affected)
            $mstUnit->id,              // SUBJECT ID
            $mstUnit->unit,       // SUBJECT CODE (human readable)
            [
                'name' => $mstUnit->name,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstUnit $mstUnit)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstUnit);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstUnit $mstUnit)
    {
        //

        $request->validate([
            'name' => 'required|string|max:255|unique:mst_units,name,' . $mstUnit->id,
            'unit' => 'required|string|max:50|unique:mst_units,unit,' . $mstUnit->id,
        ]);

        $mstUnit->update($request->all());

        $user = $request->user();

        // Log activity
        logActivity(
            'unit_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstUnit), // SUBJECT TYPE (what was affected)
            $mstUnit->id,              // SUBJECT ID
            $mstUnit->unit,       // SUBJECT CODE (human readable)
            [
                'name' => $mstUnit->name,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstUnit $mstUnit)
    {
        //
        $user = request()->user();

        // Log activity
        logActivity(
            'unit_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstUnit), // SUBJECT TYPE (what was affected)
            $mstUnit->id,              // SUBJECT ID
            $mstUnit->unit,       // SUBJECT CODE (human readable)
            [
                'name' => $mstUnit->name,
            ]
        );

        $mstUnit->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
