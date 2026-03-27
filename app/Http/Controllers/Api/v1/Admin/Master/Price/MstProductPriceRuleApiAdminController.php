<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Price;

use App\Enum\User\UserTypeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Price\MstProductPriceRule;
use Illuminate\Http\Request;

class MstProductPriceRuleApiAdminController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        $priceRules = MstProductPriceRule::with(['chargeLevel'])->latest()->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $priceRules, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'charge_level_id' => 'required|exists:mst_charge_levels,id',
            'user_type' => 'nullable|string|in:' . implode(',', UserTypeEnum::casesAsValues()),
            'pack_unit' => 'nullable|string|in:kg',
            'calc_type' => 'nullable|string|in:percentage,fixed',

            '1_pkg' => 'required|numeric',
            '2_pkg' => 'required|numeric',
            '3_pkg' => 'required|numeric',
            '5_pkg' => 'required|numeric',
            '10_pkg' => 'required|numeric',
            '20_pkg' => 'required|numeric',

        ]);

        // Check for same charge leve and user type combination
        $existingRule = MstProductPriceRule::where('charge_level_id', $request->input('charge_level_id'))
            ->where('user_type', $request->input('user_type'))
            ->where('pack_unit', $request->input('pack_unit'))
            ->first();

        if ($existingRule) {
            return $this->showErrorMessage(__('messages.error_messages.duplicate_entry_found'), 400);
        }

        $priceRule = MstProductPriceRule::create($request->all());

        // Log activity
        logActivity(
            'product_price_rule_created',        // EVENT
            $request->user(),                       // ACTOR
            MstProductPriceRule::class,         // SUBJECT TYPE
            $priceRule->id,           // SUBJECT ID
            $priceRule->rule_no, // SUBJECT CODE    
            [
                'rule_no' => $priceRule->rule_no, // META             
                'user_type' => $priceRule->user_type,
                'pack_unit' => $priceRule->pack_unit,
                'calc_type' => $priceRule->calc_type,
            ]
        );


        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstProductPriceRule $mstProductPriceRule)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstProductPriceRule->load('chargeLevel'), 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstProductPriceRule $mstProductPriceRule)
    {
        //

        $request->validate([
            'charge_level_id' => 'required|exists:mst_charge_levels,id',
            'user_type' => 'nullable|string|in:' . implode(',', UserTypeEnum::casesAsValues()),
            'pack_unit' => 'nullable|string|in:kg',
            'calc_type' => 'nullable|string|in:percentage,fixed',

            '1_pkg' => 'required|numeric',
            '2_pkg' => 'required|numeric',
            '3_pkg' => 'required|numeric',
            '5_pkg' => 'required|numeric',
            '10_pkg' => 'required|numeric',
            '20_pkg' => 'required|numeric',
        ]);

        // Check for same charge level and user type combination excluding current rule
        $existingRule = MstProductPriceRule::where('charge_level_id', $request->input('charge_level_id'))
            ->where('user_type', $request->input('user_type'))
            ->where('pack_unit', $request->input('pack_unit'))
            ->where('id', '!=', $mstProductPriceRule->id)
            ->first();

        if ($existingRule) {
            return $this->showErrorMessage(__('messages.error_messages.duplicate_entry_found'), 400);
        }

        $mstProductPriceRule->update($request->all());

        // Log activity 
        logActivity(
            'product_price_rule_updated',        // EVENT
            $request->user(),                       // ACTOR
            MstProductPriceRule::class,         // SUBJECT TYPE
            $mstProductPriceRule->id,           // SUBJECT ID   
            $mstProductPriceRule->rule_no, // SUBJECT CODE
            [                                       // META
                'charge_level_id' => $mstProductPriceRule->charge_level_id,
                'user_type' => $mstProductPriceRule->user_type,
                'pack_unit' => $mstProductPriceRule->pack_unit,
                'calc_type' => $mstProductPriceRule->calc_type,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstProductPriceRule $mstProductPriceRule)
    {
        //

        // if single then do not delete
        if (MstProductPriceRule::count() <= 1) {
            return $this->showErrorMessage(__('messages.error_messages.cannot_delete_used_in_transactions'),  400);
        }


        // Log activity
        logActivity(
            'product_price_rule_deleted',        // EVENT
            request()->user(),                       // ACTOR
            MstProductPriceRule::class,         // SUBJECT TYPE
            $mstProductPriceRule->id,           // SUBJECT ID
            $mstProductPriceRule->rule_no, // SUBJECT CODE
            [
                'rule_no' => $mstProductPriceRule->rule_no, // META
                'user_type' => $mstProductPriceRule->user_type,
            ]
        );

        $mstProductPriceRule->delete();


        return $this->successResponse(__('messages.success_messages.success_delete'), null, 200);
    }
}
