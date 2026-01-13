<?php

namespace App\Http\Controllers\Api\v1\User\Common\Legal;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Services\Common\Legal\Kyc\KycService;
use Illuminate\Http\Request;
use RuntimeException;

class UserKycApiController extends ApiResponseWithAuthController
{
    /**
     * ADD KYC (first time / after expiry / rejected)
     */
    public function storeKyc(Request $request, KycService $kycService)
    {
        $request->validate([
            'legal_name' => 'required|string|max:150',

            'aadhaar_number' => 'required|digits:12',
            'aadhaar_front_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'aadhaar_back_image'  => 'required|image|mimes:jpg,jpeg,png|max:2048',

            'pan_card_number' => 'nullable|string|max:15',
            'pan_card_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'dob' => 'required|date_format:Y-m-d|before:today',
        ]);

        // Check DOB is adult base on india

        $dob = $request->input('dob');
        $today = date('Y-m-d');
        $age = date_diff(date_create($dob), date_create($today))->y;

        if ($age < 18) {
            return $this->showErrorMessage(__('messages.error_messages.not_adult'), 422);
        }


        try {
            $kycService->addKyc(
                $request->user(),       // authenticated user
                $request->all(),        // input data
                $request->allFiles()    // uploaded files
            );

            return $this->showSuccessMessage(
                __('messages.success_messages.kyc_submitted')
            );
        } catch (RuntimeException $e) {
            return $this->showErrorMessage($e->getMessage(), 422);
        }
    }

    /**
     * UPDATE / RE-KYC
     */
    public function updateKyc(Request $request, KycService $kycService)
    {
        $request->validate([
            'legal_name' => 'sometimes|string|max:150',

            'aadhaar_number' => 'sometimes|digits:12',
            'aadhaar_front_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
            'aadhaar_back_image'  => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',

            'pan_card_number' => 'sometimes|string|max:15',
            'pan_card_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',

            'dob' => 'nullable|date_format:Y-m-d|before:today',

        ]);

        try {
            $kycService->updateKyc(
                $request->user(),       // authenticated user
                $request->all(),        // input data
                $request->allFiles()    // uploaded files
            );

            return $this->showSuccessMessage(__('messages.success_messages.kyc_updated'));
        } catch (RuntimeException $e) {
            return $this->showErrorMessage($e->getMessage(), 422);
        }
    }





    // End of class
}
