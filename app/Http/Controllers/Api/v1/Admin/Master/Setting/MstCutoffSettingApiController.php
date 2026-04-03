<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Setting;

use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstCutoffSetting;
use Carbon\Carbon;
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
            'buyer_start_time' => 'nullable',
            'buyer_end_time' => 'nullable',
            'seller_start_time' => 'nullable',
            'seller_end_time' => 'nullable',
            'is_buyer_auto_cutoff' => 'nullable|boolean',
            'is_seller_auto_cutoff' => 'nullable|boolean',
        ]);

        // Normalize time to H:i:s
        foreach (
            [
                'buyer_start_time',
                'buyer_end_time',
                'seller_start_time',
                'seller_end_time'
            ] as $field
        ) {

            if (!empty($validated[$field])) {
                $validated[$field] = Carbon::parse($validated[$field])->format('H:i:s');
            }
        }

        $cutoffSetting = MstCutoffSetting::getOrCreate();

        $cutoffSetting->fill($validated);

        $dirty = $cutoffSetting->getDirty();
        $original = $cutoffSetting->getOriginal();

        $cutoffSetting->save();

        // Activity log
        $changes = [];
        foreach ($dirty as $key => $newValue) {
            $changes[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        logActivity(
            'cutoff_setting_updated',
            $user,
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
