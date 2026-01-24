<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Depot;

use App\Enum\AddressTypeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Requests\AddressRequest;
use App\Models\Common\Address;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Depot\MstZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MstDepotApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $mstDepots = MstDepot::with('zone', 'zone.state', 'address')->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $mstDepots);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'zone_id' => 'nullable|exists:mst_zones,id',
            'name' => 'required|string|max:255|unique:mst_depots,name',
            'code' => 'nullable|string|max:50|unique:mst_depots,code',
            'contact_name' => 'required|string|max:100',

            'buyer_cutoff_time' => 'required|date_format:H:i',
            'seller_cutoff_time' => 'required|date_format:H:i',

            'max_capacity_kg' => 'required|numeric|min:1',
            'current_load_kg' => 'required|numeric|min:0',

        ]);

        $mstDepot = MstDepot::create($request->all());
        $mstZone = MstZone::findOrFail($request->zone_id);

        $user = $request->user();
        // Log activity
        logActivity(
            'depot_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->code,       // SUBJECT CODE (human readable)
            [
                'zone_code' => $mstZone->zone_code,
                'depot_name' => $mstDepot->name,
                'depot_code' => $mstDepot->code,
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

        $mstDepot = MstDepot::with('zone', 'zone.state', 'address')->findOrFail($mstDepot->id);

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
            'name' => 'required|string|max:255|unique:mst_depots,name,' . $mstDepot->id,
            // 'code' => 'required|string|max:50|unique:mst_depots,code,' . $mstDepot->id,
            'contact_name' => 'required|string|max:100',

            'buyer_cutoff_time' => 'required|date_format:H:i',
            'seller_cutoff_time' => 'required|date_format:H:i',

            'max_capacity_kg' => 'required|numeric|min:1',
            'current_load_kg' => 'required|numeric|min:0',

        ]);

        $mstDepot->update($request->all());

        $mstZone = MstZone::findOrFail($request->zone_id);
        $user = $request->user();
        // Log activity
        logActivity(
            'depot_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->code,       // SUBJECT CODE (human readable)
            [
                'zone_code' => $mstZone->zone_code,
                'depot_name' => $mstDepot->name,
                'depot_code' => $mstDepot->code,
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
        // Not to delete depot anyhow 
        return $this->errorResponse(__('messages.error_messages.main_resource_cannot_delete'), 403);

        // We can not delete depot once added

        $user = request()->user();
        // Log activity
        logActivity(
            'depot_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstDepot), // SUBJECT TYPE (what was affected)
            $mstDepot->id,              // SUBJECT ID
            $mstDepot->code,       // SUBJECT CODE (human readable)
            [
                'depot_name' => $mstDepot->name,
                'depot_code' => $mstDepot->code,
            ]
        );
        $mstDepot->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }


    // Add Address To Depot
    public function saveAddress(AddressRequest $request, MstDepot $depot)
    {
        // Log::info('Saving address for depot', ['depot_id' => $depot->id]);

        $data = $request->validated();

        $data['addr_type'] = AddressTypeEnum::DEPOT->value;


        if ($depot->addr_code) {
            // UPDATE
            $address = Address::where('addr_code', $depot->addr_code)
                ->firstOrFail();

            $address->update($data);

            $event = 'depot_address_updated';
        } else {
            // CREATE
            $address = Address::create($data);

            $depot->update([
                'addr_code' => $address->addr_code,
            ]);

            $event = 'depot_address_added';
        }

        logActivity(
            $event,
            $request->user(),
            MstDepot::class,
            $depot->id,
            $depot->code,
            [
                'depot_name' => $depot->name,
                'depot_code' => $depot->code,
                'addr_code' => $address->addr_code,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }


    // Upload photo for depot
    public function uploadPhoto(Request $request, MstDepot $depot)
    {

        // Validate image file
        $request->validate([
            'picture' => 'required|image|mimes:jpeg,jpg,png|max:2048', // max 2MB
        ]);

        $pictureFile = $request->file('picture');


        // Delete old photo if exists
        if ($depot->picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($depot->picture)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($depot->picture);
        }

        try {

            // Handle file upload
            $path = null;
            if ($pictureFile) {
                // $filename = $depot->code . '_' . time() . '.' . $pictureFile->getClientOriginalExtension();
                $path = $pictureFile->store('depot_photos/' . $depot->code,  'public');
            }
        } catch (\Exception $e) {
            Log::error('Error uploading depot photo', ['error' => $e->getMessage()]);
            // return $this->errorResponse(__('messages.error_messages.file_upload_failed'), 500);
        }

        // if no file then error
        if (!$path) {
            return $this->errorResponse(__('messages.error_messages.file_upload_failed'), 500);
        }

        // Update depot with new photo path
        $depot->update([
            'picture' => $path,
        ]);

        logActivity(
            'depot_photo_uploaded',
            $request->user(),
            MstDepot::class,
            $depot->id,
            $depot->code,
            [
                'depot_name' => $depot->name,
                'depot_code' => $depot->code,
                'photo_path' => $path,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }


    // Delete photo for depot
    public function deletePhoto(Request $request, MstDepot $depot)
    {
        // Delete old photo if exists
        if ($depot->picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($depot->picture)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($depot->picture);
        }

        // Update depot to remove photo path
        $depot->update([
            'picture' => null,
        ]);

        // Log activity
        logActivity(
            'depot_photo_deleted',
            $request->user(),
            MstDepot::class,
            $depot->id,
            $depot->code,
            [
                'depot_name' => $depot->name,
                'depot_code' => $depot->code,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_delete'),
            200
        );
    }

    //
}
