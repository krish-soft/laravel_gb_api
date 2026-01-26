<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Fulfillment;

use App\Enum\Common\Legal\KycStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Fulfillment\FulfillmentLocationDepot;
use App\Models\Common\User\UserDepot;
use App\Models\User;
use Illuminate\Http\Request;

class AdminFulfillmentLocationApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $fulfillmentLocationQuery = FulfillmentLocation::with('user:id,user_code,name,role', 'address', 'depots')->latest();

        if ($request->has('status') && !is_null($request->status)) {
            $fulfillmentLocationQuery->where('status', $request->status);
        } else {
            if (!$request->user()->isSuperAdminGroup()) {
                $fulfillmentLocationQuery->whereIn('status', [KycStatusEnum::PENDING->value, KycStatusEnum::UNDER_REVIEW->value]);
            }
        }

        $fulfillmentLocations = $fulfillmentLocationQuery->get();

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
        $fulfillmentLocation = FulfillmentLocation::with('user:id,user_code,name,role', 'address')->where('id', $fulfillmentLocation->id)->firstOrFail();

        $depots = FulfillmentLocationDepot::with(['depot', 'fulfillmentLocation:id,fl_code,name,type'])
            ->where('fulfillment_location_id', $fulfillmentLocation->id)
            ->get();

        $fulfillmentLocation->depots = $depots;



        return $this->successResponse(__('messages.success_messages.success_get'), $fulfillmentLocation, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FulfillmentLocation $fulfillmentLocation)
    {
        //
        $request->validate([
            'status' => 'sometimes|in:' . implode(',', array_map(fn($e) => $e->value, KycStatusEnum::cases())),
            'review_comment' => 'sometimes|string|max:1000',
        ]);

        $status = $request->input('status');
        $reviewComment = $request->input('review_comment');

        if ($status) {
            $fulfillmentLocation->status = $status;
            $fulfillmentLocation->review_comment = $reviewComment ?? $fulfillmentLocation->review_comment;

            if ($status == KycStatusEnum::APPROVED->value) {
                $fulfillmentLocation->is_active = true;
                $fulfillmentLocation->inactive_reason = null;
            }

            if ($status == KycStatusEnum::REJECTED->value) {
                $fulfillmentLocation->is_active = false;
                $fulfillmentLocation->inactive_reason = $reviewComment ?? 'Rejected by Admin';
            }

            // common
            $fulfillmentLocation->verification_mode = 'admin_user';
            $fulfillmentLocation->verified_at = now();
            $fulfillmentLocation->verified_by = $request->user()->name;
            $fulfillmentLocation->verified_user_id = $request->user()->id;


            $fulfillmentLocation->save();
        }


        /// Log activity
        logActivity(
            'admin_fulfillment_location_updated',
            $request->user(),       // ACTOR (who did it)
            get_class($fulfillmentLocation),       // SUBJECT TYPE (what was affected)
            $fulfillmentLocation->id,              // SUBJECT ID
            $fulfillmentLocation->fl_code,       // SUBJECT CODE (human readable)
            [
                'fl_code' => $fulfillmentLocation->fl_code,
                'status' => $status,
                'review_comment' => $reviewComment,
            ]
        );



        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FulfillmentLocation $fulfillmentLocation)
    {
        //
    }


    public function addDepot(Request $request)
    {
        $request->validate([
            'fulfillment_location_id' => 'required|exists:fulfillment_locations,id',
            'depot_id' => 'required|exists:mst_depots,id',
            'is_primary' => 'sometimes|boolean',
        ]);

        $fulfillmentLocation = FulfillmentLocation::findOrFail($request->fulfillment_location_id);

        $makePrimary = (bool)$request->input('is_primary', false);

        // Check for same depot addition
        $existingDepot = $fulfillmentLocation->depots()->where('depot_id', $request->depot_id)
            ->first();

        if ($existingDepot) {
            return $this->showErrorMessage(__('messages.error_messages.already_exists'), 422);
        }

        // allowed only one depot
        // For now removed restriction to allow multiple depots
        // if ($fulfillmentLocation->depots()->exists()) {
        //     return $this->showErrorMessage(__('messages.error_messages.only_one_depot_allowed'), 422);
        // }


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


    public function removeDepot(Request $request, FulfillmentLocationDepot $fulfillmentLocationDepot)
    {


        $depotId = $request->input('depot_id');

        // $fulfillmentLocationDepot = $fulfillmentLocation->depots()
        //     ->where('depot_id', $depotId)
        //     ->firstOrFail();
        $fulfillmentLocation = $fulfillmentLocationDepot->fulfillmentLocation;

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
