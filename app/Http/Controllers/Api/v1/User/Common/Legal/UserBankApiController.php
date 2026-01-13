<?php

namespace App\Http\Controllers\Api\v1\User\Common\Legal;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Common\User\Legal\UserBank;
use App\Services\Common\Legal\BankService;
use Illuminate\Http\Request;

class UserBankApiController extends ApiResponseWithAuthController
{
    protected BankService $bankService;

    public function __construct(BankService $bankService)
    {
        $this->bankService = $bankService;
    }

    public function index(Request $request)
    {
        try {
            $banks = $this->bankService->listBanks($request->user());
        } catch (\Exception $e) {
            return $this->showErrorMessage(
                $e->getMessage(),
                $e->getCode() ?: 400
            );
        }

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $banks,
            200
        );
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'account_holder_name' => 'required|string|max:150',
            'account_number'      => 'required|string|max:20',
            'ifsc_code'           => 'required|string|max:20',
            'bank_name'           => 'required|string|max:100',
            'branch_name'         => 'required|string|max:100',
            'account_type'        => 'required|string|max:50',
        ]);

        // Only one allowed per user with same last 4 digits

        try {
            $bank = $this->bankService->addBank(
                $request->user(),
                $validated
            );
        } catch (\Exception $e) {
            return $this->showErrorMessage(
                $e->getMessage(),
                $e->getCode() ?: 400
            );
        }

        return $this->successResponse(
            __('messages.success_messages.bank_added'),
            $bank,
            201
        );
    }

    public function show(Request $request, UserBank $userBank)
    {
        if ($userBank->user_id !== $request->user()->id) {
            return $this->errorResponse(
                __('messages.error_messages.unauthorized_action'),
                403
            );
        }

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $userBank,
            200
        );
    }

    public function update(Request $request, UserBank $userBank)
    {
        if ($userBank->user_id !== $request->user()->id) {
            return $this->errorResponse(
                __('messages.error_messages.unauthorized_action'),
                403
            );
        }

        $validated = $request->validate([
            'account_holder_name' => 'required|string|max:150',
            'ifsc_code'           => 'required|string|max:20',
            'bank_name'           => 'required|string|max:100',
            'branch_name'         => 'required|string|max:100',
            'account_type'        => 'required|string|max:50',
        ]);

        try {
            $bank = $this->bankService->updateBank(
                $userBank,
                $validated,
                $request->user()
            );
        } catch (\Exception $e) {
            return $this->showErrorMessage(
                $e->getMessage(),
                $e->getCode() ?: 400
            );
        }

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    public function destroy(Request $request, UserBank $userBank)
    {
        if ($userBank->user_id !== $request->user()->id) {
            return $this->errorResponse(__('messages.error_messages.unauthorized_action'), 403);
        }

        try {
            $this->bankService->deleteBank(
                $userBank,
                $request->user()
            );
        } catch (\Exception $e) {
            return $this->showErrorMessage(
                $e->getMessage(),
                $e->getCode() ?: 400
            );
        }

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
