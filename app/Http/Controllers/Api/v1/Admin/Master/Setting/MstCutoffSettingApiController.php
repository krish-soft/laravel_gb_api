<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Setting;

use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstCutoffSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MstCutoffSettingApiController extends ApiResponseWithAdminAuthController
{
    //

    public function getSetting(Request $request)
    {
        //

        $cutoffSetting = MstCutoffSetting::getOrCreate();

        return $this->successResponse(__('messages.success_messages.success_get'), $cutoffSetting, 200);
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
            'buyer_start_time' => 'nullable|date_format:H:i:s',
            'buyer_end_time' => 'nullable|date_format:H:i:s',

            'seller_start_time' => 'nullable|date_format:H:i:s',
            'seller_end_time' => 'nullable|date_format:H:i:s',

            'is_buyer_auto_cutoff' => 'nullable|boolean',
            'is_seller_auto_cutoff' => 'nullable|boolean',
        ]);


        $cutoffSetting = MstCutoffSetting::getOrCreate();

        $cutoffSetting->fill($validated);

        $dirty = $cutoffSetting->getDirty();
        $original = $cutoffSetting->getOriginal();

        $cutoffSetting->save();


        $changes = [];
        foreach ($dirty as $key => $newValue) {
            $changes[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        logActivity(
            'cutoff_setting_updated',
            $request->user(),
            get_class($cutoffSetting),
            $cutoffSetting->id,
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
