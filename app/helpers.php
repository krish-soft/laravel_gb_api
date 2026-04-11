<?php

use App\Helpers\FileUploadHelper;
use App\Helpers\PrivateFileUploadHelper;
use App\Models\Common\Log\ActivityLog;
use App\Models\Master\MstFinancialYear;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// Global helper for activity logging
if (!function_exists('logActivity')) {

    /**
     * Global activity logger (future-proof)
     *
     * @param string $event Event name
     * @param User|null $actor Who performed the action (user/admin/system)
     * @param string|null $subjectType Affected model class
     * @param int|null $subjectId Affected record ID
     * @param string|null $subjectCode Human-readable code (optional)
     * @param array $meta Extra metadata
     */
    function logActivity(
        string  $event,
        ?User   $actor = null,
        ?string $subjectType = null,
        ?int    $subjectId = null,
        ?string $subjectCode = null,
        array   $meta = []
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
        string                         $path,
        ?string                        $oldFile = null,
        bool                           $deleteOldFile = true,
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
        string                         $path,
        ?string                        $oldFile = null,
        bool                           $deleteOldFile = true,
    ): ?string {

        return PrivateFileUploadHelper::upload(
            $file,
            $path,
            $oldFile,
            $deleteOldFile,
        );
    }
}


if (!function_exists('currentFy')) {
    function currentFy(): ?MstFinancialYear
    {
        // Cache ONLY the ID
        $fyId = Cache::rememberForever('current_fy_id', function () {

            $setting = MstFinanceSetting::withoutGlobalScopes()->first();

            if ($setting && $setting->financial_year_id) {
                return $setting->financial_year_id;
            }

            return MstFinancialYear::withoutGlobalScopes()
                ->where('is_active', 1)
                ->latest()
                ->value('id');
        });

        if (!$fyId) {
            return null;
        }

        // ALWAYS return fresh Eloquent model
        return MstFinancialYear::withoutGlobalScopes()->find($fyId);
    }
}

if (!function_exists('currentFyStart')) {
    function currentFyStart()
    {
        return currentFy()->start_date;
    }
}

if (!function_exists('currentFyEnd')) {
    function currentFyEnd()
    {
        return currentFy()->end_date;
    }
}



if (!function_exists('storeFileWithSignedUrl')) {

    function storeFileWithSignedUrl(
        string $content,
        string $folder = 'temp',
        string $extension = 'pdf',
        string $disk = 'public',
        int $minutes = 5,
        bool $download = true
    ): string {

        $path = $folder . '/' . Str::uuid() . '.' . $extension;

        Storage::disk($disk)->put($path, $content);

        // choose route based on disk
        $route = $disk === 'public' ? 'public.files.view' : 'files.view';

        return URL::temporarySignedRoute(
            $route,
            now()->addMinutes($minutes),
            [
                'path' => $path,
                'download' => $download
            ]
        );
    }
}
