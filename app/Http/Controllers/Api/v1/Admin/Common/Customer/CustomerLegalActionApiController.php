<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Customer;

use App\Enum\Common\Legal\KycStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\User\Legal\UserKyc;
use App\Models\Common\User\Legal\UserLegalDocument;
use App\Models\Common\User\Legal\VehicleKyc;
use App\Models\Common\User\UserDepot;
use App\Models\User;
use App\Services\Common\Legal\BankService;
use App\Services\Common\Legal\Kyc\KycService;
use App\Services\Common\Legal\Kyc\VehicleKycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerLegalActionApiController extends ApiResponseWithAdminAuthController
{
    //
    protected KycService $kycService;

    protected BankService $bankService;

    protected VehicleKycService $vehicleKycService;

    public function __construct(KycService $kycService, BankService $bankService, VehicleKycService $vehicleKycService)
    {
        $this->kycService = $kycService;
        $this->bankService = $bankService;
        $this->vehicleKycService = $vehicleKycService;
    }

    /**
     *  KYC List
     */
    public function addNewKyc(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',

            'legal_name' => 'required|string|max:150',

            'aadhaar_number' => 'required|digits:12',
            'aadhaar_front_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'aadhaar_back_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',

            'pan_card_number' => 'nullable|string|max:15',
            'pan_card_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'selfie_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', // optional selfie image

            'dob' => 'required|date_format:Y-m-d|before:today',
        ]);

        $user = User::where('id', $request->user_id)->firstOrFail();

        $this->kycService->addKyc(
            $user,                   // user
            $request->all(),        // input data
            $request->allFiles()    // uploaded files
        );

        return $this->successResponse(__('messages.success_messages.kyc_submitted'), null, 200);
        //
    }

    public function getKycList(Request $request)
    {
        //
        $userKycQuery = UserKyc::with('user:id,user_code,name')->latest();

        if ($request->has('status') && ! is_null($request->status)) {
            $userKycQuery->where('status', $request->status);
        } else {
            // if (!request()->user()->isSuperAdminGroup()) {
            $userKycQuery->whereIn('status', [KycStatusEnum::PENDING->value, KycStatusEnum::UNDER_REVIEW->value]);
            // }
        }

        $userKycList = $userKycQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $userKycList, 200);
    }

    public function getKycDetails(Request $request, $kycId)
    {
        //

        $userKyc = UserKyc::with(['legalDocuments', 'user'])->where('id', $kycId)->firstOrFail();

        // If status is PENDING, change to UNDER_REVIEW
        if ($userKyc->status === KycStatusEnum::PENDING->value) {
            $userKyc->status = KycStatusEnum::UNDER_REVIEW->value;
            $userKyc->save();
        }

        // DO CRUD before this
        $depots = UserDepot::with(['depot', 'user'])
            ->where('user_id', $userKyc->user_id)
            ->get();

        $userKyc->depots = $depots;

        // log Activity
        logActivity(
            'admin_user_seen_kyc_details',
            $request->user(),       // ACTOR (who did it)
            get_class($userKyc),       // SUBJECT TYPE (what was affected)
            $userKyc->id,              // SUBJECT ID
            $userKyc->kyc_code,       // SUBJECT CODE (human readable)
            [
                'kyc_code' => $userKyc->kyc_code,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_get'), $userKyc, 200);
    }

    public function updateKycStatus(Request $request)
    {
        //
        $request->validate([
            'kyc_id' => 'required|integer',
            'status' => 'required|string',
            'review_comment' => 'required|string',
        ]);

        $kycId = $request->kyc_id;
        $status = $request->status;

        $userKyc = UserKyc::where('id', $kycId)->firstOrFail();
        $user = $userKyc->user;

        $requestData = [
            'status' => $status,
            'review_comment' => $request->review_comment,
            'is_re_kyc' => $request->is_re_kyc ?? false,
        ];

        $this->kycService->verifyKyc(
            $user,
            $userKyc,
            $requestData,
            $request->user()
        );

        return $this->successResponse(__('messages.success_messages.success_update'), $userKyc, 200);
    }

    /**
     *  Legal Document List
     */
    public function getLegalDocumentList(Request $request)
    {
        //
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $userId = $request->user_id;

        $legalDocuments = UserLegalDocument::where('user_id', $userId)->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $legalDocuments, 200);
    }

    public function deleteLegalDocument(Request $request)
    {
        //
        $request->validate([
            'document_id' => 'required|integer',
        ]);

        $documentId = $request->document_id;

        $userLegalDocument = UserLegalDocument::where('id', $documentId)->firstOrFail();

        $userLegalDocument->delete();

        // Log Activity
        logActivity(
            'user_legal_document_deleted',
            $request->user(),       // ACTOR (who did it)
            get_class($userLegalDocument),       // SUBJECT TYPE (what was affected)
            $userLegalDocument->id,              // SUBJECT ID
            $userLegalDocument->document_code,       // SUBJECT CODE (human readable)
            [
                'document_code' => $userLegalDocument->document_code,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_delete'), null, 200);
    }

    /**
     *  Vehicle KYC List
     */
    public function getVehicleKycList(Request $request)
    {
        //

        $vehicleKycQuery = VehicleKyc::with('user')->latest();

        if ($request->has('status') && ! is_null($request->status)) {
            $vehicleKycQuery->where('status', $request->status);
        } else {
            if (! request()->user()->isSuperAdminGroup()) {
                $vehicleKycQuery->whereIn('status', [KycStatusEnum::PENDING->value, KycStatusEnum::UNDER_REVIEW->value]);
            }
        }

        $vehicleKycList = $vehicleKycQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $vehicleKycList, 200);
    }

    public function getVehicleKycDetails(Request $request, $kycId)
    {
        //

        $vehicleKyc = VehicleKyc::with(['user', 'legalDocuments'])->where('id', $kycId)->firstOrFail();

        // If status is PENDING, change to UNDER_REVIEW
        if ($vehicleKyc->status === KycStatusEnum::PENDING->value) {
            $vehicleKyc->status = KycStatusEnum::UNDER_REVIEW->value;
            $vehicleKyc->save();
        }

        // log Activity
        logActivity(
            'admin_user_seen_kyc_details',
            $request->user(),       // ACTOR (who did it)
            get_class($vehicleKyc),       // SUBJECT TYPE (what was affected)
            $vehicleKyc->id,              // SUBJECT ID
            $vehicleKyc->vehicle_kyc_code,       // SUBJECT CODE (human readable)
            [
                'vehicle_kyc_code' => $vehicleKyc->vehicle_kyc_code,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_get'), $vehicleKyc, 200);
    }

    public function updateVehicleKycStatus(Request $request)
    {
        //
        $request->validate([
            'vehicle_kyc_id' => 'required|integer',
            'status' => 'required|string',
            'review_comment' => 'required|string',
        ]);

        $kycId = $request->vehicle_kyc_id;
        $status = $request->status;

        $vehicleKyc = VehicleKyc::where('id', $kycId)->firstOrFail();
        $user = $vehicleKyc->user;

        $requestData = [
            'status' => $status,
            'review_comment' => $request->review_comment,
        ];

        $this->vehicleKycService->verifyVehicleKyc(
            $user,
            $vehicleKyc,
            $requestData,
            $request->user()
        );

        return $this->successResponse(__('messages.success_messages.success_update'), $vehicleKyc, 200);
    }

    //
}
