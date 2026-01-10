<?php

use App\Models\Log\ActivityLog;


// Global helper for activity logging
if (!function_exists('logActivity')) {
    function logActivity(
        string $event,
        ?string $userCode = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = []
    ): void {
        ActivityLog::log(
            $event,
            $userCode,
            $subjectType,
            $subjectId,
            $meta
        );
    }
}
