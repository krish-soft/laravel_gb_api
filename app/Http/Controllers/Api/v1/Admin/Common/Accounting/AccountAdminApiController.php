<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Accounting;

use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Accounting\Account;
use Illuminate\Http\Request;

class AccountAdminApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //

        $request->validate([
            'accnt_code' => 'nullable|string',
            'owner_id' => 'nullable|integer',
        ]);


        $accntCode = $request->input('accnt_code');
        $ownerId = $request->input('owner_id');

        $accntQuery = Account::oldest();

        if ($accntCode) {
            $accntQuery->where('accnt_code', $accntCode);
        }

        if ($ownerId) {
            $accntQuery->where('owner_id', $ownerId);
        }


        $accounts = $accntQuery->limit(100)->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $accounts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:255',
            'owner_type' => 'required|string|max:255',
            'owner_id' => 'nullable|integer',
            'currency' => 'required|string|max:3',
            'type' => 'required|string|max:255',
        ]);


        $account =   Account::create($request->all());

        //log activity

        /// Log activity
        logActivity(
            'account_created',
            $request->user(),       // ACTOR (who did it)
            get_class($account),       // SUBJECT TYPE (what was affected)
            $account->id,              // SUBJECT ID
            $account->accnt_code,       // SUBJECT CODE (human readable)
            [
                'accnt_code' => $account->accnt_code,
                'owner_type' => $account->owner_type,
                'owner_id' => $account->owner_id,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $account);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account)
    {
        //

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'owner_type' => 'sometimes|string|max:255',
            'owner_id' => 'sometimes|nullable|integer',
            'currency' => 'sometimes|string|max:3',
            'type' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'inactive_reason' => 'sometimes|nullable|string|max:1000',
        ]);

        $account->update($request->all());

        /// Log activity
        logActivity(
            'account_updated',
            $request->user(),       // ACTOR (who did it)
            get_class($account),       // SUBJECT TYPE (what was affected)
            $account->id,              // SUBJECT ID
            $account->accnt_code,       // SUBJECT CODE (human readable)
            [
                'accnt_code' => $account->accnt_code,
                'owner_type' => $account->owner_type,
                'owner_id' => $account->owner_id,
                'is_active' => $account->is_active,
                'inactive_reason' => $account->inactive_reason,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        //
        // Check if ledgers has data and the accnt codes from AccntCodeEnum::casesAsValues()
        if (
            $account->ledgers()->exists()
            || in_array($account->accnt_code, PlatformAccountCodeEnum::casesAsValues())
        ) {
            return $this->errorResponse(__('messages.error_messages.user_detlete_prohibited'), 403);
        }

        /// Log activity
        logActivity(
            'account_deleted',
            request()->user(),       // ACTOR (who did it)
            get_class($account),       // SUBJECT TYPE (what was affected)
            $account->id,              // SUBJECT ID
            $account->accnt_code,       // SUBJECT CODE (human readable)
            [
                'accnt_code' => $account->accnt_code,
                'owner_type' => $account->owner_type,
                'owner_id' => $account->owner_id,
            ]
        );

        $account->delete();


        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }
}
