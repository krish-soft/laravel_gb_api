<?php

namespace App\Http\Controllers\Api\v1\Admin\Master;

use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\MstFinancialYear;
use Illuminate\Http\Request;

class MstFinancialYearApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $mstFinancialYearQuery = MstFinancialYear::latest();
        if ($request->has('is_active')) {
            $mstFinancialYearQuery->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        $mstFinancialYear = $mstFinancialYearQuery->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $mstFinancialYear, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $user = $request->user();
        if (!$user->isAdminManagement() || $user->role !== AdminRoleEnum::SUPERADMIN) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        $request->validate([
            'name' => 'required|string|max:50|unique:mst_financial_years,name',
            'code' => 'required|string|max:50|unique:mst_financial_years,code',
            'start_date' => 'required|date|unique:mst_financial_years,start_date',
            'end_date' => 'required|date|after:start_date|unique:mst_financial_years,end_date',
        ]);

        $mstFinancialYear = MstFinancialYear::create($request->all());

        // Log activity
        logActivity(
            'financial_year_created',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstFinancialYear), // SUBJECT TYPE (what was affected)
            $mstFinancialYear->id,              // SUBJECT ID
            $mstFinancialYear->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstFinancialYear->name,
                'code' => $mstFinancialYear->code,
                'start_date' => $mstFinancialYear->start_date,
                'end_date' => $mstFinancialYear->end_date,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstFinancialYear $mstFinancialYear)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstFinancialYear $mstFinancialYear)
    {
        //
        $user = $request->user();
        if (
            !$user->isAdminManagement() ||
            $user->role !== AdminRoleEnum::SUPERADMIN->value
        ) {
            return $this->showErrorMessage(
                __('messages.error_messages.unauthorized_action'),
                403
            );
        }


        $request->validate([
            'name' => 'required|string|max:50|unique:mst_financial_years,name,' . $mstFinancialYear->id,
            'start_date' => 'required|date|unique:mst_financial_years,start_date,' . $mstFinancialYear->id,
            'end_date' => 'required|date|after:start_date|unique:mst_financial_years,end_date,' . $mstFinancialYear->id,
        ]);

        // Check if in_active current financial year then give error 
        // get first current fy idiot then compare ids
        $currentFy = MstFinancialYear::currentFinancialYear();
        if ($currentFy && $currentFy->id === $mstFinancialYear->id && !$request->is_active) {
            return $this->showErrorMessage(__('messages.error_messages.current_financial_year_cannot_inactive'), 422);
        }

        // Old same exist check
        $oldExist = MstFinancialYear::where('id', '!=', $mstFinancialYear->id)
            ->where('start_date', $request->start_date)
            ->where('end_date', $request->end_date)
            ->exists();

        if ($oldExist) {
            return $this->showErrorMessage(__('messages.error_messages.financial_year_date_conflict'), 422);
        }

        $mstFinancialYear->fill($request->all());
        $mstFinancialYear->save();

        // Log activity
        logActivity(
            'financial_year_updated',        // EVENT
            request()->user(),                 // ACTOR (who did it)
            get_class($mstFinancialYear), // SUBJECT TYPE (what was affected)
            $mstFinancialYear->id,              // SUBJECT ID
            $mstFinancialYear->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstFinancialYear->name,
                'code' => $mstFinancialYear->code,
                'start_date' => $mstFinancialYear->start_date,
                'end_date' => $mstFinancialYear->end_date,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstFinancialYear $mstFinancialYear)
    {
        //

        // restrict deletion of current active financial year
        return $this->errorResponse(__('messages.error_messages.main_resource_cannot_delete'), 403);


        // Log activity
        logActivity(
            'financial_year_deleted',        // EVENT
            request()->user(),                 // ACTOR (who did it)
            get_class($mstFinancialYear), // SUBJECT TYPE (what was affected)
            $mstFinancialYear->id,              // SUBJECT ID
            $mstFinancialYear->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstFinancialYear->name,
                'code' => $mstFinancialYear->code,
                'start_date' => $mstFinancialYear->start_date,
                'end_date' => $mstFinancialYear->end_date,
            ]
        );

        $mstFinancialYear->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
