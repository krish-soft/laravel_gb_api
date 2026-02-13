<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\Common\User\Legal\UserKyc;
use App\Models\User;
use App\Services\Common\Legal\Kyc\KycService;
use Illuminate\Http\Request;
use RuntimeException;

class UserKycWebController extends Controller
{
    //

    public function showForm(Request $request)
    {
        $user = User::findOrFail($request->user_id)->select('id', 'name')->first();

        // SAFE STATUS ONLY
        $kycStatus = null;
        $kycStatus = UserKyc::where('user_id', $user->id)
            ->where('is_expired', false)
            ->latest()
            ->first(['id', 'status']);



        return view('user.legal.kyc.user', [
            'user' => $user,
            'kycStatus' => $kycStatus
        ]);
    }


    public function submitForm(Request $request, KycService $service)
    {
        $user = User::findOrFail($request->user_id);

        $request->validate([
            'legal_name' => 'required|string|max:150',

            'aadhaar_number' => 'required|digits:12',
            'aadhaar_front_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'aadhaar_back_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',

            'pan_card_number' => 'nullable|string|max:10',   // PAN is ALWAYS 10 chars
            'pan_card_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'selfie_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',

            'dob' => 'required|date_format:Y-m-d|before:today',
        ]);

        try {

            $service->addKyc(
                $user,
                $request->all(),
                $request->allFiles()
            );

            return redirect()->back()->with('success', 'KYC submitted successfully');
        } catch (RuntimeException $e) {

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
