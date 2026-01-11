<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Charge;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Charge\MstCharge;
use Illuminate\Http\Request;

class MstChargeApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $mstCharges = MstCharge::all();

        return $this->successResponse(__('messages.success_messages.success_get'), $mstCharges);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'code' => 'required|unique:mst_charges,code',
            'name' => 'required|string|max:100|unique:mst_charges,name',
            'description' => 'nullable|string|min:10|max:255',
        ]);

        $mstCharge = MstCharge::create($request->all());

        // Log activity
        logActivity(
            'charge_created',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstCharge), // SUBJECT TYPE (what was affected)
            $mstCharge->id,              // SUBJECT ID
            $mstCharge->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstCharge->name,
                'code' => $mstCharge->code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'));

        //
    }

    /**
     * Display the specified resource.
     */
    public function show(MstCharge $mstCharge)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstCharge);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstCharge $mstCharge)
    {
        //
        $request->validate([
            'code' => 'required|unique:mst_charges,code,' . $mstCharge->id,
            'name' => 'required|string|max:100|unique:mst_charges,name,' . $mstCharge->id,
            'description' => 'nullable|string|min:10|max:255',
        ]);

        $mstCharge->update($request->all());

        // Log activity
        logActivity(
            'charge_updated',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstCharge), // SUBJECT TYPE (what was affected)
            $mstCharge->id,              // SUBJECT ID
            $mstCharge->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstCharge->name,
                'code' => $mstCharge->code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstCharge $mstCharge)
    {
        //
        return $this->showErrorMessage(
            __('messages.error_messages.cannot_delete_used_in_transactions'),
            409
        );

        // Log activity
        logActivity(
            'charge_deleted',        // EVENT
            request()->user(),                 // ACTOR (who did it)
            get_class($mstCharge), // SUBJECT TYPE (what was affected)
            $mstCharge->id,              // SUBJECT ID
            $mstCharge->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstCharge->name,
                'code' => $mstCharge->code,
            ]
        );

        $mstCharge->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }
}
