<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    //


    protected $fillable = [
        'user_id',
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
        ?int $userId = null,
        ?string $reason = null
    ): self {
        return self::create([
            'user_id'        => $userId,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'action'         => $action,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'reason'         => $reason,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
        ]);
    }
}
