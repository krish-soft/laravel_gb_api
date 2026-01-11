<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Depot;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Models\Address;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Depot\MstZone;
use Illuminate\Http\Request;

class MstDepotApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $mstDepots = MstDepot::all();
        return $this->successResponse(__('messages.success_messages.success_get'), $mstDepots);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'zone_id' => 'required|exists:mst_zones,id',
            'depot_name' => 'required|string|max:255|unique:mst_depots,depot_name',
            'depot_code' => 'required|string|max:50|unique:mst_depots,depot_code',
            'contact_name' => 'required|string|max:100',

            'buyer_cutoff_time' => 'required|date_format:H:i',
            'seller_cutoff_time' => 'required|date_format:H:i',

            'max_capacity_kg' => 'required|numeric|min:1',
            'current_load_kg' => 'required|numeric|min:0',

        ]);

        $mstDepot = MstDepot::create($request->all());
        $mstZone  = MstZone::firstOrFail($request->zone_id);

        $user = $request->user();
        // Log activity
        logActivity(
            'depot_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->depot_code,       // SUBJECT CODE (human readable)
            [
                'zone_code' => $mstZone->zone_code,
                'depot_name' => $mstDepot->depot_name,
                'depot_code' => $mstDepot->depot_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstDepot $mstDepot)
    {
        //
        return $this->successResponse(__('messages.success_messages.success_get'), $mstDepot);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstDepot $mstDepot)
    {
        //

        $request->validate([
            'zone_id' => 'required|exists:mst_zones,id',
            'depot_name' => 'required|string|max:255|unique:mst_depots,depot_name,' . $mstDepot->id,
            'depot_code' => 'required|string|max:50|unique:mst_depots,depot_code,' . $mstDepot->id,
            'contact_name' => 'required|string|max:100',

            'buyer_cutoff_time' => 'required|date_format:H:i',
            'seller_cutoff_time' => 'required|date_format:H:i',

            'max_capacity_kg' => 'required|numeric|min:1',
            'current_load_kg' => 'required|numeric|min:0',

        ]);

        $mstDepot->update($request->all());

        $mstZone  = MstZone::firstOrFail($request->zone_id);
        $user = $request->user();
        // Log activity
        logActivity(
            'depot_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->depot_code,       // SUBJECT CODE (human readable)
            [
                'zone_code' => $mstZone->zone_code,
                'depot_name' => $mstDepot->depot_name,
                'depot_code' => $mstDepot->depot_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstDepot $mstDepot)
    {
        //

        // We can not delete depot once added

        $user = request()->user();
        // Log activity
        logActivity(
            'depot_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->depot_code,       // SUBJECT CODE (human readable)
            [
                'depot_name' => $mstDepot->depot_name,
                'depot_code' => $mstDepot->depot_code,
            ]
        );
        $mstDepot->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }


    // Add Address To Depot
    public function addAddress(AddressRequest $request, MstDepot $mstDepot)
    {
        //

        // Already Exist then give error
        if (!empty($mstDepot->addr_code)) {
            return $this->errorResponse(__('messages.error_messages.address_exists'), 422);
        }

        $address = Address::create($request->all());
        $mstDepot->addr_code = $address->addr_code;

        $mstDepot->save();

        $user = $request->user();
        // Log activity
        logActivity(
            'depot_address_added',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->depot_code,       // SUBJECT CODE (human readable)
            [
                'depot_name' => $mstDepot->depot_name,
                'depot_code' => $mstDepot->depot_code,
                'addr_code' => $address->addr_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    // update Address of Depot
    public function updateAddress(AddressRequest $request, MstDepot $mstDepot)
    {
        //

        // Check same Address or not
        $address = Address::where('addr_code', $mstDepot->addr_code)->firstOrFail();

        $address->update($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'depot_address_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->depot_code,       // SUBJECT CODE (human readable)
            [
                'depot_name' => $mstDepot->depot_name,
                'depot_code' => $mstDepot->depot_code,
                'addr_code' => $address->addr_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }
}
