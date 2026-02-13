<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\Common\User\Legal\VehicleKyc;
use App\Models\Master\Vehicle\MstVehicle;
use App\Models\User;
use App\Services\Common\Legal\Kyc\VehicleKycService;
use Illuminate\Http\Request;
use RuntimeException;

class UserVehicleKycWebController extends Controller
{
    //

    public function showForm(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        $mstVehicles = MstVehicle::active()->get();

        // ONLY GET SAFE STATUS INFO
        $vehicleKycStatus = VehicleKyc::where('user_id', $user->id)
            ->where('is_expired', false)
            ->latest()
            ->first(['id', 'status']); // 👈 ONLY THESE COLUMNS

        return view('user.legal.kyc.vehicle', [
            'user' => $user,
            'mstVehicles' => $mstVehicles,
            'vehicleKycStatus' => $vehicleKycStatus,
        ]);
    }

    public function submitForm(Request $request, VehicleKycService $service)
    {
        $user = User::findOrFail($request->user_id);

        $request->validate([
            'mst_vehicle_id' => 'required|integer|exists:mst_vehicles,id',
            'license_plate_number' => 'required|string|max:30',
            'driving_license_number' => 'required|string|max:50',
            'registration_number' => 'required|string|max:50',
            'insurance_policy_number' => 'required|string|max:50',
            'vehicle_color' => 'required|string|max:50',

            'driving_license_image' => 'required|image',
            'rc_book_image' => 'required|image',
            'insurance_image' => 'required|image',
            'vehicle_front_image' => 'required|image',
            'vehicle_back_image' => 'required|image',
        ]);

        try {

            $service->addVehicleKyc(
                $user,
                $request->all(),
                $request->allFiles()
            );

            return redirect()->back()->with('success', 'Vehicle KYC submitted successfully');
        } catch (RuntimeException $e) {

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
