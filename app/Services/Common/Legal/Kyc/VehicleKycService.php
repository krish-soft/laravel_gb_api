<?php

namespace App\Services\Common\Legal\Kyc;

use App\Enum\Common\Legal\KycStatusEnum;
use App\Enum\Common\Legal\LegalDocumentTypeEnum;
use App\Models\Common\User\Legal\VehicleKyc;
use App\Models\Common\User\Legal\UserLegalDocument;
use App\Models\Delivery\DriverVehicle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VehicleKycService
{
    /* =========================================================
       ===================== ADD VEHICLE KYC ===================
       ========================================================= */

    public function addVehicleKyc(User $user, array $data, array $files): VehicleKyc
    {
        $active = VehicleKyc::where('user_id', $user->id)
            ->where('is_expired', false)
            ->where('status', KycStatusEnum::PENDING->value)
            ->first();

        if ($active) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_already_under_review')
            );
        }

        return DB::transaction(function () use ($user, $data, $files) {

            $this->assertVehicleFiles($files);

            $vehicleKyc = new VehicleKyc();

            $vehicleKyc->fill(array_intersect_key(
                $data,
                array_flip($vehicleKyc->getFillable())
            ));

            $vehicleKyc->user_id = $user->id;
            $vehicleKyc->user_code = $user->user_code;
            $vehicleKyc->status = KycStatusEnum::PENDING->value;
            $vehicleKyc->is_expired = false;

            $vehicleKyc->save();

            logActivity(
                'vehicle_kyc_submitted',
                $user,
                VehicleKyc::class,
                $vehicleKyc->id,
                $vehicleKyc->vehicle_kyc_code,
                [
                    'status' => $vehicleKyc->status,
                ]
            );

            $this->storeDrivingLicenseDoc($user, $vehicleKyc, $files);
            $this->storeRcBookDoc($user, $vehicleKyc, $files);
            $this->storeInsuranceDoc($user, $vehicleKyc, $files);
            $this->storeVehicleImages($user, $vehicleKyc, $files);
            $this->storeVehicleExtraImages($user, $vehicleKyc, $files);

            return $vehicleKyc;
        });
    }

    /* =========================================================
       ==================== UPDATE VEHICLE KYC =================
       ========================================================= */

    public function updateVehicleKyc(User $user, array $data, array $files): VehicleKyc
    {
        $kyc = VehicleKyc::where('user_id', $user->id)
            ->where('is_expired', false)
            ->latest()
            ->first();

        if (!$kyc) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_not_found')
            );
        }

        return DB::transaction(function () use ($user, $kyc, $data, $files) {

            $kyc->fill(array_intersect_key(
                $data,
                array_flip($kyc->getFillable())
            ));

            if (!empty($files['driving_license_image'])) {
                $this->storeDrivingLicenseDoc($user, $kyc, $files);
            }

            if (!empty($files['rc_book_image'])) {
                $this->storeRcBookDoc($user, $kyc, $files);
            }

            if (!empty($files['insurance_image'])) {
                $this->storeInsuranceDoc($user, $kyc, $files);
            }

            if (!empty($files['vehicle_front_image']) || !empty($files['vehicle_back_image'])) {
                $this->storeVehicleImages($user, $kyc, $files);
            }
            if (
                !empty($files['vehicle_left_image']) ||
                !empty($files['vehicle_right_image']) ||
                !empty($files['vehicle_with_driver_image']) ||
                !empty($files['vehicle_cargo_image'])
            ) {
                $this->storeVehicleExtraImages($user, $kyc, $files);
            }

            $kyc->status = KycStatusEnum::PENDING->value;
            $kyc->save();

            logActivity(
                'vehicle_kyc_updated',
                $user,
                VehicleKyc::class,
                $kyc->id,
                $kyc->vehicle_kyc_code,
                [
                    'status' => $kyc->status,
                ]
            );

            return $kyc;
        });
    }

    /* =========================================================
       ================= ADMIN VERIFY VEHICLE KYC ==============
       ========================================================= */

    public function verifyVehicleKyc(
        User $user,
        VehicleKyc $kyc,
        array $data,
        User $loginAdmin
    ): VehicleKyc {

        if (!$loginAdmin->isAdminManagement()) {
            throw new RuntimeException(
                __('messages.error_messages.unauthorized_action')
            );
        }

        if (!in_array($data['status'], KycStatusEnum::casesAsValues())) {
            throw new RuntimeException(__('messages.error_messages.invalid_kyc_status'));
        }

        return DB::transaction(function () use ($kyc, $data, $loginAdmin, $user) {

            $kyc->status = $data['status'];
            $kyc->review_comment = $data['review_comment'] ?? null;
            $kyc->verification_mode = 'admin_user';

            if ($data['status'] === KycStatusEnum::APPROVED->value) {

                $kyc->is_verified = true;
                $kyc->is_expired = false;
                $kyc->expired_at = null;

                // ===== CREATE DRIVER VEHICLE =====
                DriverVehicle::create([
                    'picture' => $kyc->picture,
                    'driver_id' => $kyc->user_id,
                    'vehicle_id' => $kyc->vehicle_id,
                    'license_plate_number' => $kyc->license_plate_number,
                    'vehicle_color' => $kyc->vehicle_color,
                    'is_active' => true,
                    'is_available_for_delivery' => true,
                ]);
            }

            if ($data['status'] === KycStatusEnum::REJECTED->value) {
                $kyc->is_expired = true;
                $kyc->expired_at = now();
                $kyc->is_verified = false;
            }

            $kyc->verified_at = now();
            $kyc->verified_by = $loginAdmin->name;
            $kyc->verified_user_id = $loginAdmin->id;

            $kyc->save();

            UserLegalDocument::where('vehicle_kyc_id', $kyc->id)
                ->update([
                    'status' => $kyc->status,
                    'verified_at' => $kyc->verified_at,
                    'verified_by' => $loginAdmin->name,
                    'verified_user_id' => $loginAdmin->id,
                ]);

            logActivity(
                'admin_vehicle_kyc_verification_processed',
                $loginAdmin,
                VehicleKyc::class,
                $kyc->id,
                $kyc->vehicle_kyc_code,
                [
                    'decision' => $kyc->status,
                    'affected_user_id' => $kyc->user_id,
                ]
            );

            return $kyc;
        });
    }

    /* =========================================================
       ===================== DELETE VEHICLE KYC ================
       ========================================================= */

    public function deleteVehicleKyc(int $kycId, User $loginAdmin, ?string $reason = null): void
    {
        if (!$loginAdmin->isAdminManagement()) {
            throw new RuntimeException(
                __('messages.error_messages.unauthorized_action')
            );
        }

        $kyc = VehicleKyc::where('id', $kycId)->first();

        if (!$kyc) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_not_found')
            );
        }

        if ($kyc->status === KycStatusEnum::APPROVED->value) {
            throw new RuntimeException(
                __('messages.error_messages.kyc_cannot_delete_approved')
            );
        }

        DB::transaction(function () use ($kyc, $loginAdmin, $reason) {

            UserLegalDocument::where('vehicle_kyc_id', $kyc->id)->delete();

            $kyc->is_expired = true;
            $kyc->expired_at = now();
            $kyc->review_comment = $reason;
            $kyc->verified_by = $loginAdmin->name;
            $kyc->verified_user_id = $loginAdmin->id;
            $kyc->save();

            logActivity(
                'admin_vehicle_kyc_deleted',
                $loginAdmin,
                VehicleKyc::class,
                $kyc->id,
                $kyc->vehicle_kyc_code,
                [
                    'affected_user_id' => $kyc->user_id,
                    'reason' => $reason,
                ]
            );

            $kyc->delete();
        });
    }

    /* =========================================================
       ======================= VALIDATION ======================
       ========================================================= */

    protected function assertVehicleFiles(array $files): void
    {
        if (
            empty($files['driving_license_image']) ||
            empty($files['rc_book_image']) ||
            empty($files['insurance_image']) ||
            empty($files['vehicle_front_image']) ||
            empty($files['vehicle_back_image']) ||
            empty($files['vehicle_with_driver_image'])
        ) {
            throw new RuntimeException(
                __('messages.error_messages.vehicle_documents_required')
            );
        }
    }

    /* =========================================================
       ======================= DOCUMENTS =======================
       ========================================================= */

    protected function storeDrivingLicenseDoc(User $user, VehicleKyc $kyc, array $files): void
    {
        $path = uploadPrivateFile(
            $files['driving_license_image'],
            "vehicle_kyc/{$user->user_code}/driving_license"
        );

        UserLegalDocument::updateOrCreate(
            [
                'user_id' => $user->id,
                'vehicle_kyc_id' => $kyc->id,
                'document_type' => LegalDocumentTypeEnum::DRIVING_LICENSE->value
            ],
            [
                'user_code' => $user->user_code,
                'document_path_front' => $path,
            ]
        );
    }

    protected function storeRcBookDoc(User $user, VehicleKyc $kyc, array $files): void
    {
        $path = uploadPrivateFile(
            $files['rc_book_image'],
            "vehicle_kyc/{$user->user_code}/rc_book"
        );

        UserLegalDocument::updateOrCreate(
            [
                'user_id' => $user->id,
                'vehicle_kyc_id' => $kyc->id,
                'document_type' => LegalDocumentTypeEnum::RC_BOOK->value
            ],
            [
                'user_code' => $user->user_code,
                'document_path_front' => $path,
            ]
        );
    }

    protected function storeInsuranceDoc(User $user, VehicleKyc $kyc, array $files): void
    {
        $path = uploadPrivateFile(
            $files['insurance_image'],
            "vehicle_kyc/{$user->user_code}/insurance"
        );

        UserLegalDocument::updateOrCreate(
            [
                'user_id' => $user->id,
                'vehicle_kyc_id' => $kyc->id,
                'document_type' => LegalDocumentTypeEnum::INSURANCE_POLICY->value
            ],
            [
                'user_code' => $user->user_code,
                'document_path_front' => $path,
            ]
        );
    }

    protected function storeVehicleImages(User $user, VehicleKyc $kyc, array $files): void
    {
        $front = uploadPrivateFile(
            $files['vehicle_front_image'],
            "vehicle_kyc/{$user->user_code}/vehicle"
        );

        $back = uploadPrivateFile(
            $files['vehicle_back_image'],
            "vehicle_kyc/{$user->user_code}/vehicle"
        );

        UserLegalDocument::updateOrCreate(
            [
                'user_id' => $user->id,
                'vehicle_kyc_id' => $kyc->id,
                'document_type' => LegalDocumentTypeEnum::VEHICLE_PHOTO->value
            ],
            [
                'user_code' => $user->user_code,
                'document_path_front' => $front,
                'document_path_back' => $back,
            ]
        );
    }

    protected function storeVehicleExtraImages(User $user, VehicleKyc $kyc, array $files): void
    {
        $folder = "vehicle_kyc/{$user->user_code}/vehicle";

        $map = [
            'vehicle_left_image'  => LegalDocumentTypeEnum::VEHICLE_PHOTO,
            'vehicle_right_image' => LegalDocumentTypeEnum::VEHICLE_PHOTO,
            'vehicle_with_driver_image' => LegalDocumentTypeEnum::VEHICLE_WITH_DRIVER,
            'vehicle_cargo_image' => LegalDocumentTypeEnum::VEHICLE_CARGO,
        ];

        foreach ($map as $fileKey => $docType) {

            if (empty($files[$fileKey])) {
                continue;
            }

            $path = uploadPrivateFile($files[$fileKey], $folder);

            UserLegalDocument::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'vehicle_kyc_id' => $kyc->id,
                    'document_type' => $docType->value,
                ],
                [
                    'user_code' => $user->user_code,
                    'document_path_front' => $path,
                ]
            );
        }
    }
}
