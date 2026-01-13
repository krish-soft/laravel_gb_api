<?php

namespace App\Services\Common\Legal;

use App\Enum\Common\Legal\BankStatusEnum;
use App\Models\Common\User\Legal\UserBank;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BankService
{
    public function listBanks(User $user)
    {
        return UserBank::where('user_id', $user->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function addBank(User $user, array $data, ?User $actor = null): UserBank
    {

        return DB::transaction(function () use ($user, $data, $actor) {


            if (UserBank::where('user_id', $user->id)
                // ->where('account_number_last4', substr($data['account_number'], -4))
                ->exists()
            ) {
                throw new RuntimeException(__('messages.error_messages.bank_already_exists'));
            }

            $isPrimary = !UserBank::where('user_id', $user->id)->exists();

            $bank = UserBank::create([
                'user_id' => $user->id,
                'account_holder_name' => $data['account_holder_name'],
                'account_number_encrypted' => trim($data['account_number']),
                'account_number_last4' => substr(trim($data['account_number']), -4),
                'ifsc_code' => strtoupper(trim($data['ifsc_code'])),
                'bank_name' => trim($data['bank_name']),
                'branch_name' => trim($data['branch_name']),
                'account_type' => $data['account_type'],
                'status' => BankStatusEnum::PENDING->value,
                'is_primary' => $isPrimary,
            ]);

            logActivity(
                'bank_added',
                $actor ?? $user,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                [
                    'user_id' => $user->id,
                    'status' => $bank->status,
                    'is_primary' => $isPrimary,
                ]
            );

            return $bank;
        });
    }

    public function updateBank(UserBank $bank, array $data, User $actor): UserBank
    {
        if ($bank->verified_at) {
            throw new RuntimeException(__('messages.error_messages.bank_verified_locked'));
        }

        $bank->fill(array_intersect_key($data, array_flip([
            'account_holder_name',
            'ifsc_code',
            'bank_name',
            'branch_name',
            'account_type',
        ])));

        $bank->ifsc_code = strtoupper(trim($bank->ifsc_code));
        $bank->status = BankStatusEnum::PENDING->value;
        $bank->save();

        logActivity(
            'bank_updated',
            $actor,
            UserBank::class,
            $bank->id,
            $bank->bank_code,
            [
                'status' => $bank->status,
            ]
        );

        return $bank;
    }

    public function reviewBank(
        UserBank $bank,
        string $decision,
        User $admin,
        ?string $comment = null
    ): UserBank {
        if (!$admin->isAdminManagement()) {
            throw new RuntimeException(__('messages.error_messages.unauthorized_action'));
        }

        return DB::transaction(function () use ($bank, $decision, $admin, $comment) {

            $bank->status = $decision;
            $bank->review_comment = $comment;

            if ($decision === BankStatusEnum::VERIFIED) {
                $bank->verified_at = now();
                $bank->verified_user_id = $admin->id;
                $bank->verified_by = $admin->name;
            } else {
                $bank->verified_at = null;
                $bank->verified_user_id = null;
                $bank->verified_by = null;
            }

            $bank->save();

            logActivity(
                'bank_reviewed',
                $admin,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                [
                    'decision' => $decision,
                    'user_id' => $bank->user_id,
                    'comment' => $comment,
                ]
            );

            return $bank;
        });
    }

    public function setPrimary(User $user, UserBank $bank): void
    {
        if ($bank->user_id !== $user->id) {
            throw new RuntimeException(__('messages.error_messages.unauthorized_action'));
        }

        DB::transaction(function () use ($user, $bank) {

            UserBank::where('user_id', $user->id)->update(['is_primary' => false]);

            $bank->is_primary = true;
            $bank->save();

            logActivity(
                'bank_primary_set',
                $user,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                []
            );
        });
    }

    public function deleteBank(UserBank $bank, User $actor, ?string $reason = null): void
    {
        if ($bank->is_primary) {
            throw new RuntimeException(__('messages.error_messages.primary_bank_delete_forbidden'));
        }

        // TODO:: Pending Payments check

        DB::transaction(function () use ($bank, $actor, $reason) {

            logActivity(
                'bank_deleted',
                $actor,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                [
                    'reason' => $reason,
                ]
            );

            $bank->delete();
        });
    }
}
