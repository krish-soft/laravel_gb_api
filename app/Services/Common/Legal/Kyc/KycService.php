<?php

namespace App\Services\Common\Legal\Kyc;

use App\Enum\Common\Legal\KycStatusEnum;
use App\Enum\Common\Legal\LegalDocumentTypeEnum;
use App\Models\Common\User\Legal\UserKyc;
use App\Models\Common\User\Legal\UserLegalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KycService
{
    /* =========================================================
       ===================== ADD KYC ===========================
       ========================================================= */

    public function addKyc(User $user, array $data, array $files): UserKyc
    {
        // Block only if a pending KYC exists
        $activeKyc = UserKyc::where('user_id', $user->id)
            ->where('is_expired', false)
            ->where('status', KycStatusEnum::PENDING->value)
            ->first();

        if ($activeKyc) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_already_under_review')
            );
        }

        return DB::transaction(function () use ($user, $data, $files) {

            // ---------- CLEAN INPUT ----------
            $aadhaar = !empty($data['aadhaar_number'])
                ? preg_replace('/\D/', '', $data['aadhaar_number'])
                : null;

            $pan = array_key_exists('pan_card_number', $data)
                ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['pan_card_number']))
                : null;

            // ---------- VALIDATION ----------
            $this->assertValidAadhaar($aadhaar, $files);
            $this->assertValidPan($pan, $files, null);

            // ---------- CREATE KYC ----------
            $kyc = new UserKyc();

            // AUTO-FILL all allowed fields
            $kyc->fill(array_intersect_key(
                $data,
                array_flip($kyc->getFillable())
            ));

            // FORCE required / derived fields
            $kyc->user_id = $user->id;
            $kyc->user_code = $user->user_code;
            $kyc->status = KycStatusEnum::PENDING->value;
            $kyc->is_expired = false;
            $kyc->pan_card_number = $pan;
            $kyc->aadhaar_last4 = substr($aadhaar, -4);

            $kyc->save();

            // Update Kyc Code to USer
            $user->kyc_code = $kyc->kyc_code;
            $user->save();

            logActivity(
                'user_kyc_submitted',
                $user,                         // ACTOR (user)
                UserKyc::class,                // SUBJECT TYPE
                $kyc->id,                      // SUBJECT ID
                $kyc->kyc_code,                // SUBJECT CODE
                [
                    'status' => $kyc->status,
                ]
            );


            // ---------- DOCUMENTS ----------
            $this->storeAadhaarDoc($user, $kyc, $aadhaar, $files);

            if ($pan) {
                $this->storePanDoc($user, $kyc, $pan, $files);
            }


            return $kyc;
        });
    }


    /* =========================================================
       ==================== UPDATE / RE-KYC ====================
       ========================================================= */

    public function updateKyc(User $user, array $data, array $files): UserKyc
    {
        $kyc = UserKyc::where('user_id', $user->id)
            ->where('is_expired', false)
            ->latest()
            ->first();

        if (!$kyc) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_not_found')
            );
        }

        return DB::transaction(function () use ($user, $kyc, $data, $files) {

            // ---------- CLEAN INPUT ----------
            $aadhaar = !empty($data['aadhaar_number'])
                ? preg_replace('/\D/', '', $data['aadhaar_number'])
                : null;

            $pan = array_key_exists('pan_card_number', $data)
                ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['pan_card_number']))
                : null;

            // ---------- AUTO-FILL ALL FIELDS ----------
            $kyc->fill(array_intersect_key(
                $data,
                array_flip($kyc->getFillable())
            ));

            // ---------- AADHAAR ----------
            if ($aadhaar) {
                $this->assertValidAadhaar($aadhaar, $files);
                $kyc->aadhaar_last4 = substr($aadhaar, -4);
                $this->storeAadhaarDoc($user, $kyc, $aadhaar, $files);
            }

            // ---------- PAN ----------
            if ($pan !== null) {
                $this->assertValidPan($pan, $files, $user->id);
                $kyc->pan_card_number = $pan;
                $this->storePanDoc($user, $kyc, $pan, $files);
            }

            // ---------- RE-KYC ----------
            $kyc->status = KycStatusEnum::PENDING->value;
            $kyc->save();

            logActivity(
                'user_kyc_updated',
                $user,                         // ACTOR (user)
                UserKyc::class,                // SUBJECT
                $kyc->id,
                $kyc->kyc_code,
                [
                    'status' => $kyc->status,
                ]
            );



            return $kyc;
        });
    }



    /* =========================================================
   =============== ADMIN VERIFY / REVIEW ====================
   ========================================================= */

    public function verifyKyc(
        User $user,
        int $kycId,
        array $data,
        User $loginAdmin
    ): UserKyc {

        if (!$loginAdmin->isAdminManagement()) {
            throw new RuntimeException(
                __('messages.error_messages.unauthorized_action')
            );
        }

        $kyc = UserKyc::where('id', $kycId)->first();

        if (!$kyc) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_not_found')
            );
        }

        if (!in_array($data['status'], [
            KycStatusEnum::PENDING->value,
            KycStatusEnum::APPROVED->value,
            KycStatusEnum::REJECTED->value,
        ])) {
            throw new RuntimeException(__('messages.error_messages.invalid_kyc_status'));
        }

        return DB::transaction(function () use ($kyc, $data, $loginAdmin, $user) {

            $kyc->status = $data['status'];
            $kyc->review_comment = $data['review_comment'] ?? null;
            $kyc->verified_at = now();
            $kyc->verified_by = $loginAdmin->name;
            $kyc->verified_user_id = $loginAdmin->id;

            // If rejected → mark expired (forces re-KYC)
            if ($data['status'] === KycStatusEnum::REJECTED->value) {
                $kyc->is_expired = true;
                $kyc->expired_at = now();
            }

            $kyc->save();

            /* OPTIONAL: update all documents status together */
            if (!empty($kyc->status !== KycStatusEnum::PENDING->value)) {

                UserLegalDocument::where('user_kyc_id', $kyc->id)
                    ->update([
                        'status' => $kyc->status,
                        'verified_at' => $kyc->verified_at,
                        'verified_by' => $loginAdmin->name,
                        'verified_user_id' => $loginAdmin->id,
                    ]);
            }

            // Log
            logActivity(
                'admin_kyc_verification_processed',
                $loginAdmin,                   // ACTOR (admin)
                UserKyc::class,                // SUBJECT
                $kyc->id,
                $kyc->kyc_code,
                [
                    'decision' => $kyc->status,
                    'review_comment' => $kyc->review_comment,
                    'affected_user_id' => $kyc->user_id,
                ]
            );


            return $kyc;
        });
    }

    /* =========================================================
   ===================== DELETE KYC =========================
   ========================================================= */

    public function deleteKyc(
        int $kycId,
        User $loginAdmin,
        string $reason = null
    ): void {

        if (!$loginAdmin->isAdminManagement()) {
            throw new RuntimeException(
                __('messages.error_messages.unauthorized_action')
            );
        }

        $kyc = UserKyc::where('id', $kycId)->first();

        if (!$kyc) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_not_found')
            );
        }

        // Safety rule: do not delete approved KYC unless explicitly allowed
        if ($kyc->status === KycStatusEnum::APPROVED->value) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_cannot_delete_approved')
            );
        }

        DB::transaction(function () use ($kyc, $loginAdmin, $reason) {

            // Soft delete documents first
            UserLegalDocument::where('user_kyc_id', $kyc->id)->delete();

            // Mark KYC as expired + soft delete
            $kyc->is_expired = true;
            $kyc->expired_at = now();
            $kyc->review_comment = $reason;
            $kyc->verified_by = $loginAdmin->name;
            $kyc->verified_user_id = $loginAdmin->id;
            $kyc->save();

            // Log
            logActivity(
                'admin_kyc_deleted',
                $loginAdmin,                   // ACTOR (admin)
                UserKyc::class,                // SUBJECT
                $kyc->id,
                $kyc->kyc_code,
                [
                    'affected_user_id' => $kyc->user_id,
                    'reason' => $reason,
                ]
            );


            $kyc->delete();
        });
    }


    /* =========================================================
       ===================== HELPERS ===========================
       ========================================================= */

    protected function cleanInputs(array $data): array
    {
        $aadhaar = !empty($data['aadhaar_number'])
            ? preg_replace('/\D/', '', $data['aadhaar_number'])
            : null;

        $pan = array_key_exists('pan_card_number', $data)
            ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['pan_card_number']))
            : null;

        return [$aadhaar, $pan];
    }

    /* =========================================================
       ===================== VALIDATION ========================
       ========================================================= */

    protected function assertValidAadhaar(?string $aadhaar, array $files): void
    {
        if (!$aadhaar || !preg_match('/^\d{12}$/', $aadhaar)) {
            throw new RuntimeException(
                __('messages.error_messages.invalid_aadhaar_number')
            );
        }

        if (!$this->verhoeffCheck($aadhaar)) {
            throw new RuntimeException(
                __('messages.error_messages.invalid_aadhaar_number')
            );
        }

        if (empty($files['aadhaar_front_image']) || empty($files['aadhaar_back_image'])) {
            throw new RuntimeException(
                __('messages.error_messages.aadhaar_images_required')
            );
        }
    }

    protected function assertValidPan(?string $pan, array $files, ?int $ignoreUserId): void
    {
        if (!$pan) {
            return;
        }

        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            throw new RuntimeException(
                __('messages.error_messages.invalid_pan_card_format')
            );
        }

        if (empty($files['pan_card_image'])) {
            throw new RuntimeException(
                __('messages.error_messages.pan_card_image_required')
            );
        }

        $query = UserKyc::where('pan_card_number', $pan);
        if ($ignoreUserId) {
            $query->where('user_id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw new RuntimeException(
                __('messages.error_messages.account_already_registered')
            );
        }
    }

    /* =========================================================
       ===================== DOCUMENTS =========================
       ========================================================= */

    protected function storeAadhaarDoc(
        User $user,
        UserKyc $kyc,
        string $aadhaar,
        array $files
    ): void {
        $front = uploadPrivateFile(
            $files['aadhaar_front_image'],
            "user_kyc/{$user->user_code}/aadhaar"
        );

        $back = uploadPrivateFile(
            $files['aadhaar_back_image'],
            "user_kyc/{$user->user_code}/aadhaar"
        );

        UserLegalDocument::updateOrCreate(
            [
                'user_id' => $user->id,
                'user_kyc_id' => $kyc->id,
                'document_type' => LegalDocumentTypeEnum::AADHAAR->value
            ],
            [
                'user_id' => $user->id,
                'user_code' => $user->user_code,
                'document_number_last4' => substr($aadhaar, -4),
                'document_path_front' => $front,
                'document_path_back' => $back,
            ]
        );
    }

    protected function storePanDoc(
        User $user,
        UserKyc $kyc,
        string $pan,
        array $files
    ): void {
        $path = uploadPrivateFile(
            $files['pan_card_image'],
            "user_kyc/{$user->user_code}/pan_card"
        );

        UserLegalDocument::updateOrCreate(
            [
                'user_id' => $user->id,
                'user_code' => $user->user_code,
                'user_kyc_id' => $kyc->id,
                'document_type' => LegalDocumentTypeEnum::PAN_CARD->value
            ],
            [
                'user_id' => $user->id,
                'user_code' => $user->user_code,
                // 'document_number' => $pan,
                'document_number_encrypted' => $pan,
                'document_number_last4' => substr($pan, -4),
                'document_path_front' => $path,
            ]
        );
    }

    /* =========================================================
       ===================== AADHAAR ===========================
       ========================================================= */

    protected function verhoeffCheck(string $num): bool
    {
        $d = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
            [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
            [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
            [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
            [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
            [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
            [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
            [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
            [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
        ];

        $p = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
            [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
            [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
            [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
            [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
            [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
            [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
        ];

        $c = 0;
        foreach (array_reverse(str_split($num)) as $i => $n) {
            $c = $d[$c][$p[$i % 8][$n]];
        }

        return $c === 0;
    }
}
