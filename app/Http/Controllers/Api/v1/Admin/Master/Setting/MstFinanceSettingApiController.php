<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Setting;

use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Master\Setting\MstFinanceSetting;
use Illuminate\Http\Request;

class MstFinanceSettingApiController extends ApiResponseWithAdminAuthController
{
    //

    public function getSetting(Request $request)
    {
        //

        $financeSetting = MstFinanceSetting::getOrCreate();

        return $this->successResponse(__('messages.success_messages.success_get'), $financeSetting, 200);
    }

    public function updateSetting(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdminManagement() || $user->role !== AdminRoleEnum::SUPERADMIN->value) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        $validated = $request->validate([
            'currency'                   => 'sometimes|string|max:10',
            'currency_symbol'            => 'sometimes|string|max:5',

            'currency_position'          => 'sometimes|string|max:10',
            'thousand_separator'         => 'sometimes|string|max:5',
            'decimal_separator'          => 'sometimes|string|max:5',
            'decimal_places'             => 'sometimes|integer',

            'financial_year_id'          => 'sometimes|integer|exists:mst_financial_years,id',

        ]);

        $financeSetting = MstFinanceSetting::getOrCreate();

        $financeSetting->fill($validated);

        $dirty = $financeSetting->getDirty();
        $original = $financeSetting->getOriginal();

        $financeSetting->save();

        $changes = [];
        foreach ($dirty as $key => $newValue) {
            $changes[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        logActivity(
            'app_setting_updated',
            $request->user(),
            get_class($financeSetting),
            $financeSetting->id,
            null,
            $changes
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }



    //
}
