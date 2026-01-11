<?php

namespace App\Http\Controllers\Api\v1\User\Fulfillment;

use App\Enum\Fulfillment\FulfillmentLocationTypeEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Models\Address;
use App\Models\Fulfillment\FulfillmentLocation;
use Illuminate\Http\Request;
use Termwind\Components\Raw;

class FulfillmentLocationApiController extends ApiResponseWithAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $user = $request->user();

        $locations = FulfillmentLocation::where('user_id', $user->id)->get();

        return $this->successResponse(__('messages.success_messages.success_get'),  $locations, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:' . implode(',', FulfillmentLocationTypeEnum::casesAsValues()),

        ]);

        $location = FulfillmentLocation::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'type' => $request->type,
        ]);

        // Log activity
        logActivity(
            'fulfillment_location_create',
            $request->user(),                 // ACTOR (who did it)
            get_class($location), // SUBJECT TYPE (what was affected)
            $location->id,              // SUBJECT ID
            $location->fl_code,       // SUBJECT CODE (human readable)
            [
                'name' => $location->name,
                'type' => $location->type,
                'fl_code' => $location->fl_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FulfillmentLocation $fulfillmentLocation)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'),  $fulfillmentLocation, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FulfillmentLocation $fulfillmentLocation)
    {
        //
        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'type' => 'sometimes|required|string|in:' . implode(',', FulfillmentLocationTypeEnum::casesAsValues()),

        ]);

        $user = $request->user();

        if ($fulfillmentLocation->user_id !== $user->id) {
            return $this->errorResponse(__('messages.error_messages.unauthorized_access'), 403);
        }

        $fulfillmentLocation->update($request->only(['name', 'type']));

        // Log activity
        logActivity(
            'user_fulfillment_location_update',
            $user,                 // ACTOR (who did it)
            get_class($fulfillmentLocation), // SUBJECT TYPE (what was affected)
            $fulfillmentLocation->id,              // SUBJECT ID
            $fulfillmentLocation->fl_code,       // SUBJECT CODE (human readable)
            [
                'name' => $fulfillmentLocation->name,
                'type' => $fulfillmentLocation->type,
                'fl_code' => $fulfillmentLocation->fl_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, FulfillmentLocation $fulfillmentLocation)
    {
        //

        $user = $request->user();

        if ($fulfillmentLocation->user_id !== $user->id) {
            return $this->errorResponse(__('messages.error_messages.unauthorized_access'), 403);
        }

        // Log activity
        logActivity(
            'user_fulfillment_location_delete',
            $user,                 // ACTOR (who did it)
            get_class($fulfillmentLocation), // SUBJECT TYPE (what was affected)
            $fulfillmentLocation->id,              // SUBJECT ID
            $fulfillmentLocation->fl_code,       // SUBJECT CODE (human readable)
            [
                'name' => $fulfillmentLocation->name,
                'type' => $fulfillmentLocation->type,
                'fl_code' => $fulfillmentLocation->fl_code,
            ]
        );

        $fulfillmentLocation->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }


    // Add Address For Fulfillment Location
    public function addAddress(AddressRequest $request, FulfillmentLocation $fulfillmentLocation)
    {
        //
        // Already Exist then give error
        if (!empty($fulfillmentLocation->addr_code)) {
            return $this->errorResponse(__('messages.error_messages.address_exists'), 422);
        }

        $address = Address::create($request->all());
        $fulfillmentLocation->addr_code = $address->addr_code;

        $fulfillmentLocation->save();

        $user = $request->user();
        // Log activity
        logActivity(
            'fulfillment_location_address_added',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($fulfillmentLocation), // SUBJECT TYPE (what was affected)
            $fulfillmentLocation->id,              // SUBJECT ID
            $fulfillmentLocation->fl_code,       // SUBJECT CODE (human readable)
            [
                'name' => $fulfillmentLocation->name,
                'type' => $fulfillmentLocation->type,
                'addr_code' => $address->addr_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    // update Address of Depot
    public function updateAddress(AddressRequest $request, FulfillmentLocation $fulfillmentLocation)
    {
        //

        // Check same Address or not
        $address = Address::where('addr_code', $fulfillmentLocation->addr_code)->firstOrFail();

        $address->update($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'fulfillment_location_address_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($fulfillmentLocation), // SUBJECT TYPE (what was affected)
            $fulfillmentLocation->id,              // SUBJECT ID
            $fulfillmentLocation->fl_code,       // SUBJECT CODE (human readable)
            [
                'name' => $fulfillmentLocation->name,
                'type' => $fulfillmentLocation->type,
                'addr_code' => $address->addr_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }
}
