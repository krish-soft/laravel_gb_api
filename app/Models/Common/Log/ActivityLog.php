<?php

namespace App\Models\Common\Log;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ActivityLog extends Model
{
    protected $fillable = [
        'event',

        'actor_type',
        'actor_id',
        'actor_code',
        'actor_snapshot',

        'subject_type',
        'subject_id',
        'subject_code',
        'subject_snapshot',

        'related_type',
        'related_id',

        'meta',
        'ip_address',
        'user_agent',
        'user_group',
    ];

    protected $casts = [
        'meta' => 'array',
        'actor_snapshot' => 'array',
        'subject_snapshot' => 'array',
    ];

    /* ===================== LOGGER ====================== */

    public static function log(
        string $event,
        ?User $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectCode = null,
        array $meta = [],
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): ?self {
        try {

            /* ---------- ACTOR ---------- */
            $actorType = 'system';
            $actorId = null;
            $actorCode = null;
            $userGroup = 'system';

            $actorSnapshot = null;

            if ($actor) {
                $actorType = $actor->isAdminManagement() ? 'admin' : 'user';

                $actorId = $actor->id;
                $actorCode = $actor->user_code;
                $userGroup = $actorType;

                $actorSnapshot = [
                    'id' => $actor->id,
                    'user_code' => $actor->user_code,
                    'name' => $actor->name,
                    'email' => $actor->email,
                    'phone_number' => $actor->phone_number,
                    'role' => $actor->role,
                    'user_type' => $actor->user_type,
                ];
            }

            /* ---------- SUBJECT ---------- */
            $subjectSnapshot = null;

            if ($subjectType && $subjectId) {
                $subjectSnapshot = [
                    'id' => $subjectId,
                    'code' => $subjectCode,
                    'type' => $subjectType,
                ];
            }

            /* ---------- CREATE LOG ---------- */
            return self::create([
                'event' => substr($event, 0, 100),

                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_code' => $actorCode,
                'actor_snapshot' => $actorSnapshot,

                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_code' => $subjectCode,
                'subject_snapshot' => $subjectSnapshot,

                'related_type' => $relatedType,
                'related_id' => $relatedId,

                'meta' => $meta ?: null,

                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),

                'user_group' => $userGroup,
            ]);

        } catch (\Throwable $e) {
            Log::error('Activity log failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /* ===================== RELATIONS ====================== */

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function related()
    {
        return $this->morphTo();
    }
}