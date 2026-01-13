<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Setting;

use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Setting\AppSetting;
use Illuminate\Http\Request;

class AppSettingApiController extends ApiResponseWithAdminAuthController
{
    //

    public function getSetting(Request $request)
    {
        //

        $appSettting = AppSetting::first();

        return $this->successResponse(__('messages.success_messages.success_get'), $appSettting, 200);
    }

    public function updateSetting(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdminManagement() || $user->role !== AdminRoleEnum::SUPERADMIN) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        $validated = $request->validate([
            'app_name'                    => 'required|string|max:30',

            'currency'                    => 'sometimes|string|max:10',
            'currency_symbol'             => 'sometimes|string|max:5',
            'date_format'                 => 'sometimes|string|max:50',
            'time_format'                 => 'sometimes|string|max:50',

            'is_maintenance_mode'         => 'sometimes|boolean',
            'maintenance_message'         => 'nullable|string|max:1000',

            'is_registration_enabled'        => 'sometimes|boolean',

            'app_version'                 => 'sometimes|string|max:50',

            'mobile_app_android_version'  => 'required_with:is_force_app_android_update|string|max:50',
            'is_force_app_android_update' => 'sometimes|boolean',

            'mobile_app_ios_version'      => 'required_with:is_force_app_ios_update|string|max:50',
            'is_force_app_ios_update'     => 'sometimes|boolean',
        ]);

        $appSetting = AppSetting::first() ?? new AppSetting();

        $appSetting->fill($validated);

        $dirty = $appSetting->getDirty();
        $original = $appSetting->getOriginal();

        $appSetting->save();

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
            get_class($appSetting),
            $appSetting->id,
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
