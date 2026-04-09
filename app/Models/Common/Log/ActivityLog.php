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

        'related_type',
        'related_id',

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
        array $meta = [],

        ?string $relatedType = null,
        ?int $relatedId = null,
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

                // RELATED
                'related_type' => $relatedType ? substr($relatedType, 0, 100) : null,
                'related_id'   => $relatedId,

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
        return $this->belongsTo(User::class, 'actor_id')->safe();
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function related()
    {
        return $this->morphTo();
    }


    public function toLog(): array
    {
        return [
            'log_type'       => 'activity',

            'subject_type'   => $this->subject_type,
            'subject_id'     => $this->subject_id,

            'related_type'   => $this->related_type,
            'related_id'     => $this->related_id,

            'auditable_type' => null,
            'auditable_id'   => null,

            'action'         => $this->event,
            'meta'           => $this->meta,

            'created_at'     => $this->created_at,
            'log'            => $this,
        ];
    }



    //
}
