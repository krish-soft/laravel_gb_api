<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Charge\Rule;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Charge\Rule\MstMinimumOrderChargeRule;
use Illuminate\Http\Request;

class MstMinimumOrderChargeRuleApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $mstMinimumOrderChargeRules = MstMinimumOrderChargeRule::all();
        return $this->successResponse(__('messages.success_messages.success_get'), $mstMinimumOrderChargeRules);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'charge_id' => 'required|exists:mst_charges,id',
            'charge_level_id' => 'required|exists:mst_charge_levels,id',
            'description' => 'required|string|min:10|max:255',
            'calc_type' => 'required|string|in:fixed,percentage',
            'calc_condition' => 'required|string',
            'min_order_price' => 'required|numeric|min:0.01',
            'min_order_qty' => 'nullable|numeric|min:0',
            'min_order_weight' => 'nullable|numeric|min:0',
            'charge_amount' => 'required|numeric|min:0',
        ]);

        // Check Exist
        $existingRule = MstMinimumOrderChargeRule::where('charge_id', $request->charge_id)
            ->where('charge_level_id', $request->charge_level_id)
            ->where('calc_type', $request->calc_type)
            ->where('calc_condition', $request->calc_condition)
            ->where('min_order_price', $request->min_order_price)
            ->first();

        if ($existingRule) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }

        $mstMinimumOrderChargeRule = MstMinimumOrderChargeRule::create($request->all());

        // Log activity
        logActivity(
            'minimum_order_charge_rule_created',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstMinimumOrderChargeRule), // SUBJECT TYPE (what was affected)
            $mstMinimumOrderChargeRule->id,              // SUBJECT ID
            $mstMinimumOrderChargeRule->rule_no,       // SUBJECT CODE (human readable)
            [
                'charge_id' => $mstMinimumOrderChargeRule->charge_id,
                'charge_level_id' => $mstMinimumOrderChargeRule->charge_level_id,
                'calc_type' => $mstMinimumOrderChargeRule->calc_type,
                'calc_condition' => $mstMinimumOrderChargeRule->calc_condition,
                'min_order_price' => $mstMinimumOrderChargeRule->min_order_price,
                'charge_amount' => $mstMinimumOrderChargeRule->charge_amount,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstMinimumOrderChargeRule $mstMinimumOrderChargeRule)
    {
        //
        return $this->successResponse(__('messages.success_messages.success_get'), $mstMinimumOrderChargeRule);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstMinimumOrderChargeRule $mstMinimumOrderChargeRule)
    {
        //
        $request->validate([
            'charge_id' => 'required|exists:mst_charges,id',
            'charge_level_id' => 'required|exists:mst_charge_levels,id',
            'description' => 'required|string|min:10|max:255',
            'calc_type' => 'required|string|in:fixed,percentage',
            'calc_condition' => 'required|string',
            'min_order_price' => 'required|numeric|min:0.01',
            'min_order_qty' => 'nullable|numeric|min:0',
            'min_order_weight' => 'nullable|numeric|min:0',
            'charge_amount' => 'required|numeric|min:0',
        ]);
        // Check Exist
        $existingRule = MstMinimumOrderChargeRule::where('charge_id', $request->charge_id)
            ->where('charge_level_id', $request->charge_level_id)
            ->where('calc_type', $request->calc_type)
            ->where('calc_condition', $request->calc_condition)
            ->where('min_order_price', $request->min_order_price)
            ->where('id', '!=', $mstMinimumOrderChargeRule->id)
            ->first();

        if ($existingRule) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }

        $mstMinimumOrderChargeRule->update($request->all());

        // Log activity
        logActivity(
            'minimum_order_charge_rule_updated',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstMinimumOrderChargeRule), // SUBJECT TYPE (what was affected)
            $mstMinimumOrderChargeRule->id,              // SUBJECT ID
            $mstMinimumOrderChargeRule->rule_no,       // SUBJECT CODE (human readable)
            [
                'charge_id' => $mstMinimumOrderChargeRule->charge_id,
                'charge_level_id' => $mstMinimumOrderChargeRule->charge_level_id,
                'calc_type' => $mstMinimumOrderChargeRule->calc_type,
                'calc_condition' => $mstMinimumOrderChargeRule->calc_condition,
                'min_order_price' => $mstMinimumOrderChargeRule->min_order_price,
                'charge_amount' => $mstMinimumOrderChargeRule->charge_amount,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstMinimumOrderChargeRule $mstMinimumOrderChargeRule)
    {
        //
        return $this->showErrorMessage(
            __('messages.error_messages.cannot_delete_used_in_transactions'),
            409
        );


        $mstMinimumOrderChargeRule->delete();
        // Log activity
        logActivity(
            'minimum_order_charge_rule_deleted',        // EVENT
            request()->user(),                 // ACTOR (who did it)
            get_class($mstMinimumOrderChargeRule), // SUBJECT TYPE (what was affected)
            $mstMinimumOrderChargeRule->id,              // SUBJECT ID
            $mstMinimumOrderChargeRule->rule_no,       // SUBJECT CODE (human readable)
            [
                'charge_id' => $mstMinimumOrderChargeRule->charge_id,
                'charge_level_id' => $mstMinimumOrderChargeRule->charge_level_id,
                'calc_type' => $mstMinimumOrderChargeRule->calc_type,
                'calc_condition' => $mstMinimumOrderChargeRule->calc_condition,
                'min_order_price' => $mstMinimumOrderChargeRule->min_order_price,
                'charge_amount' => $mstMinimumOrderChargeRule->charge_amount,
            ]
        );
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }
}
