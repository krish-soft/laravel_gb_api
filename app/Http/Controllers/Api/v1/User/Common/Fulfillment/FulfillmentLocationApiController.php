<?php

namespace App\Http\Controllers\Api\v1\User\Common\Fulfillment;

use App\Enum\AddressTypeEnum;
use App\Enum\Common\Fulfillment\FulfillmentLocationTypeEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Requests\AddressRequest;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Depot\MstDepot;
use Illuminate\Http\Request;

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

        return $this->successResponse(__('messages.success_messages.success_get'), $locations, 200);
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

        return $this->successResponse(__('messages.success_messages.success_create'), $location, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FulfillmentLocation $fulfillmentLocation)
    {
        //
        $fulfillmentLocation = FulfillmentLocation::with('address')->find($fulfillmentLocation->id);

        return $this->successResponse(__('messages.success_messages.success_get'), $fulfillmentLocation, 200);
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


    public function saveAddress(AddressRequest $request, FulfillmentLocation $fulfillmentLocation)
    {
        // Log::info('Saving address for depot', ['depot_id' => $depot->id]);

        $user = $request->user();
        $data = $request->validated();
        if ($user->isSeller()) {
            $data['addr_type'] = AddressTypeEnum::PICK->value;
        } else if ($user->isBuyer()) {
            $data['addr_type'] = AddressTypeEnum::SHIP->value;
        } else if ($user->isDelivery()) {
            $data['addr_type'] = AddressTypeEnum::DELIVERY_PARTNER_HUB->value;
        }

        if ($fulfillmentLocation->addr_code) {
            // UPDATE
            $address = Address::where('addr_code', $fulfillmentLocation->addr_code)
                ->firstOrFail();

            $address->update($data);

            $event = 'fulfillment_location_address_updated';
        } else {
            // CREATE
            $address = Address::create($data);

            $fulfillmentLocation->update([
                'addr_code' => $address->addr_code,
            ]);

            $event = 'fulfillment_location_address_added';
        }

        logActivity(
            $event,
            $request->user(),
            MstDepot::class,
            $fulfillmentLocation->id,
            $fulfillmentLocation->fl_code,
            [
                'name' => $fulfillmentLocation->name,
                'type' => $fulfillmentLocation->type,
                'addr_code' => $address->addr_code,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }

}
