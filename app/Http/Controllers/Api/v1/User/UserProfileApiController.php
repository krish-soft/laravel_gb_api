<?php

namespace App\Http\Controllers\Api\v1\User;

use App\Enum\AddressTypeEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Models\Common\Address;
use App\Models\User;
use Illuminate\Http\Request;

class UserProfileApiController extends ApiResponseWithAuthController
{
    //

    public function metaDetails(Request $request)
    {
        $user = $request->user();


        $userPrimaryDepotData = [];
        $userPrimaryDepot = $user->primaryDepot;
        if ($userPrimaryDepot) {
            $mDepot = $userPrimaryDepot->depot;

            // load address and market
            $userPrimaryDepotData = $mDepot->load('address', 'market');
        }

        $meta = [
            // Basic Info
            'user_code' => $user->user_code,
            'name' => $user->name,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'user_type' => $user->user_type, // buyer, seller, delivery


            // KYC Status
            'is_kyc_submitted' => $user->isKycSubmitted(),
            'is_kyc_approved' => $user->isKycApproved(),
            'is_re_kyc' => $user->isReKyc(),
            'kyc_review_comment' => $user->kycReviewComment(),


            // Vehicle KYC
            'is_vehicle_kyc_submitted' => $user->isVehicleKycSubmitted(),
            'is_vehicle_kyc_approved' => $user->isVehicleKycApproved(),
            'vehicle_kyc_review_comment' => $user->vehicleKycReviewComment(),

            'is_bank_verified' => $user->isBankVerified(),

            'is_account_disabled' => !$user->is_active,
            'account_disabled_message' => $user->inactive_reason ?? null,
            // 'is_available_for_delivery'=> $user->isAvailableForDelivery(), // Only for delivery users

            'primary_depot_data' => $userPrimaryDepotData,

        ];


        return $this->successResponse(__('messages.success_messages.success_get'), $meta, 200);
    }


    public function getProfile(Request $request)
    {
        $requestUser = $request->user();

        $user = User::with(['address', 'billAddress'])
            ->find($requestUser->id);

        return $this->successResponse(__('messages.success_messages.success_get'), $user, 200);
    }



    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number,' . $user->id,
        ]);

        $user->name = $request->input('name');
        $user->email = $request->filled('email') ? $request->input('email') : $user->email;
        // $user->phone_number = $request->input('phone_number'); // can not change phone numnber
        $user->save();

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }


    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        // Check current password
        if (!password_verify($request->input('current_password'), $user->password)) {
            return $this->showErrorMessage(__('messages.error_messages.invalid_current_password'), 422);
        }

        $user->password = bcrypt($request->input('new_password'));
        $user->save();



        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }


    // 

    public function saveAddress(AddressRequest $request)
    {

        $data = $request->validated();
        $user = $request->user();

        if ($user->isBuyer()) {
            $data['addr_type'] = AddressTypeEnum::SHIP->value;
        } else if ($user->isSeller()) {
            $data['addr_type'] = AddressTypeEnum::PICK->value;
        } else if ($user->isDelivery()) {
            $data['addr_type'] = AddressTypeEnum::DELIVERY_PARTNER_HUB->value;
        }



        if ($user->addr_code) {
            // UPDATE
            $address = Address::where('addr_code', $user->addr_code)
                ->firstOrFail();

            $address->update($data);

            $event = 'user_address_updated';
        } else {
            // CREATE
            $address = Address::create($data);

            $user->update([
                'addr_code' => $address->addr_code,
            ]);

            $event = 'user_address_added';
        }

        // Log Activity
        logActivity(
            $event,
            $request->user(),
            get_class($user),
            $user->id,
            $user->code,
            [
                'user_code' => $user->code,
                'addr_code' => $address->addr_code,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }


    public function saveBillingAddress(AddressRequest $request)
    {

        $data = $request->validated();
        $user = $request->user();

        if ($user->isBuyer()) {
            $data['addr_type'] = AddressTypeEnum::SHIP->value;
        } else if ($user->isSeller()) {
            $data['addr_type'] = AddressTypeEnum::PICK->value;
        } else if ($user->isDelivery()) {
            $data['addr_type'] = AddressTypeEnum::DELIVERY_PARTNER_HUB->value;
        }



        if ($user->bill_addr_code) {
            // UPDATE
            $address = Address::where('bill_addr_code', $user->bill_addr_code)
                ->firstOrFail();

            $address->update($data);

            $event = 'user_billing_address_updated';
        } else {
            // CREATE
            $address = Address::create($data);

            $user->update([
                'bill_addr_code' => $address->bill_addr_code,
            ]);

            $event = 'user_billing_address_added';
        }

        // Log Activity
        logActivity(
            $event,
            $request->user(),
            get_class($user),
            $user->id,
            $user->code,
            [
                'user_code' => $user->code,
                'bill_addr_code' => $address->bill_addr_code,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.success_update'),
            200
        );
    }


    //
}
