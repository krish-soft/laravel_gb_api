<?php

use App\Models\Log\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

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

use App\Helpers\PrivateFileUploadHelper;

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
