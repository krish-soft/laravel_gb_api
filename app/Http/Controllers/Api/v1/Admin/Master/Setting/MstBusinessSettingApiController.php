<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Setting;

use App\Enum\AddressTypeEnum;
use App\Enum\Admin\AdminRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Requests\AddressRequest;
use App\Models\Common\Address;
use App\Models\Master\Setting\MstBusinessSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MstBusinessSettingApiController extends ApiResponseWithAdminAuthController
{
    //

    public function getSetting(Request $request)
    {
        //

        $businessSetting = MstBusinessSetting::getOrCreate();

        $businessSetting = MstBusinessSetting::with('billAddress')->where('id', $businessSetting->id)->first();

        return $this->successResponse(__('messages.success_messages.success_get'), $businessSetting, 200);
    }

    public function updateSetting(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdminManagement() || $user->role !== AdminRoleEnum::SUPERADMIN->value) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        $validated = $request->validate([
            'picture'                 => 'nullable|string',

            'legal_name'              => 'nullable|string|max:100',
            'trade_name'              => 'nullable|string|max:100',

            'gst_number'             => 'nullable|string|max:20',
            'cin_number'             => 'nullable|string|max:20',
            'pan_number'             => 'nullable|string|max:20',
            'tan_number'             => 'nullable|string|max:20',

            'email'                   => 'nullable|string|email|max:255',
            'phone_number'            => 'nullable|string|max:20',

            'website'                => 'nullable|string|max:255|url',
            'terms_url'              => 'nullable|string|max:255|url',
            'privacy_url'            => 'nullable|string|max:255|url',

            'notes'                  => 'nullable|string',

            'is_active'              => 'sometimes|boolean',

        ]);

        $businessSetting = MstBusinessSetting::getOrCreate();

        $businessSetting->fill($validated);

        $dirty = $businessSetting->getDirty();
        $original = $businessSetting->getOriginal();

        $businessSetting->save();

        $changes = [];
        foreach ($dirty as $key => $newValue) {
            $changes[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        logActivity(
            'business_setting_updated',
            $request->user(),
            get_class($businessSetting),
            $businessSetting->id,
            null,
            $changes
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }


    public function saveBillAddress(AddressRequest $request)
    {
        // Log::info('Saving address for depot', ['businessSetting' => $businessSetting->setting_code]);

        $data = $request->validated();

        $data['addr_type'] = AddressTypeEnum::BILL->value;

        $businessSetting = MstBusinessSetting::getOrCreate();

        if ($businessSetting->bill_addr_code) {
            // UPDATE
            $address = Address::where('addr_code', $businessSetting->bill_addr_code)
                ->firstOrFail();

            $address->update($data);

            $event = 'business_bill_address_updated';
        } else {
            // CREATE
            $address = Address::create($data);

            $businessSetting->update([
                'bill_addr_code' => $address->bill_addr_code,
            ]);

            $event = 'business_bill_address_added';
        }


        logActivity(
            $event,
            $request->user(),
            MstBusinessSetting::class,
            $businessSetting->id,
            $businessSetting->id,
            [
                'legal_name' => $businessSetting->legal_name,
                'bill_addr_code' => $address->bill_addr_code,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }


    // Upload photo for business setting
    public function uploadPhoto(Request $request, MstBusinessSetting $businessSetting)
    {

        // Validate image file
        $request->validate([
            'picture' => 'required|image|mimes:jpeg,jpg,png|max:2048', // max 2MB
        ]);

        $pictureFile = $request->file('picture');


        // Delete old photo if exists
        if ($businessSetting->picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($businessSetting->picture)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($businessSetting->picture);
        }

        try {

            // Handle file upload
            $path = null;
            if ($pictureFile) {
                // $filename = $businessSetting->code . '_' . time() . '.' . $pictureFile->getClientOriginalExtension();
                $path = $pictureFile->store('business_setting_photos/' . $businessSetting->id,  'public');
            }
        } catch (\Exception $e) {
            Log::error('Error uploading business setting photo', ['error' => $e->getMessage()]);
            // return $this->errorResponse(__('messages.error_messages.file_upload_failed'), 500);
        }

        // if no file then error
        if (!$path) {
            return $this->errorResponse(__('messages.error_messages.file_upload_failed'), 500);
        }

        // Update business setting with new photo path
        $businessSetting->update([
            'picture' => $path,
        ]);

        logActivity(
            'business_setting_photo_uploaded',
            $request->user(),
            MstBusinessSetting::class,
            $businessSetting->id,
            $businessSetting->id,
            [
                'business_setting_name' => $businessSetting->name,
                'photo_path' => $path,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }


    // Delete photo for business setting
    public function deletePhoto(Request $request, MstBusinessSetting $businessSetting)
    {
        // Delete old photo if exists
        if ($businessSetting->picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($businessSetting->picture)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($businessSetting->picture);
        }

        // Update business setting to remove photo path
        $businessSetting->update([
            'picture' => null,
        ]);

        // Log activity
        logActivity(
            'business_setting_photo_deleted',
            $request->user(),
            MstBusinessSetting::class,
            $businessSetting->id,
            $businessSetting->id,
            [
                'business_setting_name' => $businessSetting->name,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_delete'),
            200
        );
    }


    //
}
