<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Setting;

use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Master\Setting\MstAppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MstAppSettingApiController extends ApiResponseWithAdminAuthController
{
    //

    public function getSetting(Request $request)
    {
        //

        $appSetting = MstAppSetting::getOrCreate();

        return $this->successResponse(__('messages.success_messages.success_get'), $appSetting, 200);
    }

    public function updateSetting(Request $request)
    {



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

        $validated = $request->validate([
            'app_name' => 'nullable|string|max:30',

            'currency' => 'nullable|string|max:10',
            'currency_symbol' => 'nullable|string|max:5',

            'timezone' => 'nullable|string|max:50',
            'locale' => 'nullable|string|max:10',
            'fallback_locale' => 'nullable|string|max:10',

            'date_format' => 'nullable|string|max:50',
            'time_format' => 'nullable|string|max:50',

            // only from cmd
            'is_maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:1000',

            'is_registration_enabled' => 'nullable|boolean',

            'app_version' => 'nullable|string|max:50',

            'is_force_app_android_update' => 'nullable|boolean',
            'mobile_app_android_version' => 'nullable|string|max:50',

            'driver_mobile_app_android_version' => 'nullable|string|max:50',
            'is_force_driver_app_android_update' => 'nullable|boolean',

            'is_force_app_ios_update' => 'nullable|boolean',
            'mobile_app_ios_version' => 'nullable|string|max:50',

            'driver_mobile_app_ios_version' => 'nullable|string|max:50',
            'is_force_driver_app_ios_update' => 'nullable|boolean',
        ]);


        $appSetting = MstAppSetting::getOrCreate();

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
