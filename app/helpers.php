<?php

use App\Helpers\FileUploadHelper;
use App\Helpers\PrivateFileUploadHelper;
use App\Models\Common\Log\ActivityLog;
use App\Models\User;

// Global helper for activity logging
if (!function_exists('logActivity')) {

    /**
     * Global activity logger (future-proof)
     *
     * @param string      $event        Event name
     * @param User|null   $actor        Who performed the action (user/admin/system)
     * @param string|null $subjectType  Affected model class
     * @param int|null    $subjectId    Affected record ID
     * @param string|null $subjectCode  Human-readable code (optional)
     * @param array       $meta         Extra metadata
     */
    function logActivity(
        string $event,
        ?User $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectCode = null,
        array $meta = []
    ): void {

        ActivityLog::log(
            event: $event,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: $subjectId,
            subjectCode: $subjectCode,
            meta: $meta
        );
    }
}

/*
|--------------------------------------------------------------------------
| Private File Upload Helper (PAN / Aadhaar / KYC)
|--------------------------------------------------------------------------
*/


if (!function_exists('uploadPublicFile')) {
    function uploadPublicFile(
        ?\Illuminate\Http\UploadedFile $file,
        string $path,
        ?string $oldFile = null,
        bool $deleteOldFile = true,
    ): ?string {

        return FileUploadHelper::upload(
            $file,
            $path,
            $oldFile,
            $deleteOldFile,
            'public',
        );
    }
}


/*
|--------------------------------------------------------------------------
| Private File Upload Helper (PAN / Aadhaar / KYC)
|--------------------------------------------------------------------------
*/


if (!function_exists('uploadPrivateFile')) {
    function uploadPrivateFile(
        ?\Illuminate\Http\UploadedFile $file,
        string $path,
        ?string $oldFile = null,
        bool $deleteOldFile = true,
    ): ?string {

        return PrivateFileUploadHelper::upload(
            $file,
            $path,
            $oldFile,
            $deleteOldFile,
        );
    }
}
