<?php

namespace App\Http\Controllers\Api\v1\User\Common\Legal;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Services\Common\Legal\Kyc\VehicleKycService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class UserVehicleKycApiController extends ApiResponseWithAuthController
{

    public function signedVehicleKycUrl(Request $request)
    {
        $user = $request->user();

        // create temporary signed url (15 minutes expiry)
        $signedUrl = URL::temporarySignedRoute(
            'vehicle.kyc.form',
            Carbon::now()->addMinutes(30),
            [
                'user_id' => $user->id,
            ]
        );

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'vehicle_kyc_signed_url' => $signedUrl
            ]
        );
    }

    /**
     * ADD VEHICLE KYC
     */
    public function storeVehicleKyc(Request $request, VehicleKycService $vehicleKycService)
    {
        $request->validate([

            'mst_vehicle_id' => 'required|integer|exists:mst_vehicles,id',

            'license_plate_number'   => 'required|string|max:30',
            'driving_license_number' => 'required|string|max:50',
            'registration_number'    => 'required|string|max:50',
            'insurance_policy_number' => 'required|string|max:50',
            'vehicle_color'          => 'required|string|max:50',

            // ===== REQUIRED IMAGES =====
            'driving_license_image' => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'rc_book_image'         => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'insurance_image'       => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'vehicle_front_image'   => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'vehicle_back_image'    => 'required|image|mimes:jpg,jpeg,png|max:4096',

            'picture' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        try {

            $vehicleKycService->addVehicleKyc(
                $request->user(),
                $request->all(),
                $request->allFiles()
            );

            return $this->showSuccessMessage(
                __('messages.success_messages.kyc_submitted')
            );
        } catch (RuntimeException $e) {

            return $this->showErrorMessage($e->getMessage(), 422);
        }
    }


    /**
     * UPDATE / RE-VEHICLE KYC
     */
    public function updateVehicleKyc(Request $request, VehicleKycService $vehicleKycService)
    {
        $request->validate([

            'mst_vehicle_id' => 'nullable|integer|exists:mst_vehicles,id',

            'license_plate_number'   => 'nullable|string|max:30',
            'driving_license_number' => 'nullable|string|max:50',
            'registration_number'    => 'nullable|string|max:50',
            'insurance_policy_number' => 'nullable|string|max:50',
            'vehicle_color'          => 'nullable|string|max:50',

            // ===== OPTIONAL IMAGES =====
            'driving_license_image' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'rc_book_image'         => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'insurance_image'       => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'vehicle_front_image'   => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'vehicle_back_image'    => 'nullable|image|mimes:jpg,jpeg,png|max:4096',

            'picture' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        try {

            $vehicleKycService->updateVehicleKyc(
                $request->user(),
                $request->all(),
                $request->allFiles()
            );

            return $this->showSuccessMessage(
                __('messages.success_messages.kyc_updated')
            );
        } catch (RuntimeException $e) {

            return $this->showErrorMessage($e->getMessage(), 422);
        }
    }

    // End of class
}
