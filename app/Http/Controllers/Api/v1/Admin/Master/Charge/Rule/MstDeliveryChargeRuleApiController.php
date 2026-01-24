<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Charge\Rule;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Charge\Rule\MstDeliveryChargeRule;
use Illuminate\Http\Request;

class MstDeliveryChargeRuleApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $mstDeliveryChargeRules = MstDeliveryChargeRule::latest()->with(['charge', 'chargeLevel'])->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $mstDeliveryChargeRules);
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
            'description' => 'required|string|min:5|max:255',

            'measure_value' => 'required|numeric|min:0',
            'measure_unit' => 'required|string|exists:mst_units,unit',
            'pack_type_unit' => 'required|string|exists:mst_pack_types,unit',
            'charge_amount' => 'required|numeric|min:0',
        ]);

        // Check Exist
        $existingRule = MstDeliveryChargeRule::where('charge_id', $request->charge_id)
            ->where('charge_level_id', $request->charge_level_id)
            ->where('measure_value', $request->measure_value)
            ->where('measure_unit', $request->measure_unit)
            ->where('pack_type_unit', $request->pack_type_unit)
            ->first();

        if ($existingRule) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }

        $mstDeliveryChargeRule = MstDeliveryChargeRule::create($request->all());

        // Log activity
        logActivity(
            'delivery_charge_rule_created',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstDeliveryChargeRule), // SUBJECT TYPE (what was affected)
            $mstDeliveryChargeRule->id,              // SUBJECT ID
            $mstDeliveryChargeRule->rule_no,        // SUBJECT CODE (human readable)
            [
                'charge_id' =>  $mstDeliveryChargeRule->charge_id,
                'charge_level_id' =>  $mstDeliveryChargeRule->charge_level_id,
                'rule_no'       =>  $mstDeliveryChargeRule->rule_no,
                'measure_value' => $mstDeliveryChargeRule->measure_value,
                'measure_unit' => $mstDeliveryChargeRule->measure_unit,
                'pack_type_unit' => $mstDeliveryChargeRule->pack_type_unit,
                'charge_amount' => $mstDeliveryChargeRule->charge_amount,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstDeliveryChargeRule $mstDeliveryChargeRule)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstDeliveryChargeRule);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstDeliveryChargeRule $mstDeliveryChargeRule)
    {
        //

        $request->validate([
            'charge_id' => 'required|exists:mst_charges,id',
            'charge_level_id' => 'required|exists:mst_charge_levels,id',
            'description' => 'required|string|min:5|max:255',
            'measure_value' => 'required|numeric|min:0',
            'measure_unit' => 'required|string|exists:mst_units,unit',
            'pack_type_unit' => 'required|string|exists:mst_pack_types,unit',
            'charge_amount' => 'required|numeric|min:0',
        ]);

        // Check Exist
        $existingRule = MstDeliveryChargeRule::where('charge_id', $request->charge_id)
            ->where('charge_level_id', $request->charge_level_id)
            ->where('measure_value', $request->measure_value)
            ->where('measure_unit', $request->measure_unit)
            ->where('pack_type_unit', $request->pack_type_unit)
            ->where('id', '!=', $mstDeliveryChargeRule->id)
            ->first();

        if ($existingRule) {
            return $this->showErrorMessage(
                __('messages.error_messages.already_exists'),
                409
            );
        }
        $mstDeliveryChargeRule->update($request->all());

        // Log activity
        logActivity(
            'delivery_charge_rule_updated',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstDeliveryChargeRule), // SUBJECT TYPE (what was affected)
            $mstDeliveryChargeRule->id,              // SUBJECT ID
            $mstDeliveryChargeRule->rule_no,        // SUBJECT CODE (human readable)
            [
                'charge_id' =>  $mstDeliveryChargeRule->charge_id,
                'charge_level_id' =>  $mstDeliveryChargeRule->charge_level_id,
                'rule_no'       =>  $mstDeliveryChargeRule->rule_no,
                'measure_value' => $mstDeliveryChargeRule->measure_value,
                'measure_unit' => $mstDeliveryChargeRule->measure_unit,
                'pack_type_unit' => $mstDeliveryChargeRule->pack_type_unit,
                'charge_amount' => $mstDeliveryChargeRule->charge_amount,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstDeliveryChargeRule $mstDeliveryChargeRule)
    {
        //
        if($mstDeliveryChargeRule->orderCharges()->exists()) {
            return $this->showErrorMessage(
                __('messages.error_messages.cannot_delete_used_in_transactions'),
                409
            );
        }

        // Log activity
        logActivity(
            'delivery_charge_rule_deleted',        // EVENT
            request()->user(),                 // ACTOR (who did it)
            get_class($mstDeliveryChargeRule), // SUBJECT TYPE (what was affected)
            $mstDeliveryChargeRule->id,              // SUBJECT ID
            $mstDeliveryChargeRule->rule_no,        // SUBJECT CODE (human readable)
            [
                'charge_id' =>  $mstDeliveryChargeRule->charge_id,
                'charge_level_id' =>  $mstDeliveryChargeRule->charge_level_id,
                'rule_no'       =>  $mstDeliveryChargeRule->rule_no,
                'measure_value' => $mstDeliveryChargeRule->measure_value,
                'measure_unit' => $mstDeliveryChargeRule->measure_unit,
                'pack_type_unit' => $mstDeliveryChargeRule->pack_type_unit,
                'charge_amount' => $mstDeliveryChargeRule->charge_amount,
            ]
        );

        $mstDeliveryChargeRule->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
