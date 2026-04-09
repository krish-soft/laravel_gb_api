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

            'seller_start_time' => 'nullable',
            'seller_end_time' => 'nullable',

            'buyer_start_time' => 'nullable',
            'buyer_end_time' => 'nullable',

            'is_buyer_auto_cutoff' => 'nullable|boolean',
            'is_seller_auto_cutoff' => 'nullable|boolean',
        ]);


        // Need to check seller_end_time above buyer_end_time 
        // buyer_end_time is always after seller_end_time

        $error = $this->validateCutoffOrder($validated);

        if ($error) {
            return $this->showErrorMessage($error, 422);
        }


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


    private function validateCutoffOrder(array $data)
    {
        if (!empty($data['seller_end_time']) && !empty($data['buyer_end_time'])) {

            $sellerEnd = Carbon::parse($data['seller_end_time']);
            $buyerEnd  = Carbon::parse($data['buyer_end_time']);

            // buyer_end_time must be at least 15 minutes after seller_end_time
            if ($buyerEnd->lessThan($sellerEnd->copy()->addMinutes(15))) {
                return 'Buyer end time must be at least 15 minutes after seller end time.';
            }
        }

        if (!empty($data['seller_start_time']) && !empty($data['seller_end_time'])) {

            $sellerStart = Carbon::parse($data['seller_start_time']);
            $sellerEnd   = Carbon::parse($data['seller_end_time']);

            if ($sellerEnd->lessThanOrEqualTo($sellerStart)) {
                return 'Seller end time must be after seller start time.';
            }
        }

        if (!empty($data['buyer_start_time']) && !empty($data['buyer_end_time'])) {

            $buyerStart = Carbon::parse($data['buyer_start_time']);
            $buyerEnd   = Carbon::parse($data['buyer_end_time']);

            if ($buyerEnd->lessThanOrEqualTo($buyerStart)) {
                return 'Buyer end time must be after buyer start time.';
            }
        }

        return null;
    }

    // ORG
    // private function validateCutoffOrder(array $data)
    // {
    //     if (!empty($data['seller_end_time']) && !empty($data['buyer_end_time'])) {

    //         $sellerEnd = Carbon::parse($data['seller_end_time']);
    //         $buyerEnd  = Carbon::parse($data['buyer_end_time']);

    //         if ($buyerEnd->lessThanOrEqualTo($sellerEnd)) {
    //             return 'Buyer end time must be after seller end time.';
    //         }
    //     }

    //     if (!empty($data['seller_start_time']) && !empty($data['seller_end_time'])) {

    //         $sellerStart = Carbon::parse($data['seller_start_time']);
    //         $sellerEnd   = Carbon::parse($data['seller_end_time']);

    //         if ($sellerEnd->lessThanOrEqualTo($sellerStart)) {
    //             return 'Seller end time must be after seller start time.';
    //         }
    //     }

    //     if (!empty($data['buyer_start_time']) && !empty($data['buyer_end_time'])) {

    //         $buyerStart = Carbon::parse($data['buyer_start_time']);
    //         $buyerEnd   = Carbon::parse($data['buyer_end_time']);

    //         if ($buyerEnd->lessThanOrEqualTo($buyerStart)) {
    //             return 'Buyer end time must be after buyer start time.';
    //         }
    //     }

    //     return null;
    // }

    //
}
