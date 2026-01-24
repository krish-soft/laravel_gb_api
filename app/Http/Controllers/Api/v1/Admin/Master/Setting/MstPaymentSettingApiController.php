<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Setting;

use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use Illuminate\Http\Request;

class MstPaymentSettingApiController extends ApiResponseWithAdminAuthController
{
    //

    public function getSetting(Request $request)
    {
        //

        $paymentSetting = MstPaymentSetting::getOrCreate();

        return $this->successResponse(__('messages.success_messages.success_get'), $paymentSetting, 200);
    }

    public function updateSetting(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdminManagement() || $user->role !== AdminRoleEnum::SUPERADMIN) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        $validated = $request->validate([
            'payment_in_mode'           => 'sometimes|string|max:50|in:razorpay,manual',
            'payment_out_mode'          => 'sometimes|string|max:50|in:razorpay,manual',

            'min_payout_amount'         => 'sometimes|numeric|min:1',
            'max_payout_amount'         => 'sometimes|numeric|min:1',
            'min_cart_order_amount'     => 'sometimes|numeric|min:1',
            'max_cart_order_amount'     => 'sometimes|numeric|min:1',

            'payout_cycle'              => 'sometimes|integer|min:1',
            'refund_window_days'        => 'sometimes|integer|min:1',
            'max_payment_attempts'      => 'sometimes|integer|min:1',
            'cart_expiry_minutes'       => 'sometimes|integer|min:1',

        ]);

        $paymentSetting = MstPaymentSetting::getOrCreate();

        $paymentSetting->fill($validated);

        $dirty = $paymentSetting->getDirty();
        $original = $paymentSetting->getOriginal();

        $paymentSetting->save();

        $changes = [];
        foreach ($dirty as $key => $newValue) {
            $changes[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        logActivity(
            'payment_setting_updated',
            $request->user(),
            get_class($paymentSetting),
            $paymentSetting->id,
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
