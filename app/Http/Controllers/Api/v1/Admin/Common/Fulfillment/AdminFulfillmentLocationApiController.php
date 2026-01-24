<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Fulfillment;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\User;
use Illuminate\Http\Request;

class AdminFulfillmentLocationApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $fulfillmentLocations = FulfillmentLocation::with('user', 'address', 'depots')->latest()->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $fulfillmentLocations, 200);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(FulfillmentLocation $fulfillmentLocation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FulfillmentLocation $fulfillmentLocation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FulfillmentLocation $fulfillmentLocation)
    {
        //
    }


    public function addDepot(Request $request, FulfillmentLocation $fulfillmentLocation)
    {
        $request->validate([
            'depot_id' => 'required|exists:mst_depots,id',
            'is_primary' => 'sometimes|boolean',
        ]);

        $makePrimary = (bool)$request->input('is_primary', false);

        // Check if user already has a primary depot
        $hasPrimary = $fulfillmentLocation->depots()->where('is_primary', true)->exists();

        // If incoming depot should be primary, unset existing primary
        if ($makePrimary) {
            $fulfillmentLocation->depots()->where('is_primary', true)->update([
                'is_primary' => false,
            ]);
        }

        // If no primary exists at all, force this one as primary
        if (!$makePrimary && !$hasPrimary) {
            $makePrimary = true;
        }

        $fulfillmentLocation->depots()->create([
            'depot_id' => $request->depot_id,
            'is_primary' => $makePrimary,
        ]);

        // Log activity
        logActivity(
            'user_depot_added',
            $request->user(),
            get_class($fulfillmentLocation),
            $fulfillmentLocation->id,
            $fulfillmentLocation->fl_code,
            [
                'fl_code' => $fulfillmentLocation->fl_code,
                'depot_id' => $request->depot_id,
                'is_primary' => $makePrimary,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_create'),
            201
        );
    }


    public function removeDepot(Request $request, FulfillmentLocation $fulfillmentLocation, $depotId)
    {
        $fulfillmentLocationDepot = $fulfillmentLocation->depots()
            ->where('depot_id', $depotId)
            ->firstOrFail();

        $wasPrimary = $fulfillmentLocationDepot->is_primary;

        $fulfillmentLocationDepot->delete();

        // If primary depot was removed, promote another one
        if ($wasPrimary) {
            $nextDepot = $fulfillmentLocation->depots()->first();

            if ($nextDepot) {
                $nextDepot->update([
                    'is_primary' => true,
                ]);
            }
        }

        // Log activity
        logActivity(
            'user_depot_removed',
            $request->user(),
            get_class($fulfillmentLocation),
            $fulfillmentLocation->id,
            $fulfillmentLocation->fl_code,
            [
                'fl_code' => $fulfillmentLocation->fl_code,
                'depot_id' => $depotId,
                'was_primary' => $wasPrimary,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_delete'),
            200
        );
    }
}
