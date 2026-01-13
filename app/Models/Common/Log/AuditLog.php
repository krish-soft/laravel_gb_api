<?php

namespace App\Models\Common\Log;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;


// Do not extend BaseModel to avoid logging loops

class AuditLog extends Model
{
    //

    protected $fillable = [
        'user_code',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'reason',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Quick audit helper
     */
    public static function log(
        string $action,
        Model $model,
        array $oldValues = [],
        array $newValues = [],
        ?string $userCode = null,
        ?string $reason = null
    ): ?self {
        try {
            return self::create([
                'user_code'        => $userCode ? substr($userCode, 0, 20) : null,
                'auditable_type'   => substr(get_class($model), 0, 100),
                'auditable_id'     => $model->getKey(),
                'action'           => substr($action, 0, 20),
                'old_values'       => $oldValues ?: null,
                'new_values'       => $newValues ?: null,
                'reason'           => $reason ? substr($reason, 0, 255) : null,
                'ip_address'       => substr(request()?->ip() ?? '', 0, 45),
                'user_agent'       => substr(request()?->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Throwable $e) {
            // NEVER break main flow
            Log::error('Audit log failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
