<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Customer;

use App\Enum\Common\Legal\KycStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\User\Legal\UserKyc;
use App\Models\Common\User\Legal\UserLegalDocument;
use App\Services\Common\Legal\BankService;
use App\Services\Common\Legal\Kyc\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerLegalActionApiController extends ApiResponseWithAdminAuthController
{
    //
    protected KycService $kycService;
    protected BankService $bankService;

    public function __construct(KycService $kycService, BankService $bankService) {}

    /**
     *  KYC List
     */

    public function getKycList(Request $request)
    {
        //
        $userKycQuery = UserKyc::with('user:id,user_code,name')->latest();

        if ($request->has('status') && !is_null($request->status)) {
            $userKycQuery->where('status', $request->status);
        } else {
            $userKycQuery->where('status',  KycStatusEnum::PENDING->value);
        }

        $userKycList = $userKycQuery->get();


        return  $this->successResponse(__('messages.success_messages.success_get'),  $userKycList, 200);
    }


    public function getKycDetails(Request $request, $kycId)
    {
        //

        $userKyc = UserKyc::with('legalDocuments')->where('id', $kycId)->firstOrFail();

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

        return  $this->successResponse(__('messages.success_messages.success_get'), $legalDocuments, 200);
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

        return  $this->successResponse(__('messages.success_messages.success_delete'), null, 200);
    }


    /**
     *  Bank List
     */



    //
}
