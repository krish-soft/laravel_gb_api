<?php

namespace App\Models\Log;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ActivityLog extends Model
{
    //


    protected $fillable = [
        'user_code',
        'event',
        'subject_type',
        'subject_id',
        'meta',
        'ip_address',
        'user_agent',
        'user_group',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Quick log helper
     */
    public static function log(
        string $event,
        ?string $userCode = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = []
    ): ?self {
        try {

            // 🔥 AUTO IDENTIFY USER GROUP
            $userGroup = 'system';

            if ($userCode) {
                $user = User::where('user_code', $userCode)->first();

                if ($user) {
                    $userGroup = $user->isAdminManagement() ? 'admin' : 'user';
                }
            }

            return self::create([
                'user_code'      => $userCode ? substr($userCode, 0, 20) : null,
                'event'          => substr($event, 0, 50),
                'subject_type'   => $subjectType ? substr($subjectType, 0, 100) : null,
                'subject_id'     => $subjectId,
                'meta'           => $meta ?: null,
                'ip_address'     => request()?->ip() ? substr(request()->ip(), 0, 45) : null,
                'user_agent'     => request()?->userAgent() ? substr(request()->userAgent(), 0, 255) : null,
                'user_group' =>    $userGroup,
            ]);
        } catch (\Throwable $e) {
            // NEVER break main app
            Log::error('Activity log failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // User relationship

    public function userData($userCode)
    {
        return $this->belongsTo(User::class, 'user_code', 'user_code');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_code', 'user_code');
    }

    // 
}
