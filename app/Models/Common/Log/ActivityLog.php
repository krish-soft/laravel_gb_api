<?php

namespace App\Models\Common\Log;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

// Do not extend BaseModel to avoid logging loops

class ActivityLog extends Model
{

    protected $fillable = [
        'event',

        // ACTOR (who did it)
        'actor_type',
        'actor_id',
        'actor_code',

        // SUBJECT (what / whose data)
        'subject_type',
        'subject_id',
        'subject_code',

        // Extra context
        'meta',
        'ip_address',
        'user_agent',
        'user_group',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* =========================================================
       ===================== QUICK LOGGER ======================
       ========================================================= */

    public static function log(
        string $event,

        // ACTOR
        ?User $actor = null,

        // SUBJECT
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectCode = null,

        // META
        array $meta = []
    ): ?self {
        try {

            // ---------- ACTOR INFO ----------
            $actorType = 'system';
            $actorId   = null;
            $actorCode = null;
            $userGroup = 'system';

            if ($actor) {
                $actorType = $actor->isAdminManagement() ? 'admin' : 'user';
                $actorId   = $actor->id;
                $actorCode = substr($actor->user_code, 0, 100);
                $userGroup = $actorType;
            }

            return self::create([
                'event'        => substr($event, 0, 100),

                // ACTOR
                'actor_type'   => $actorType,
                'actor_id'     => $actorId,
                'actor_code'   => $actorCode,

                // SUBJECT
                'subject_type' => $subjectType ? substr($subjectType, 0, 100) : null,
                'subject_id'   => $subjectId,
                'subject_code' => $subjectCode ? substr($subjectCode, 0, 100) : null,

                // META
                'meta'         => $meta ?: null,

                // REQUEST INFO
                'ip_address'   => request()?->ip() ? substr(request()->ip(), 0, 45) : null,
                'user_agent'   => request()?->userAgent()
                    ? substr(request()->userAgent(), 0, 255)
                    : null,

                'user_group'   => $userGroup,
            ]);
        } catch (\Throwable $e) {
            // NEVER break main app
            Log::error('Activity log failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /* =========================================================
       ===================== RELATIONS =========================
       ========================================================= */

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
