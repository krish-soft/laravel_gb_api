<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    //

    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Quick log helper
     */
    public static function log(
        string $event,
        ?int $userId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = []
    ): self {
        return self::create([
            'user_id'      => $userId,
            'event'        => $event,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'meta'         => $meta,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);
    }
}
